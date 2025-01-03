<?php
/*
    Plugin Name: OpenKBS
    Description: Connect AI Agents to your WordPress
    Version: 1.0.0
    Author: OpenKBS
    Text Domain: openkbs
    Domain Path: /languages
    License: GPL v3
    License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

require_once plugin_dir_path(__FILE__) . 'src/openkbs-utils.php';
require_once plugin_dir_path(__FILE__) . 'src/settings/openkbs-admin.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-api.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-filesystem-api.php';
require_once plugin_dir_path(__FILE__) . 'src/openkbs-meta-plugin-api.php';
require_once plugin_dir_path(__FILE__) . 'src/events-woo.php';
require_once plugin_dir_path(__FILE__) . 'src/events-wpcf7.php';
require_once plugin_dir_path(__FILE__) . 'src/events-wordpress.php';
require_once plugin_dir_path(__FILE__) . 'src/semantic-search.php';
require_once plugin_dir_path(__FILE__) . 'src/search-widget.php';
require_once plugin_dir_path(__FILE__) . 'src/chat-widget.php';


class OpenKBS_AI_Plugin {
    // Whitelist of API namespaces that can be accessed with HTTP_WP_API_KEY
    private $allowed_api_namespaces = [
        'wp/v2',
        'wc/v3',
        'openkbs/v1'
    ];

    private $active_plugins = [];
    private $public_search_enabled = null;

    public function __construct() {
        $this->active_plugins = apply_filters('active_plugins', get_option('active_plugins'));

        // Enable REST API
        add_filter('rest_enabled', '__return_true');
        add_filter('rest_jsonp_enabled', '__return_true');

        // Remove any potential REST API restrictions
        remove_filter('rest_api_init', 'disable_rest_api');

        // Ensure proper URL rewriting for REST API
        add_filter('rest_url_prefix', function($prefix) {
            return 'wp-json';
        });

        add_action('rest_api_init', array($this, 'register_api_key_authentication'), 15);
        add_action('rest_api_init', array($this, 'openkbs_register_endpoints'));
        add_action('wp_ajax_register_openkbs_app', 'openkbs_register_app');
        add_action('wp_ajax_nopriv_register_openkbs_app', 'openkbs_register_app');
        add_action('wp_ajax_delete_openkbs_app', 'openkbs_delete_app');
        add_action('wp_ajax_process_posts_for_indexing', 'openkbs_ajax_process_posts');

        add_action('admin_enqueue_scripts', 'openkbs_enqueue_scripts');
        add_action('admin_enqueue_scripts', 'openkbs_enqueue_polling_scripts');
        add_action('wp_ajax_openkbs_check_callback', 'openkbs_handle_polling');
        add_action('wp_ajax_toggle_filesystem_api', 'openkbs_handle_filesystem_api_toggle');
        add_action('wp_ajax_toggle_public_search', 'openkbs_handle_public_search_toggle');
        add_action('wp_ajax_openkbs_create_public_chat_token', 'openkbs_ajax_create_public_chat_token');
        add_action('wp_ajax_nopriv_openkbs_create_public_chat_token', 'openkbs_ajax_create_public_chat_token');
        add_action('wp_ajax_get_default_config_function', function() {
            wp_send_json_success(openkbs_get_default_config_function());
        });

        add_shortcode('openkbs_search', array($this, 'render_search_widget'));

        add_filter('admin_footer_text', 'openkbs_modify_admin_footer_text');
        add_filter('update_footer', 'openkbs_remove_update_footer', 11);

        add_action('init', 'openkbs_hook_wordpress_events');

        if (in_array('woocommerce/woocommerce.php', $this->active_plugins)) {
            add_action('init', 'openkbs_hook_woocommerce_events');
        }
    
        if (in_array('contact-form-7/wp-contact-form-7.php', $this->active_plugins)) {
            add_action('init', 'openkbs_hook_wpcf7_events');
        }

        add_action('wp_footer', 'openkbs_render_chat_widget');
    }

    private function is_openkbs_public_search_enabled() {
        if ($this->public_search_enabled === null) {
            $this->public_search_enabled = (bool) get_option('openkbs_public_search_enabled');
        }
        return $this->public_search_enabled;
    }

    public function render_search_widget($atts) {
        openkbs_enqueue_search_widget_assets();
        return openkbs_get_search_widget_html($atts);
    }

    public function register_api_key_authentication() {
        // Run after default authentication (which is at priority 10)
        add_filter('rest_authentication_errors', array($this, 'validate_api_key'), 90);
    }

    public function openkbs_register_endpoints() {
        register_rest_route('openkbs/v1', '/callback', array(
            'methods' => 'POST',
            'callback' => 'openkbs_handle_callback',
            'permission_callback' => array($this, 'check_openkbs_permission')
        ));

        register_rest_route('openkbs/v1', '/search', array(
            'methods' => 'GET',
            'callback' => 'openkbs_handle_search',
            'permission_callback' => array($this, 'check_openkbs_permission')
        ));

        register_rest_route('openkbs/v1', '/search-public', array(
            'methods' => 'GET',
            'callback' => 'openkbs_handle_search',
            'permission_callback' => array($this, 'check_public_search_permission')
        ));
    }

    public function check_public_search_permission() {
        // Check if public search is enabled
        if (!$this->is_openkbs_public_search_enabled()) {
            return new WP_Error(
                'rest_forbidden',
                'Public search is not enabled.',
                array('status' => 403)
            );
        }

        $this->set_current_user_with_full_access();
        return true;
    }

    public function check_openkbs_permission() {
        $api_key_header = isset($_SERVER['HTTP_WP_API_KEY']) ? $_SERVER['HTTP_WP_API_KEY'] : '';
        if (empty($api_key_header) || !$this->validate_api_key_against_db($api_key_header)) {
            return new WP_Error(
                'rest_forbidden',
                'Invalid or missing API key for OpenKBS endpoint.',
                array('status' => 403)
            );
        }

        $this->set_current_user_with_full_access();
        return true;
    }

    public function validate_api_key($result) {
        // If another authentication method has already failed, return that error
        if (is_wp_error($result)) {
            return $result;
        }

        $api_key_header = isset($_SERVER['HTTP_WP_API_KEY']) ? $_SERVER['HTTP_WP_API_KEY'] : '';
        $current_route = $this->get_current_route();
        $is_allowed_namespace = $this->is_allowed_namespace($current_route);

        // Check if this is the public search endpoint and if public search is enabled
        if ($current_route === 'openkbs/v1/search-public' && $this->is_openkbs_public_search_enabled()) {
            $this->set_current_user_with_full_access();
            return null; // Allow access without API key
        }

        // If API key is provided and route is in allowed namespaces
        if (!empty($api_key_header) && $is_allowed_namespace) {
            if ($this->validate_api_key_against_db($api_key_header)) {
                $this->set_current_user_with_full_access();
                return null; // Proceed with request
            } else {
                return new WP_Error(
                    'rest_forbidden',
                    'Invalid API key provided.',
                    array('status' => 403)
                );
            }
        }

        // For OpenKBS endpoints (except public search), always require API key
        if (strpos($current_route, 'openkbs/v1') === 0) {
            return new WP_Error(
                'rest_forbidden',
                'API key required for OpenKBS endpoints.',
                array('status' => 403)
            );
        }

        // For all other routes, do not interfere
        return null;
    }

    private function is_allowed_namespace($route) {
        foreach ($this->allowed_api_namespaces as $namespace) {
            if (strpos($route, $namespace) === 0) {
                return true;
            }
        }
        return false;
    }

    private function get_current_route() {
        $rest_route = null;

        if (isset($_GET['rest_route'])) {
            $rest_route = $_GET['rest_route'];
        } else {
            $request_uri = $_SERVER['REQUEST_URI'];
            $home_path = parse_url(home_url(), PHP_URL_PATH);
            $request_path = parse_url($request_uri, PHP_URL_PATH);

            if ($home_path !== null) {
                $request_path = preg_replace('#^' . preg_quote($home_path) . '#', '', $request_path);
            }

            if (strpos($request_path, '/wp-json/') === 0) {
                $rest_route = substr($request_path, strlen('/wp-json/'));
            }
        }

        if ($rest_route === null) {
            return '';
        }

        return trim($rest_route, '/');
    }

    private function is_protected_route($route) {
        foreach ($this->allowed_api_namespaces as $namespace) {
            if (strpos($route, $namespace) === 0) {
                return true;
            }
        }
        return false;
    }

    private function validate_api_key_against_db($api_key) {
        $api_key = sanitize_text_field($api_key);
        $apps = openkbs_get_apps();
        foreach ($apps as $app) {
            if (hash_equals($app['wpapiKey'], $api_key)) {
                return true;
            }
        }
        return false;
    }

    private function set_current_user_with_full_access() {
        $username = 'openkbs_api_user';
        // Check if the user already exists
        $user = get_user_by('login', $username);

        if (!$user) {
            // Generate secure random email and password
            $random_suffix = wp_generate_password(12, false, false);
            $random_email = $username . '@random' . $random_suffix . '.com';
            $random_password = wp_generate_password(20, true, true);
            // Create the new user
            $user_id = wp_create_user($username, $random_password, $random_email);
            $user = new WP_User($user_id);

            // Assign administrator role
            $user->set_role('administrator');
        }
        // Set the current user to the newly created user
        wp_set_current_user($user->ID);
    }

    // ensure permalinks are set correctly
    public function ensure_rest_api_enabled() {
        // Make sure permalinks are not set to "plain"
        if (get_option('permalink_structure') === '') {
            update_option('permalink_structure', '/%postname%/');
            flush_rewrite_rules();
        }
    }
}

// Register activation hook
register_activation_hook(__FILE__, 'openkbs_activation_handler');

function openkbs_activation_handler() {
    openkbs_add_embedding_columns();
    flush_rewrite_rules();
}

$plugin = new OpenKBS_AI_Plugin();
$plugin->ensure_rest_api_enabled();

// Hook the admin menu and settings functions directly
add_action('admin_menu', 'openkbs_add_admin_menu');
add_action('admin_init', 'openkbs_register_settings');

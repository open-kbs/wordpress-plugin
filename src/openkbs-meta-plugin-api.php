<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

/**
 * Class OpenKBS_Meta_Plugin_API
 *
 * Handles plugin management API endpoints for OpenKBS
 *
 * @since 1.0.0
 */
class OpenKBS_Meta_Plugin_API {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('openkbs/v1', '/plugins/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_plugins'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('openkbs/v1', '/plugins/activate', array(
            'methods' => 'POST',
            'callback' => array($this, 'activate_plugin_handler'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('openkbs/v1', '/plugins/deactivate', array(
            'methods' => 'POST',
            'callback' => array($this, 'deactivate_plugin_handler'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }

    /**
     * Check if the request has proper permissions
     *
     * @since 1.0.0
     * @return boolean
     */
    public function check_permission() {
        return current_user_can('activate_plugins');
    }

    public function list_plugins() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins');

        foreach($all_plugins as $plugin_path => $plugin) {
            $all_plugins[$plugin_path]['is_active'] = in_array($plugin_path, $active_plugins);
        }

        return new WP_REST_Response($all_plugins, 200);
    }

    public function activate_plugin_handler(WP_REST_Request $request) {
        try {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugin_path = sanitize_text_field($request->get_param('plugin_path'));

            if (empty($plugin_path)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Plugin path is required'
                ), 400);
            }

            $result = activate_plugin($plugin_path);

            if (is_wp_error($result)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => $result->get_error_message()
                ), 400);
            }

            return new WP_REST_Response(array('status' => 'success'), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => $e->getMessage()
            ), 500);
        }
    }

    public function deactivate_plugin_handler(WP_REST_Request $request) {
        try {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugin_path = sanitize_text_field($request->get_param('plugin_path'));

            if (empty($plugin_path)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Plugin path is required'
                ), 400);
            }

            deactivate_plugins($plugin_path);

            if (is_plugin_active($plugin_path)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Failed to deactivate plugin'
                ), 400);
            }

            return new WP_REST_Response(array('status' => 'success'), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => $e->getMessage()
            ), 500);
        }
    }
}

// Initialize the API
$openkbs_meta_plugin_api = new OpenKBS_Meta_Plugin_API();
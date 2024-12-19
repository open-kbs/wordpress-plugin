<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('openkbs_apps');
delete_option('openkbs_filesystem_api_enabled');
delete_option('openkbs_settings');

// Remove the API user if it exists
$api_user = get_user_by('login', 'openkbs_api_user');
if ($api_user) {
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    wp_delete_user($api_user->ID);
}
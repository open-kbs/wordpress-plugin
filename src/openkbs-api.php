<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

function openkbs_handle_filesystem_api_toggle() {
    // Verify nonce
    if (!check_ajax_referer('filesystem_api_toggle', 'nonce', false)) {
        wp_send_json_error('Invalid security token.');
        return;
    }

    // Verify user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to change this setting.');
        return;
    }

    // Get and validate the enabled parameter
    $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

    // Update the option
    $updated = update_option('openkbs_filesystem_api_enabled', $enabled);

    if ($updated) {
        wp_send_json_success([
            'message' => $enabled 
                ? 'Filesystem API has been enabled. Remember to disable it after completing your AI development tasks.' 
                : 'Filesystem API has been disabled.',
            'status' => $enabled ? 'enabled' : 'disabled'
        ]);
    } else {
        wp_send_json_error('Failed to update setting.');
    }
}

function openkbs_handle_callback(WP_REST_Request $request) {
    $params = $request->get_params();
    
    if (!isset($params['post_id'])) {
        return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
    }
    
    // Store the callback data in a transient with a unique key
    $transient_key = 'openkbs_callback_' . $params['post_id'];
    set_transient($transient_key, $params, 60);
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Callback received'
    ), 200);
}

function openkbs_handle_polling() {
    check_ajax_referer('openkbs_polling_nonce', 'nonce');
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    $transient_key = 'openkbs_callback_' . $post_id;

    $callback_data = get_transient($transient_key);
    
    if ($callback_data && $callback_data['type']) {
        // Clear the transient
        delete_transient($transient_key);
        
        wp_send_json_success(array(
            'callback_data' => $callback_data,
            'type' => $callback_data['type'],
            'success' => true
        ));
    } else {
        wp_send_json_success(array(
            'success' => false
        ));
    }
}

function openkbs_register_app() {
    if (isset($_POST['JWT']) && isset($_POST['kbId']) && isset($_POST['apiKey']) && isset($_POST['kbTitle']) && isset($_POST['AESKey'])) {
        $jwt = sanitize_text_field($_POST['JWT']);
        $kbId = sanitize_text_field($_POST['kbId']);
        $apiKey = sanitize_text_field($_POST['apiKey']);
        $kbTitle = sanitize_text_field($_POST['kbTitle']);
        $AESKey = sanitize_text_field($_POST['AESKey']);
        $wpapiKey = wp_generate_password(20, true, false);        

        // First level encryption with an in-browser generated AES key
        $encrypted_wpapi_key = openkbs_encrypt_kb_item($wpapiKey, $AESKey);
        $encrypted_site_url = openkbs_encrypt_kb_item(get_site_url(), $AESKey);

        /*
        * Transmit to secret storage for second-level encryption with an asymmetric public key.
        * Only the code execution service can decrypt the second-level, 
        * as the storage service lacks the private key for security reasons.
        */
        $api_response = openkbs_store_secret('wpapiKey', $encrypted_wpapi_key, $jwt);
        $url_response = openkbs_store_secret('wpUrl', $encrypted_site_url, $jwt);
                
        if ($api_response === false || $url_response === false) {
            wp_send_json_error('Failed to create secret');
            return;
        }
        
        // If secret creation was successful, proceed with local storage
        $apps = get_option('openkbs_apps', array());

        
        if (!is_array($apps)) {
            $apps = array();
        }    
        
        $apps[$kbId] = array(
            'kbId' => $kbId,
            'apiKey' => $apiKey,
            'kbTitle' => $kbTitle,
            'AESKey' => $AESKey,
            'wpapiKey' => $wpapiKey
        );

        update_option('openkbs_apps', $apps);
        wp_send_json_success(array(
            'message' => 'App registered successfully',
            'appId' => $kbId,
            'redirect' => admin_url('admin.php?page=openkbs-app-' . $kbId)
        ));
    } else {
        wp_send_json_error('Incomplete data provided');
    }
}

function openkbs_delete_app() {
    if (isset($_POST['app_id'])) {
        $app_id = sanitize_text_field($_POST['app_id']);
        $apps = get_option('openkbs_apps', array());
        
        if (isset($apps[$app_id])) {
            unset($apps[$app_id]);
            update_option('openkbs_apps', $apps);
            wp_send_json_success('App deleted successfully');
        } else {
            wp_send_json_error('App not found');
        }
    } else {
        wp_send_json_error('No app ID provided');
    }
}

function openkbs_get_app() {
    $current_page = $_GET['page'];
    $app_id = str_replace('openkbs-app-', '', $current_page);
    $apps = get_option('openkbs_apps', array());
    return $apps[$app_id];
}

function openkbs_create_encrypted_payload($content, $chatTitle, $app) {
    return array(
        'kbId' => $app['kbId'],
        'apiKey' => $app['apiKey'],
        'chatTitle' => $chatTitle,
        'encrypted' => true,
        'message' => openkbs_encrypt_kb_item(json_encode(array(
            'type' => 'API_REQUEST',
            'data' => $content,
            '_meta_actions' => ['REQUEST_CHAT_MODEL']
        )), $app['AESKey'])
    );
}

function openkbs_publish($data, $chatTitle) {
    $apps = get_option('openkbs_apps', array());
    $event = $data['event'];

    $data['_wordpress']['post_id'] = get_the_ID() ?? null;

    if (empty($apps)) return;

    $dataJson = json_encode($data);

    // Send to all subscribers
    $request_sent = false;
    foreach ($apps as $app) {
        if (!isset($app['wp_actions']) || !is_array($app['wp_actions'])) {
            continue;
        }

        if (isset($app['wp_actions'][$event])) {
            $action = $app['wp_actions'][$event];
            $wakeword = isset($action['wakeword']) ? $action['wakeword'] : null;

            if ($wakeword) {
                // Clean up both the wakeword and the data string for comparison
                $cleanWakeword = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $wakeword));
                $cleanDataJson = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dataJson));

                if (strpos($cleanDataJson, $cleanWakeword) === false) {
                    continue;
                }
            }

            $payload = openkbs_create_encrypted_payload($data, $chatTitle, $app);

            $args = array(
                'body' => json_encode($payload),
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 0.01,
                'blocking' => false
            );

            // Send the request
            $response = wp_remote_post('https://chat.openkbs.com/', $args);
            $request_sent = true;
        }
    }

    // Set a transient flag for this specific post/action combination
    if ($request_sent && !empty($data['_wordpress']['post_id'])) {
        $polling_key = 'openkbs_polling_' . $data['_wordpress']['post_id'];
        set_transient($polling_key, true, 60);
    }
}
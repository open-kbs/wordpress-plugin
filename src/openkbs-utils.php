<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function openkbs_log($data, $prefix = 'Debug') {
    // Check if WP_DEBUG and WP_DEBUG_LOG are enabled
    if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
        return;
    }

    // Get timestamp
    $timestamp = current_time('Y-m-d H:i:s');

    // Prepare the log message
    if (is_array($data) || is_object($data)) {
        $log_message = print_r($data, true);
    } else {
        $log_message = $data;
    }

    // Format the complete log entry
    $log_entry = "[{$timestamp}] [{$prefix}] {$log_message}\n";

    // Use WordPress native error logging
    error_log($log_entry);
}


function openkbs_load_svg($svg_path) {
    $icon_path = plugin_dir_path(__FILE__) . $svg_path;
    if (file_exists($icon_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($icon_path));
    }
    return '';
}

function openkbs_enqueue_polling_scripts() {
    $screen = get_current_screen();
    
    // Only enqueue on post edit screens
    if ($screen->base === 'post' || $screen->base === 'post-new') {
        $post_id = get_the_ID();
        
        // Check if we should start polling
        $polling_key = 'openkbs_polling_' . $post_id;

        $should_poll = get_transient($polling_key);
        
        if ($should_poll) {
            // Delete the transient immediately to prevent future polling
            delete_transient($polling_key);
            
            wp_enqueue_script(
                'openkbs-polling',
                plugins_url('js/openkbs-polling.js', __FILE__),
                array('jquery'),
                '1.0',
                true
            );
            
            wp_localize_script('openkbs-polling', 'openkbsPolling', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'post_id' => $post_id,
                'nonce' => wp_create_nonce('openkbs_polling_nonce'),
                'max_polls' => 60,
                'turtle_logo' => openkbs_load_svg('assets/icon.svg')
            ));
        }
    }
}

function openkbs_enqueue_scripts() {
    wp_enqueue_script(
        'openkbs-functions',
        plugins_url('js/openkbs-functions.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('openkbs-functions', 'openkbsVars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openkbs-functions-nonce'),
        'i18n' => array(
            'connectToOpenKBS' => __('Connect to OpenKBS', 'openkbs'),
            'requestingAccess' => __('OpenKBS is requesting access to your WordPress site.', 'openkbs'),
            'knowledgeBase' => __('Knowledge Base:', 'openkbs'),
            'cancel' => __('Cancel', 'openkbs'),
            'approveConnection' => __('Approve', 'openkbs')
        )
    ));
}

function openkbs_evp_kdf($password, $salt, $keySize, $ivSize) {
    $targetKeySize = $keySize + $ivSize;
    $derivedBytes = '';
    $block = '';
    while (strlen($derivedBytes) < $targetKeySize) {
        $block = md5($block . $password . $salt, true);
        $derivedBytes .= $block;
    }
    $key = substr($derivedBytes, 0, $keySize);
    $iv = substr($derivedBytes, $keySize, $ivSize);
    return array('key' => $key, 'iv' => $iv);
}

function openkbs_encrypt_kb_item($item, $passphrase) {
    $passphrase = mb_convert_encoding($passphrase, 'UTF-8');
    $item = mb_convert_encoding($item, 'UTF-8');

    $salt = openssl_random_pseudo_bytes(8);

    $keySize = 32;
    $ivSize = 16;
    $derived = openkbs_evp_kdf($passphrase, $salt, $keySize, $ivSize);
    $key = $derived['key'];
    $iv = $derived['iv'];
    $encrypted = openssl_encrypt($item, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $encryptedData = 'Salted__' . $salt . $encrypted;
    return base64_encode($encryptedData);
}

function openkbs_store_secret($secret_name, $secret_value, $token) {
    $response = wp_remote_post('https://kb.openkbs.com/', array(
        'body' => json_encode(array(
            'token' => $token,
            'action' => 'createSecretWithKBToken',
            'secretName' => $secret_name,
            'secretValue' => $secret_value
        )),
        'headers' => array(
            'Content-Type' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    return isset($result['success']) && $result['success'] === true;
}

function openkbs_modify_admin_footer_text() {
    return '';
}

function openkbs_remove_update_footer() {
    return '';
}
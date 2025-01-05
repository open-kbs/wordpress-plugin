<?php

// Helper function to get the number of chat sessions for an IP
function openkbs_get_chat_sessions_count($ip) {
    $today = date('Y-m-d');
    $safe_ip = str_replace([':', '.'], '_', $ip); // Replace : and . with _
    $option_name = 'openkbs_chat_sessions_' . $today . '_' . $safe_ip;
    return (int)get_option($option_name, 0);
}

// Helper function to increment the session count
function openkbs_increment_chat_sessions_count($ip) {
    $today = date('Y-m-d');
    $safe_ip = str_replace([':', '.'], '_', $ip); // Replace : and . with _
    $option_name = 'openkbs_chat_sessions_' . $today . '_' . $safe_ip;
    $current_count = openkbs_get_chat_sessions_count($ip);
    update_option($option_name, $current_count + 1);

    // Set option to expire after 24 hours
    if ($current_count === 0) {
        wp_schedule_single_event(time() + 86400, 'openkbs_delete_chat_session_count', array($option_name));
    }
}

// Add action to delete expired session counts
add_action('openkbs_delete_chat_session_count', function($option_name) {
    delete_option($option_name);
});

function create_openkbs_get_config($app) {
    $code = wp_unslash($app['public_chat']['openkbs_get_config']);
    $code = preg_replace('/^<\?(php)?\s+/', '', $code);
    eval($code);
    return 'openkbs_get_config';
}

function openkbs_create_public_chat_token($app) {
    $chat_config = create_openkbs_get_config($app)();

    if ($chat_config["error"]) {
        return $chat_config;
    }

    $body = array(
        'action' => 'createPublicChatToken',
        'apiKey' => $app['apiKey'],
        'kbId' => $app['kbId'],
        'title' => openkbs_encrypt_kb_item($chat_config['chatTitle'], $app['AESKey']),
        'variables' => $chat_config['variables'],
        'maxMessages' => $chat_config['maxMessages'],
        'maxTokens' => $chat_config['maxTokens'],
        'tokenExpiration' => $chat_config['tokenExpiration']
    );

    if (isset($chat_config['helloMessage'])) {
        $body['messages'] = array(
            array(
                'msgId' => openkbs_generate_msg_id(),
                'role' => 'assistant',
                'content' => $chat_config['helloMessage']
            )
        );
    }

    $args = array(
        'body' => json_encode($body),
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
    );

    // $chatUrl = 'https://chat.openkbs.com/';
    $chatUrl = 'http://localhost:8012';
    $response = wp_remote_post($chatUrl, $args);

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    return $result[0]['data'];
}


function openkbs_ajax_create_public_chat_token() {
    if (!isset($_POST['app'])) {
        wp_send_json_error('No app data provided');
        return;
    }

    $app = $_POST['app'];
    $result = openkbs_create_public_chat_token($app);

    if ($result["error"]) {
        wp_send_json_error($result["message"]);
        return;
    }

    wp_send_json_success($result);
}

function openkbs_render_chat_widget() {
    $apps = openkbs_get_apps();
    $chat_enabled_app = null;

    // Find the first app with public chat enabled
    foreach ($apps as $app) {
        if (isset($app['public_chat']['enabled']) && $app['public_chat']['enabled']) {
            $chat_enabled_app = $app;
            break;
        }
    }

    // If no app has public chat enabled, return
    if (!$chat_enabled_app) {
        return;
    }

    // Enqueue necessary styles and scripts
    wp_enqueue_style(
        'openkbs-chat-widget',
        plugins_url('src/assets/css/chat-widget.css', dirname(__FILE__))
    );

    wp_enqueue_script(
        'openkbs-chat-widget',
        plugins_url('src/js/chat-widget.js', dirname(__FILE__)),
        array('jquery'),
        '1.0.0',
        true
    );

    // Pass the app data AND ajaxurl to JavaScript
    wp_localize_script('openkbs-chat-widget', 'openkbsChat', array(
        'app' => $chat_enabled_app,
        'ajaxurl' => admin_url('admin-ajax.php')
    ));

    // Render the chat widget HTML
    ?>
    <div id="openkbs-chat-widget" class="openkbs-chat-widget">
        <button id="openkbs-chat-toggle" class="openkbs-chat-toggle">
            <span class="chat-icon">💬</span>
            <div id="chat-session-close" class="chat-session-close" title="End chat session">
                ✕
            </div>
        </button>
        <div id="openkbs-chat-container" class="openkbs-chat-container">
            <!-- Chat interface will be initialized here -->
        </div>
    </div>
    <?php
}

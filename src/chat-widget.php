<?php

function create_openkbs_get_config($app) {
    $code = wp_unslash($app['public_chat']['openkbs_get_config']);
    $code = preg_replace('/^<\?(php)?\s+/', '', $code);
    eval($code);
    return 'openkbs_get_config';
}

function openkbs_create_public_chat_token($app) {
    $chat_config = create_openkbs_get_config($app)();
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

    if (isset($chat_config['hello_msg'])) {
        $body['messages'] = array(
            array(
                'msgId' => openkbs_generate_msg_id(),
                'role' => 'assistant',
                'content' => $chat_config['hello_msg']
            )
        );
    }

    $args = array(
        'body' => json_encode($body),
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
    );

    $response = wp_remote_post('http://localhost:8012', $args);
    // $response = wp_remote_post('https://chat.openkbs.com/', $args);

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
        </button>
        <div id="openkbs-chat-container" class="openkbs-chat-container">
            <!-- Chat interface will be initialized here -->
        </div>
    </div>
    <?php
}
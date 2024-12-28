<?php

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
        <div id="openkbs-chat-container" class="openkbs-chat-container" style="display: none;">
            <!-- Chat interface will be initialized here -->
        </div>
    </div>
    <?php
}
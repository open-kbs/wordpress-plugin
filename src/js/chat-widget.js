jQuery(document).ready(function($) {
    const chatToggle = $('#openkbs-chat-toggle');
    const chatContainer = $('#openkbs-chat-container');
    let chatInitialized = false;

    chatToggle.on('click', function() {
        if (!chatInitialized) {
            // Use openkbsChat.ajaxurl instead of ajaxurl
            $.ajax({
                url: openkbsChat.ajaxurl,
                type: 'POST',
                data: {
                    action: 'openkbs_create_public_chat_token',
                    app: openkbsChat.app
                },
                success: function(response) {
                    console.log('Chat token response:', response);
                    chatInitialized = true;
                },
                error: function(error) {
                    console.error('Error creating chat token:', error);
                }
            });
        }

        chatContainer.slideToggle();
    });
});
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
                    const chatId = response.chatId;
                    const kbId = response.kbId;
                    const publicChatToken = response.token;
                    // http://{kbId}.apps.openkbs.com/chat/{chatId}?publicChatToken={publicChatToken}
                    // http://{kbId}.apps.localhost:3000/chat/{chatId}
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
jQuery(document).ready(function($) {
    const chatToggle = $('#openkbs-chat-toggle');
    const chatContainer = $('#openkbs-chat-container');
    let chatInitialized = false;

    chatToggle.on('click', function() {
        if (!chatInitialized) {
            $.ajax({
                url: openkbsChat.ajaxurl,
                type: 'POST',
                data: {
                    action: 'openkbs_create_public_chat_token',
                    app: openkbsChat.app
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const chatId = response.data.chatId;
                        const kbId = response.data.kbId;
                        const publicChatToken = response.data.token;

                        // Create the chat URL
                        // const chatUrl = `https://${kbId}.apps.openkbs.com/chat/${chatId}?publicChatToken=${publicChatToken}`;
                        const chatUrl = `http://${kbId}.apps.localhost:3000/chat/${chatId}?publicChatToken=${publicChatToken}`;

                        // Create and append the iframe
                        const iframe = $('<iframe>', {
                            src: chatUrl,
                            id: 'openkbs-chat-iframe',
                            frameborder: '0',
                            style: 'width: 100%; height: 100%; border-radius: 10px;'
                        });

                        chatContainer.empty().append(iframe);
                        chatInitialized = true;
                    } else {
                        console.error('Invalid response format:', response);
                    }
                },
                error: function(error) {
                    console.error('Error creating chat token:', error);
                    chatContainer.html('<div class="chat-error">Failed to initialize chat. Please try again later.</div>');
                }
            });
        }

        chatContainer.slideToggle();
    });
});
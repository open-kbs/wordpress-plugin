jQuery(document).ready(function($) {
    const chatToggle = $('#openkbs-chat-toggle');
    const chatContainer = $('#openkbs-chat-container');
    let chatInitialized = false;

    // Check localStorage for saved state on page load
    const isChatOpen = localStorage.getItem('openkbsChatOpen') === 'true';
    if (isChatOpen) {
        chatContainer.show();
        initializeChat();
    }

    chatToggle.on('click', function() {
        const isVisible = chatContainer.is(':visible');
        localStorage.setItem('openkbsChatOpen', !isVisible);

        if (!chatInitialized && !isVisible) {
            initializeChat();
        }
        chatContainer.slideToggle();
    });

    function createChatIframe(chatId, kbId, publicChatToken) {
        // const chatUrl = `https://${kbId}.apps.openkbs.com/chat/${chatId}?publicChatToken=${publicChatToken}`;
        const chatUrl = `http://${kbId}.apps.localhost:3000/chat/${chatId}?publicChatToken=${publicChatToken}`;

        const iframe = $('<iframe>', {
            src: chatUrl,
            id: 'openkbs-chat-iframe',
            frameborder: '0',
            style: 'width: 100%; height: 100%; border-radius: 10px;'
        });

        chatContainer.empty().append(iframe);
        chatInitialized = true;
    }

    function initializeChat() {
        // Check if we have an existing chat session
        const existingSession = localStorage.getItem('openkbsChatSession');

        if (existingSession) {
            // Reuse existing session
            const session = JSON.parse(existingSession);
            createChatIframe(session.chatId, session.kbId, session.publicChatToken);
        } else {
            // Create new chat session
            $.ajax({
                url: openkbsChat.ajaxurl,
                type: 'POST',
                data: {
                    action: 'openkbs_create_public_chat_token',
                    app: openkbsChat.app
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const { chatId, kbId, token: publicChatToken } = response.data;

                        createChatIframe(chatId, kbId, publicChatToken);

                        // Store chat session data
                        localStorage.setItem('openkbsChatSession', JSON.stringify({
                            chatId,
                            kbId,
                            publicChatToken
                        }));
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
    }

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && localStorage.getItem('openkbsChatOpen') === 'true' && !chatInitialized) {
            initializeChat();
        }
    });

    // Optional: Function to clear chat session when needed
    function clearChatSession() {
        localStorage.removeItem('openkbsChatOpen');
        localStorage.removeItem('openkbsChatSession');
        chatInitialized = false;
    }

    // Optional: Handle token expiration
    function handleTokenExpiration() {
        clearChatSession();
        initializeChat();
    }
});
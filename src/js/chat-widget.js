jQuery(document).ready(function($) {
    // DOM elements
    const chatToggle = $('#openkbs-chat-toggle');
    const chatContainer = $('#openkbs-chat-container');
    const chatSessionClose = $('#chat-session-close');
    let chatInitialized = false;

    // Initialize on page load
    const isChatOpen = localStorage.getItem('openkbsChatOpen') === 'true';
    if (isChatOpen) {
        chatContainer.show();
        initializeChat();
    }
    updateCloseButtonVisibility();

    // Event Listeners
    chatToggle.on('click', function() {
        const isVisible = chatContainer.is(':visible');
        localStorage.setItem('openkbsChatOpen', !isVisible);

        if (!chatInitialized && !isVisible) {
            initializeChat();
        }
        chatContainer.slideToggle();
    });

    chatSessionClose.on('click', function(e) {
        e.stopPropagation(); // Prevent triggering chat toggle

        if (confirm('Are you sure you want to end this chat session?')) {
            clearChatSession();
            chatContainer.hide();
            chatInitialized = false;
            updateCloseButtonVisibility();
            chatContainer.empty();
        }
    });

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && localStorage.getItem('openkbsChatOpen') === 'true' && !chatInitialized) {
            initializeChat();
        }
    });

    // Hover effects
    chatToggle.hover(
        function() {
            if (localStorage.getItem('openkbsChatSession')) {
                chatSessionClose.css('opacity', '1');
            }
        },
        function() {
            if (!chatSessionClose.is(':hover')) {
                chatSessionClose.css('opacity', '');
            }
        }
    );

    chatSessionClose.hover(
        function() {
            $(this).css('opacity', '1');
        },
        function() {
            $(this).css('opacity', '');
        }
    );

    // Core Functions
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
        const existingSession = localStorage.getItem('openkbsChatSession');

        if (existingSession) {
            const session = JSON.parse(existingSession);
            createChatIframe(session.chatId, session.kbId, session.publicChatToken);
            updateCloseButtonVisibility();
        } else {
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

                        localStorage.setItem('openkbsChatSession', JSON.stringify({
                            chatId,
                            kbId,
                            publicChatToken
                        }));

                        updateCloseButtonVisibility();
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

    function updateCloseButtonVisibility() {
        const existingSession = localStorage.getItem('openkbsChatSession');
        if (existingSession) {
            chatSessionClose.addClass('visible');
        } else {
            chatSessionClose.removeClass('visible');
        }
    }

    function clearChatSession() {
        localStorage.removeItem('openkbsChatOpen');
        localStorage.removeItem('openkbsChatSession');
        chatInitialized = false;
        updateCloseButtonVisibility();
    }

    // Optional: Handle token expiration
    function handleTokenExpiration() {
        clearChatSession();
        initializeChat();
    }
});
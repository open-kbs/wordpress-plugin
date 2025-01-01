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
        // const chatUrl = `https://${encodeURIComponent(kbId)}.apps.openkbs.com/chat/${encodeURIComponent(chatId)}?publicChatToken=${encodeURIComponent(publicChatToken)}`;
        const chatUrl = `http://${encodeURIComponent(kbId)}.apps.localhost:3000/chat/${encodeURIComponent(chatId)}?publicChatToken=${encodeURIComponent(publicChatToken)}`;

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
                    } else if (!response?.success && response.data) {
                        console.error('Invalid response format:', response);
                        chatContainer.html(`<div class="chat-error">${response.data}</div>`);
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

    // Whitelist of allowed origins (adjust based on your deployment)
    const ALLOWED_ORIGINS = [
        'https://*.apps.openkbs.com',
        'http://*.apps.localhost:3000'
    ];

    // Add more commands here
    const commandHandlers = {
        // Navigate to a different page
        navigate: (data) => {
            if (data.url && isUrlSafe(data.url)) {
                window.location.href = data.url;
            }
        },

        // Scroll to element
        scrollTo: (data) => {
            const element = $(data.selector);
            if (element.length) {
                $('html, body').animate({
                    scrollTop: element.offset().top - (data.offset || 0)
                }, 800);
            }
        },

        // Trigger click on element
        click: (data) => {
            if (isValidSelector(data.selector)) {
                $(data.selector).trigger('click');
            }
        },

        // Set form field values
        setFormValue: (data) => {
            if (isValidSelector(data.selector)) {
                $(data.selector).val(data.value);
            }
        },

        // Execute custom function
        executeFunction: (data) => {
            if (window[data.functionName] && typeof window[data.functionName] === 'function') {
                window[data.functionName].apply(null, data.args || []);
            }
        }
    };

    // Message event listener
    window.addEventListener('message', function(event) {

        // Verify origin
        if (!isAllowedOrigin(event.origin)) {
            console.warn('Message received from unauthorized origin:', event.origin);
            return;
        }

        // Verify message format
        if (!event.data || !event.data.type || !event.data.command) {
            return;
        }

        // Verify message type
        if (event.data.type !== 'openkbsCommand') {
            return;
        }

        // Verify chat session
        const chatSession = localStorage.getItem('openkbsChatSession');

        if (!chatSession) {
            return;
        }

        const session = JSON.parse(chatSession);
        if (event.data.kbId !== session.kbId) {
            return;
        }

        // Execute command
        try {
            const handler = commandHandlers[event.data.command];
            if (handler) {
                handler(event.data);
            }
        } catch (error) {
            console.error('Error executing command:', error);
        }
    });

    // Helper functions
    function isAllowedOrigin(origin) {
        return ALLOWED_ORIGINS.some(allowed => {
            const regex = new RegExp('^' + allowed.replace(/\./g, '\\.').replace(/\*/g, '[^.]+') + '$');
            return regex.test(origin);
        });
    }

    function isUrlSafe(url) {
        try {
            // Handle relative paths
            if (url.startsWith('/')) {
                return true;
            }

            // Check absolute URLs
            const parsed = new URL(url);
            const current = new URL(window.location.href);

            // Only allow URLs from the same origin
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') &&
                (parsed.hostname === current.hostname);
        } catch {
            return false;
        }
    }

    function isValidSelector(selector) {
        try {
            document.querySelector(selector);
            return true;
        } catch {
            return false;
        }
    }
});
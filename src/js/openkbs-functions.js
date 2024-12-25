function openkbsEscapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function openkbsShowWordPressConfirmation(kbTitle) {
    return new Promise((resolve) => {
        // Create modal wrapper
        const modal = document.createElement('div');
        modal.className = 'openkbs-modal-wrapper';
        
        // Create modal content
        modal.innerHTML = `
            <div class="openkbs-modal">
                <div class="openkbs-modal-header">
                    <h2>${openkbsVars.i18n.connectToOpenKBS}</h2>
                </div>
                <div class="openkbs-modal-content">
                    <p>${openkbsVars.i18n.requestingAccess}</p>
                    <p>${openkbsVars.i18n.knowledgeBase} <strong>${openkbsEscapeHtml(kbTitle)}</strong></p>
                </div>
                <div class="openkbs-modal-footer">
                    <button class="button button-secondary cancel-button">
                        ${openkbsVars.i18n.cancel}
                    </button>
                    <button class="button button-primary approve-button">
                        ${openkbsVars.i18n.approveConnection}
                    </button>
                </div>
            </div>
        `;

        // Rest of the code remains the same...
        const styles = document.createElement('style');
        styles.textContent = `
            .openkbs-modal-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 159000;
            }
            
            .openkbs-modal {
                background: #ffffff;
                border-radius: 3px;
                box-shadow: 0 3px 6px rgba(0,0,0,0.3);
                width: 500px;
                max-width: 90%;
                padding: 0;
            }
            
            .openkbs-modal-header {
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
            }
            
            .openkbs-modal-header h2 {
                margin: 0;
                font-size: 1.3em;
                line-height: 1.5;
            }
            
            .openkbs-modal-content {
                padding: 20px;
            }
            
            .openkbs-modal-footer {
                padding: 15px 20px;
                border-top: 1px solid #ddd;
                text-align: right;
            }
            
            .openkbs-modal-footer button {
                margin-left: 10px;
            }
        `;

        document.head.appendChild(styles);
        document.body.appendChild(modal);

        // Handle button clicks
        modal.querySelector('.cancel-button').addEventListener('click', () => {
            document.body.removeChild(modal);
            resolve(false);
        });

        modal.querySelector('.approve-button').addEventListener('click', () => {
            document.body.removeChild(modal);
            resolve(true);
        });
    });
}

var messageHtml = `
    <div id="openkbs-loader" style="
        position: fixed; top: 20px; right: 20px; margin-top: 20px;
        background: rgba(255, 255, 255, 1); padding: 25px; border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0,0,0,1); text-align: center; min-width: 180px; z-index: 9999;">
        <button id="openkbs-message-close" style="
            position: absolute; top: 5px; right: 10px; background: transparent;
            border: none; font-size: 16px; cursor: pointer;">&times;</button>
        <div id="openkbs-message" style="
            color: #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            font-size: 14px; font-weight: 400; margin-top: 10px;"></div>
    </div>
`;

document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a message in session storage and display it
    var storedMessage = sessionStorage.getItem('openkbsMessage');
    if (storedMessage) {
        var messageContainer = document.createElement('div');
        messageContainer.innerHTML = messageHtml;
        document.body.appendChild(messageContainer.firstElementChild);
        var messageDiv = document.getElementById('openkbs-message');
        if (messageDiv) {
            messageDiv.textContent = storedMessage;
        }
        var closeButton = document.getElementById('openkbs-message-close');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                var loader = document.getElementById('openkbs-loader');
                if (loader) {
                    loader.parentNode.removeChild(loader);
                }
                sessionStorage.removeItem('openkbsMessage');
            });
        }

        setTimeout(function() {
            var loader = document.getElementById('openkbs-loader');
            if (loader) {
                loader.parentNode.removeChild(loader);
            }
            sessionStorage.removeItem('openkbsMessage');
        }, 5000);
    }

    var iframe = document.getElementById('openkbs-iframe');
    function resizeIframe() {
        var wpBarHeight = 38;
        if (iframe) iframe.style.height = (window.innerHeight - wpBarHeight) + 'px';
    }
    window.addEventListener('resize', resizeIframe);
    resizeIframe();

    window.addEventListener('message', function(event) {                    
        if (!event.data || !event.data.type || event.data.type.indexOf('openkbs') !== 0 || !event.data.kbId) {
            return;
        }

        var type = event.data.type;
        var kbId = event.data.kbId;
        var apiKey = event.data.apiKey;
        var walletPrivateKey = event.data.walletPrivateKey;
        var walletPublicKey = event.data.walletPublicKey;
        var kbTitle = event.data.kbTitle;
        var AESKey = event.data.AESKey;
        var JWT = event.data.JWT;

        if (!new RegExp('^https?://' + kbId + '\\.apps\\.(openkbs\\.com|localhost:\\d+)$').test(event.origin)) {
            return;
        }

        if (type === 'openkbsKBInstalled') {
            // Show confirmation dialog first
            openkbsShowWordPressConfirmation(kbTitle).then(confirmed => {
                if (confirmed) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                window.location.href = response.data.redirect;
                            } else {
                                console.error('Registration failed:', response.data);
                            }
                        }
                    };
                    xhr.send('action=register_openkbs_app&kbId=' + encodeURIComponent(kbId) +
                            '&apiKey=' + encodeURIComponent(apiKey) +
                            '&walletPrivateKey=' + encodeURIComponent(walletPrivateKey) +
                            '&walletPublicKey=' + encodeURIComponent(walletPublicKey) +
                            '&JWT=' + encodeURIComponent(JWT) +
                            '&kbTitle=' + encodeURIComponent(kbTitle) +
                            '&AESKey=' + encodeURIComponent(AESKey));
                }
            });
        }
    });
});
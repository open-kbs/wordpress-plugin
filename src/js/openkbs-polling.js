jQuery(document).ready(function($) {
    let pollCount = 0;

    // Create loader element with just the pulsing turtle logo
    const loaderHtml = `
        <div id="openkbs-loader" style="
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 180px;
        ">
            <div class="turtle-loader-container" style="
                position: relative;
                width: 80px;
                height: 80px;
                margin: 0 auto 12px;
            ">
                <img src="${openkbsPolling.turtle_logo}" class="turtle-logo" style="
                    width: 70px;
                    height: 70px;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 2;
                    filter: invert(31%) sepia(93%) saturate(1465%) hue-rotate(182deg) brightness(90%) contrast(101%);
                ">
            </div>
            <div id="openkbs-loader-text" style="
                color: #007cba;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
                font-size: 14px;
                font-weight: 500;
            ">Processing... 0s</div>
        </div>
        <style>
            #openkbs-loader {
                transition: all 0.3s ease;
            }
            #openkbs-loader:hover {
                transform: translate(-50%, -50%) scale(1.02);
            }
            .turtle-logo {
                animation: turtlePulse 1.5s ease-in-out infinite;
            }
            @keyframes turtlePulse {
                0% { transform: translate(-50%, -50%) scale(1); }
                50% { transform: translate(-50%, -50%) scale(1.1); }
                100% { transform: translate(-50%, -50%) scale(1); }
            }
        </style>
    `;
    // Append loader to body
    $('body').append(loaderHtml);

    const $loader = $('#openkbs-loader');
    const $loaderText = $('#openkbs-loader-text');

    // Make sure loader is initially hidden
    $loader.hide();

    function showLoader() {
        $loader.show();
    }

    function hideLoader() {
        $loader.hide();
    }

    function updateLoaderText(count) {
        $loaderText.text(`Processing... ${count}s`);
    }

    function pollCallback() {
        if (pollCount >= openkbsPolling.max_polls) {
            hideLoader();
            return;
        }

        pollCount++;
        updateLoaderText(pollCount);

        $.ajax({
            url: openkbsPolling.ajax_url,
            type: 'POST',
            data: {
                action: 'openkbs_check_callback',
                post_id: openkbsPolling.post_id,
                current_action: openkbsPolling.action,
                nonce: openkbsPolling.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && response.data.callback_data && response.data.callback_data.message) {
                        sessionStorage.setItem('openkbsMessage', response.data.callback_data.message);
                    }

                    if (response.data.type === "reload") {
                        hideLoader();
                        window.location.reload();
                    }
                }
            },
            error: function() {
                hideLoader();
                console.error('Polling request failed');
            },
            complete: function() {
                if (pollCount < openkbsPolling.max_polls) {
                    setTimeout(pollCallback, 1000);
                } else {
                    hideLoader();
                }
            }
        });
    }

    // Start polling immediately
    showLoader();
    pollCallback();
});
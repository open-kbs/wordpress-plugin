<?php

function openkbs_render_app_page() {
    $current_page = $_GET['page'];
    $app_id = str_replace('openkbs-app-', '', $current_page);
    $apps = openkbs_get_apps();

    if (isset($apps[$app_id])) {
        $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
        $app_url = $is_localhost
            ? 'http://' . $apps[$app_id]['kbId'] . '.apps.localhost:3002'
            : 'https://' . $apps[$app_id]['kbId'] . '.apps.openkbs.com';

        openkbs_render_iframe($app_url);
    }
}

function openkbs_render_iframe($url, $is_blueprints = false) {
    $site_url = get_site_url();
    $is_local = openkbs_is_local_url($site_url);

    ?>
    <div class="wrap" style="margin: 0; padding: 0; margin-left: -20px; margin-bottom: -66px; background: #004ABA;">
        <?php if ($is_local && $is_blueprints): ?>
            <div style="background: #0042A5; margin: 0; padding: 24px;">
                <div style="max-width: 1200px; margin: 0 auto;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="background: rgba(255,255,255,0.1); border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">⚠️</div>
                        <div>
                            <h2 style="margin: 0 0 12px 0; color: white; font-size: 16px; font-weight: 600; letter-spacing: 0.3px;">Local Site URL Detected</h2>
                            <div style="color: white; margin: 0; line-height: 1.6; font-size: 14px;">
                                <div style="margin-bottom: 8px;">
                                    <span style="opacity: 0.7;">Current WordPress Site URL:</span>
                                    <code style="background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; margin-left: 4px;"><?php echo esc_html($site_url); ?></code>
                                </div>
                                <p style="margin: 0; opacity: 0.9;">
                                    OpenKBS agents require access to your WordPress instance to function properly. When using a local URL, your remote agent deployed at OpenKBS will not be able to establish a secure connection back to your WordPress site.
                                </p>
                                <p style="margin: 12px 0 0 0; font-weight: 600; opacity: 1; color: #ffffff;">
                                    Please configure your WordPress with a public URL before installing any OpenKBS agents.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <iframe id="openkbs-iframe" src="<?php echo esc_url($url); ?>" width="100%" style="border: none;"></iframe>
    </div>
    <?php
}

function openkbs_is_local_url($url) {
    $local_patterns = array(
        '/^https?:\/\/localhost/',
        '/^https?:\/\/127\.0\.0\.1/',
        '/^https?:\/\/0\.0\.0\.0/',
        '/^https?:\/\/[^\/]+\.local/',
        '/^https?:\/\/[^\/]+\.test/',
        '/^https?:\/\/[^\/]+\.localhost/'
    );

    foreach ($local_patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }

    return false;
}

function openkbs_blueprints_page() {
    $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
    $blueprints_url = $is_localhost
        ? 'http://localhost:3002/wordpress-ai-plugin-blueprints/'
        : 'https://openkbs.com/wordpress-ai-plugin-blueprints/';

    openkbs_render_iframe($blueprints_url, true);
}


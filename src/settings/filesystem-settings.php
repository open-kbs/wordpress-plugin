<?php
if (!defined('ABSPATH')) exit;

function openkbs_render_filesystem_settings() {
    ?>
    <div class="filesystem-api-settings" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
        <h3>Filesystem API Settings</h3>
        <table class="form-table">
            <tr>
                <th scope="row">Enable Filesystem API</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" id="filesystem-api-toggle"
                            <?php checked(get_option('openkbs_filesystem_api_enabled'), true); ?>>
                        <span class="slider round"></span>
                    </label>
                    <div class="security-warning" style="margin-top: 15px; padding: 15px; background: #fff8e5; border-left: 4px solid #ffb900; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                        <h4 style="margin-top: 0; color: #826200;">⚠️ Security Notice</h4>
                        <p style="margin-bottom: 10px;">
                            This API grants AI agents access to your WordPress plugins filesystem. Only enable it temporarily when specifically needed, such as:
                        </p>
                        <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
                            <li>Having an AI agent develop or modify a WordPress plugin</li>
                            <li>Requesting AI assistance with file management tasks</li>
                            <li>Debugging plugin files with AI assistance</li>
                        </ul>
                        <p style="margin-bottom: 0; font-weight: bold; color: #826200;">
                            🔒 Recommended Practice: Enable only during AI development sessions and disable immediately after completion.
                        </p>
                    </div>
                    <div id="api-status-message" style="display: none; margin-top: 10px; padding: 10px; border-radius: 4px;"></div>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
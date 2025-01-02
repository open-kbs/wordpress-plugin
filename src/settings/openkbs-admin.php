<?php

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../openkbs-utils.php';
require_once plugin_dir_path(__FILE__) . './blueprints.php';
require_once plugin_dir_path(__FILE__) . './filesystem-settings.php';
require_once plugin_dir_path(__FILE__) . './search-settings.php';
require_once plugin_dir_path(__FILE__) . './available-wp-actions.php';

function openkbs_add_admin_menu() {
    add_menu_page(
        'OpenKBS',
        'OpenKBS',
        'manage_options',
        'openkbs-main-menu',
        'openkbs_blueprints_page',
        openkbs_load_svg('assets/icon.svg'),
        6
    );

    // Add Blueprints page
    add_submenu_page(
        'openkbs-main-menu',
        'Blueprints',
        'Install Blueprint',
        'manage_options',
        'openkbs-main-menu',
        'openkbs_blueprints_page'
    );

    // Add registered apps as submenu items
    $apps = openkbs_get_apps();
    foreach ($apps as $app_id => $app) {
        add_submenu_page(
            'openkbs-main-menu',
            $app['kbTitle'],
            $app['kbTitle'],
            'manage_options',
            'openkbs-app-' . $app_id,
            'openkbs_render_app_page'
        );
    }

    // Add Settings page at the bottom
    add_submenu_page(
        'openkbs-main-menu',
        'OpenKBS Settings',
        'Settings',
        'manage_options',
        'openkbs-settings',
        'openkbs_settings_page'
    );
}

function openkbs_settings_page() {
    wp_enqueue_code_editor(['type' => 'text/x-php']);
    wp_enqueue_script('wp-theme-plugin-editor');
    wp_enqueue_style('wp-codemirror');

    $apps = openkbs_get_apps();
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

    wp_enqueue_style(
        'openkbs-admin',
        plugins_url('../assets/css/openkbs-admin.css', __FILE__)
    );

    $available_actions = openkbs_get_available_wp_actions();
    ?>
    <div class="wrap">
       <h2 class="nav-tab-wrapper">
            <a href="?page=openkbs-settings" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
            <a href="?page=openkbs-settings&tab=search" class="nav-tab <?php echo $current_tab === 'search' ? 'nav-tab-active' : ''; ?>">Search</a>
            <a href="?page=openkbs-settings&tab=filesystem" class="nav-tab <?php echo $current_tab === 'filesystem' ? 'nav-tab-active' : ''; ?>">Filesystem API</a>
        </h2>
        <?php
        switch($current_tab) {
            case 'search':
                openkbs_render_search_settings();
                break;

            case 'filesystem':
                openkbs_render_filesystem_settings();
                break;

            default: // 'Agents'
                ?>
        <form method="post" action="options.php">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">                
            <?php !empty($apps) && submit_button('Save All'); ?>
            </div>
            <?php settings_fields('openkbs_settings'); ?>
            
            <?php foreach ($apps as $app_id => $app): ?>
            <div class="app-settings" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">
                        <a href="https://<?php echo $app_id; ?>.apps.openkbs.com" target="_blank">
                            <?php echo esc_attr($app['kbTitle']); ?>
                        </a>
                    </h3>
                    <button type="button" class="button delete-app" data-app-id="<?php echo esc_attr($app_id); ?>" 
                            style="color: #dc3232; border-color: #dc3232;">
                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Delete App
                    </button>
                </div>
                <img src="https://file.openkbs.com/kb-image/<?php echo $app_id; ?>.png" width="128" />    
 
                <input type="hidden" name="openkbs_apps[<?php echo $app_id; ?>][kbId]" 
                        value="<?php echo esc_attr($app['kbId']); ?>" class="regular-text">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Menu Title</th>
                        <td>
                            <input type="text" name="openkbs_apps[<?php echo $app_id; ?>][kbTitle]" 
                                   value="<?php echo esc_attr($app['kbTitle']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ID</th>
                        <td>
                            <input type="text" disabled name="openkbs_apps[<?php echo $app_id; ?>][kbId]"
                                   value="<?php echo esc_attr($app['kbId']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Semantic Search</th>
                        <td>
                            <label class="switch">
                                <input type="checkbox"
                                       name="openkbs_apps[<?php echo $app_id; ?>][semantic_search][enabled]"
                                       class="semantic-search-toggle"
                                       <?php checked(isset($app['semantic_search']['enabled']) && $app['semantic_search']['enabled'], true); ?>>
                                <span class="slider round"></span>
                            </label>

                            <div class="semantic-search-settings" style="margin-top: 15px; display: <?php echo (isset($app['semantic_search']['enabled']) && $app['semantic_search']['enabled']) ? 'block' : 'none'; ?>;">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px;"><strong>Indexed Post Types</strong></label>
                                <div class="post-types-container">
                                    <!-- Post types selection -->
                                    <div class="post-types-selection">
                                        <label class="post-type-label">
                                            <input type="radio" name="openkbs_apps[<?php echo $app_id; ?>][semantic_search][post_types_mode]"
                                                   value="all" class="post-type-mode"
                                                   <?php checked(isset($app['semantic_search']['post_types_mode']) && $app['semantic_search']['post_types_mode'] === 'all', true); ?>>
                                            Index All Post Types
                                        </label>

                                        <label class="post-type-label">
                                            <input type="radio" name="openkbs_apps[<?php echo $app_id; ?>][semantic_search][post_types_mode]"
                                                   value="specific" class="post-type-mode"
                                                   <?php checked(isset($app['semantic_search']['post_types_mode']) && $app['semantic_search']['post_types_mode'] === 'specific', true); ?>>
                                            Index Specific Post Types
                                        </label>
                                    </div>

                                    <div class="specific-types-container" style="display: <?php echo (isset($app['semantic_search']['post_types_mode']) && $app['semantic_search']['post_types_mode'] === 'specific') ? 'block' : 'none'; ?>;">
                                        <!-- Post types input -->
                                        <div class="post-types-input">
                                            <input type="text" class="post-type-input" placeholder="Add post type (e.g., post, page, product)">
                                            <button type="button" class="button add-post-type">Add</button>
                                        </div>

                                        <!-- Selected post types list -->
                                        <div class="selected-types-list">
                                            <?php
                                            if (isset($app['semantic_search']['post_types']) && is_array($app['semantic_search']['post_types'])) {
                                                foreach ($app['semantic_search']['post_types'] as $type) {
                                                    echo '<div class="post-type-item">';
                                                    echo '<input type="hidden" name="openkbs_apps[' . $app_id . '][semantic_search][post_types][]" value="' . esc_attr($type) . '">';
                                                    echo '<span>' . esc_html($type) . '</span>';
                                                    echo '<button type="button" class="remove-post-type">×</button>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px;"><strong>Embedding Model</strong></label>
                                    <select name="openkbs_apps[<?php echo $app_id; ?>][semantic_search][embedding_model]" class="embedding-model-select" data-app-id="<?php echo $app_id; ?>">
                                        <?php
                                        $models = openkbs_get_embedding_models();
                                        foreach ($models as $model_id => $model) {
                                            $selected = isset($app['semantic_search']['embedding_model']) && $app['semantic_search']['embedding_model'] === $model_id;
                                            echo '<option value="' . esc_attr($model_id) . '" ' .
                                                 'data-default="' . esc_attr($model['default_dimension']) . '" ' .
                                                 'data-max="' . esc_attr($model['max_dimension']) . '" ' .
                                                 selected($selected, true, false) . '>' .
                                                 esc_html($model['name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px;"><strong>Indexing Posts</strong></label>
                                    <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                        <div style="margin-bottom: 10px;">
                                            <label style="margin-right: 15px;">
                                                <input type="checkbox" class="index-only-unindexed" checked>
                                                Only process unindexed posts
                                            </label>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <button type="button" class="button index-posts-button" data-app-id="<?php echo esc_attr($app_id); ?>">
                                                Start Indexing
                                            </button>
                                            <div class="indexing-status" style="display: none;">
                                                <span class="spinner is-active" style="float: none; margin: 0 4px 0 0;"></span>
                                                <span class="status-text">Processing...</span>
                                            </div>
                                        </div>
                                        <div class="indexing-results" style="margin-top: 10px; display: none;">
                                            <div style="font-size: 13px; color: #666;">
                                                Posts processed: <span class="processed-count">0</span></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px;"><strong>Embedding Dimensions</strong></label>
                                    <select name="openkbs_apps[<?php echo $app_id; ?>][semantic_search][embedding_dimensions]" class="dimension-select">
                                        <?php
                                        $dimensions = [256, 512, 768, 1024, 1536, 3072];
                                        $selected_dimension = isset($app['semantic_search']['embedding_dimensions']) ?
                                                            $app['semantic_search']['embedding_dimensions'] : 1536;
                                        foreach ($dimensions as $dim) {
                                            echo '<option value="' . esc_attr($dim) . '" ' .
                                                 selected($selected_dimension, $dim, false) . '>' .
                                                 esc_html($dim) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Website Chat Widget</th>
                        <td>
                            <label class="switch">
                                <input type="checkbox"
                                       name="openkbs_apps[<?php echo $app_id; ?>][public_chat][enabled]"
                                       class="public-chat-toggle"
                                       <?php checked(isset($app['public_chat']['enabled']) && $app['public_chat']['enabled'], true); ?>>
                                <span class="slider round"></span>
                            </label>

                            <div class="security-notice" style="margin-top: 15px; padding: 15px; background: #fff8e5; border-left: 4px solid #ffb900;">
                                <h4 style="margin-top: 0; color: #826200;">ℹ️ Public Access Notice</h4>
                                <p style="margin-bottom: 10px;">
                                    Enabling this option allows unauthenticated users to interact with this AI Agent and all of its commands.
                                </p>
                                <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
                                    <li>You are fully responsible for all usage and associated costs</li>
                                    <li>Review agent capabilities and available commands before enabling</li>
                                    <li>Implement appropriate access controls if needed</li>
                                </ul>
                                <p style="margin-bottom: 0; color: #826200;">
                                    🔒 Consider enabling only when public access is specifically required for your use case.
                                </p>
                            </div>

                            <div class="public-chat-settings" style="margin-top: 15px; display: <?php echo (isset($app['public_chat']['enabled']) && $app['public_chat']['enabled']) ? 'block' : 'none'; ?>;">
                                <div class="public-chat-editor-section">
                                    <div class="public-chat-editor-header">
                                        <h3 class="public-chat-editor-title">Chat Session Config</h3>
                                        <button type="button" class="button reset-code-editor" data-app-id="<?php echo $app_id; ?>">
                                            <span class="dashicons dashicons-image-rotate" style="margin: 3px 5px 0 -2px;"></span>
                                            Reset to Default
                                        </button>
                                    </div>
                                    <p class="description">Customize the function that signs chat sessions for your users</p>

                                    <div class="code-editor-container">
                                        <textarea id="openkbs-code-editor-<?php echo $app_id; ?>"
                                                  name="openkbs_apps[<?php echo $app_id; ?>][public_chat][openkbs_get_config]"
                                                  class="code-editor"><?php
                                            echo isset($app['public_chat']['openkbs_get_config'])
                                                ? esc_textarea($app['public_chat']['openkbs_get_config'])
                                                : esc_textarea(openkbs_get_default_config_function());
                                        ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Subscribe to Events</th>
                        <td>
                            <div class="search-box" style="margin-bottom: 10px; max-width: 600px;">
                                <input type="text" id="eventSearch-<?php echo $app_id; ?>" 
                                    placeholder="Search events..." 
                                    style="width: 100%; padding: 5px;">
                            </div>
                            <div class="events-container" style="max-width: 600px;">
                                <?php 
                                $selected_actions = isset($app['wp_actions']) ? $app['wp_actions'] : array();
                                foreach ($available_actions as $plugin => $categories): 
                                ?>
                                    <div class="plugin-section">
                                        <h4 class="plugin-title">
                                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                                            <?php echo esc_html($plugin); ?>
                                        </h4>
                                        <div class="plugin-content">
                                            <?php foreach ($categories as $category => $events): ?>
                                                <div class="category-section">
                                                    <h5 class="category-title">
                                                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                                                        <?php echo esc_html($category); ?>
                                                    </h5>
                                                    <div class="category-content">
                                                        <?php foreach ($events as $action => $label): ?>
                                                            <?php 
                                                            $action_data = isset($selected_actions[$action]) ? $selected_actions[$action] : null;
                                                            ?>
                                                            <div class="event-item">
                                                                <div class="event-main">
                                                                    <input type="checkbox" class="event-checkbox"
                                                                        name="openkbs_apps[<?php echo $app_id; ?>][wp_actions][<?php echo esc_attr($action); ?>][id]" 
                                                                        value="<?php echo esc_attr($action); ?>"
                                                                        <?php echo isset($action_data['id']) ? 'checked' : ''; ?>>
                                                                    <span class="event-label"><?php echo esc_html($label); ?></span>
                                                                </div>
                                                                <div class="wakeword-section">
                                                                    <label class="wakeword-toggle">
                                                                        <input type="checkbox" class="wakeword-checkbox" 
                                                                            data-action="<?php echo esc_attr($action); ?>"
                                                                            <?php echo isset($action_data['wakeword']) && $action_data['wakeword'] !== '' ? 'checked' : ''; ?>>
                                                                        <span class="wakeword-label">wake word</span>
                                                                    </label>
                                                                    <div class="wakeword-info">
                                                                        <span class="dashicons dashicons-info-outline"></span>
                                                                        <div class="tooltip">
                                                                            <p>If the wake word is enabled, this event will only trigger when the specified wake word is present in the content.</p>
                                                                            
                                                                            <div class="example-section">
                                                                                <p>Example:</p>
                                                                                <p>If your wake word is set to "agent please", you can inject prompts like:</p>
                                                                                <div class="example-box">agent please, generate all product details</div>
                                                                            </div>
                                                                            
                                                                            <div class="tip">
                                                                                <p>Tip:</p>
                                                                                <p>Place the wake word strategically in titles or descriptions to control when and where AI assistance is needed. You can also enclose your prompt within HTML comments to ensure it remains invisible to the user during AI processing, like this:</p>
                                                                                <div class="example-box">iPhone 16 Pro &lt;!--agent please, generate a description for this product--&gt;</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <input type="text" 
                                                                        name="openkbs_apps[<?php echo $app_id; ?>][wp_actions][<?php echo esc_attr($action); ?>][wakeword]" 
                                                                        value="<?php echo esc_attr($action_data['wakeword'] ?? ''); ?>" 
                                                                        class="wakeword-input" 
                                                                        placeholder="agent please">
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Access Keys</th>
                        <td>
                            <button type="button" class="button toggle-api-keys" data-app-id="<?php echo $app_id; ?>">
                                <span class="dashicons dashicons-arrow-right"></span> Show Access Keys
                            </button>
                            <div class="api-keys-section" style="display: none; margin-top: 15px;">
                                <table class="api-keys-table" style="border-collapse: collapse; width: 100%;">
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <label>OpenKBS API Key</label><br>
                                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][apiKey]"
                                                   value="<?php echo esc_attr($app['apiKey']); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <label>OpenKBS AES Key</label><br>
                                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][AESKey]"
                                                   value="<?php echo esc_attr($app['AESKey']); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <label>OpenKBS Private Key</label><br>
                                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][walletPrivateKey]"
                                                   value="<?php echo esc_attr($app['walletPrivateKey']); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <label>OpenKBS Public Key</label><br>
                                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][walletPublicKey]"
                                                   value="<?php echo esc_attr($app['walletPublicKey']); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <label>WP Plugin API Key</label><br>
                                            <input type="password" name="openkbs_apps[<?php echo $app_id; ?>][wpapiKey]"
                                                   value="<?php echo esc_attr($app['wpapiKey']); ?>" class="regular-text" readonly>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>

            <?php !empty($apps) && submit_button('Save All'); ?>
        </form>
                <?php
                break;
        }
        ?>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.public-chat-toggle').each(function() {
                    const toggle = $(this);
                    const settingsSection = toggle.closest('td').find('.public-chat-settings');
                    const appId = toggle.closest('.app-settings').find('.embedding-model-select').data('app-id');

                    // Initialize CodeMirror with enhanced settings
                    const editorTextarea = document.getElementById('openkbs-code-editor-' + appId);
                    if (editorTextarea) {
                        const editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                        editorSettings.codemirror = _.extend(
                            {},
                            editorSettings.codemirror,
                            {
                                mode: 'php',
                                lineNumbers: true,
                                lineWrapping: true,
                                autoCloseBrackets: true,
                                matchBrackets: true,
                                indentUnit: 4,
                                indentWithTabs: false,
                                extraKeys: {
                                    "Tab": "indentMore",
                                    "Shift-Tab": "indentLess",
                                },
                                theme: 'default',
                                styleActiveLine: true,
                                matchBrackets: true,
                                autoCloseBrackets: true,
                                foldGutter: true,
                                gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                                foldOptions: {
                                    widget: "...",
                                    minFoldSize: 3
                                }
                            }
                        );

                        const editor = wp.codeEditor.initialize(editorTextarea, editorSettings);

                        // Store editor instance for reset functionality
                        window.openkbsEditors = window.openkbsEditors || {};
                        window.openkbsEditors[appId] = editor;

                        // Handle toggle
                        toggle.change(function() {
                            if ($(this).is(':checked')) {
                                settingsSection.slideDown(300, function() {
                                    editor.codemirror.refresh();
                                });
                            } else {
                                settingsSection.slideUp(300);
                            }
                        });
                    }
                });

                // Handle reset button click
                $('.reset-code-editor').click(function() {
                    const appId = $(this).data('app-id');
                    const editor = window.openkbsEditors[appId];

                    if (confirm('Are you sure you want to reset the function to its default state? This will overwrite any changes you have made.')) {
                        // Get default template via AJAX
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'get_default_config_function',
                            },
                            success: function(response) {
                                if (response.success) {
                                    editor.codemirror.setValue(response.data);
                                } else {
                                    alert('Error loading default template');
                                }
                            },
                            error: function() {
                                alert('Error loading default template');
                            }
                        });
                    }
                });

                $('.index-posts-button').click(function() {
                    const button = $(this);
                    const appId = button.data('app-id');
                    const statusDiv = button.siblings('.indexing-status');
                    const resultsDiv = button.closest('div').siblings('.indexing-results');
                    const onlyUnindexed = button.closest('div').parent().find('.index-only-unindexed').is(':checked');

                    // Disable button and show status
                    button.prop('disabled', true);
                    statusDiv.show();
                    resultsDiv.hide();

                    // Initialize cumulative counter
                    let totalProcessed = 0;
                    let isFirstBatch = true;

                    function processIndexing() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'process_posts_for_indexing',
                                app_id: appId,
                                only_unindexed: onlyUnindexed,
                                nonce: '<?php echo wp_create_nonce("process_posts_nonce"); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Update counters
                                    totalProcessed += parseInt(response.data.processed);

                                    // Store initial total on first batch
                                    if (isFirstBatch) {
                                        isFirstBatch = false;
                                    }

                                    resultsDiv.show();
                                    resultsDiv.find('.processed-count').text(totalProcessed);

                                    // If only processing unindexed items and there are still items to process
                                    if (onlyUnindexed && response.data.total > 0) {
                                        // Continue processing after a short delay
                                        setTimeout(processIndexing, 1000); // 1 second delay between batches
                                    } else {
                                        // Enable button and hide status when complete
                                        button.prop('disabled', false);
                                        statusDiv.hide();
                                    }
                                } else {
                                    alert('Error processing posts: ' + response.data);
                                    button.prop('disabled', false);
                                    statusDiv.hide();
                                }
                            },
                            error: function() {
                                alert('An error occurred. Please try again.');
                                button.prop('disabled', false);
                                statusDiv.hide();
                            }
                        });
                    }

                    // Start the processing
                    processIndexing();
                });

                $('#filesystem-api-toggle').change(function() {
                    const isEnabled = $(this).is(':checked');
                    const statusMessage = $('#api-status-message');

                    // Show loading state
                    $(this).prop('disabled', true);
                    statusMessage.html('Saving...').css({
                        'display': 'block',
                        'background-color': '#f0f0f0',
                        'color': '#666'
                    });

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'toggle_filesystem_api',
                            enabled: isEnabled,
                            nonce: '<?php echo wp_create_nonce('filesystem_api_toggle'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                statusMessage.html('Settings saved successfully!').css({
                                    'background-color': '#dff0d8',
                                    'color': '#3c763d'
                                });
                            } else {
                                statusMessage.html('Error: ' + response.data).css({
                                    'background-color': '#f2dede',
                                    'color': '#a94442'
                                });
                                // Revert toggle if save failed
                                $('#filesystem-api-toggle').prop('checked', !isEnabled);
                            }
                        },
                        error: function() {
                            statusMessage.html('Network error occurred').css({
                                'background-color': '#f2dede',
                                'color': '#a94442'
                            });
                            // Revert toggle if save failed
                            $('#filesystem-api-toggle').prop('checked', !isEnabled);
                        },
                        complete: function() {
                            $('#filesystem-api-toggle').prop('disabled', false);
                            // Hide status message after 3 seconds
                            setTimeout(function() {
                                statusMessage.fadeOut();
                            }, 3000);
                        }
                    });
                });
            });
        </script>

    <script>
    jQuery(document).ready(function($) {
        // Handle post type mode selection
        $('.post-type-mode').change(function() {
            const specificContainer = $(this).closest('.post-types-container').find('.specific-types-container');
            if ($(this).val() === 'specific') {
                specificContainer.slideDown();
            } else {
                specificContainer.slideUp();
                // Clear specific post types when switching to 'all'
                specificContainer.find('.selected-types-list').empty();
            }
        });

        // Handle post type addition
        $('.add-post-type').click(function() {
            const container = $(this).closest('.post-types-container');
            const input = container.find('.post-type-input');
            const value = input.val().trim().toLowerCase();

            // Validate input
            if (value === '') return;

            // Check if already exists
            const existingInputs = container.find('.post-type-item input[type="hidden"]');
            let exists = false;
            existingInputs.each(function() {
                if ($(this).val() === value) {
                    exists = true;
                    return false;
                }
            });

            if (exists) {
                alert('This post type already exists');
                return;
            }

            // Create new post type item
            const appId = $(this).closest('tr').find('.embedding-model-select').data('app-id');
            const newItem = $('<div class="post-type-item">' +
                '<input type="hidden" name="openkbs_apps[' + appId + '][semantic_search][post_types][]" value="' + value + '">' +
                '<span>' + value + '</span>' +
                '<button type="button" class="remove-post-type">×</button>' +
                '</div>');

            container.find('.selected-types-list').append(newItem);
            input.val('');
            showUnsavedChangesNotice();
        });

        // Handle post type removal
        $(document).on('click', '.remove-post-type', function() {
            $(this).closest('.post-type-item').remove();
        });

        // Handle enter key in post type input
        $('.post-type-input').keypress(function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).closest('.post-types-input').find('.add-post-type').click();
            }
        });

        // Initialize toggle states on page load
        $('.semantic-search-toggle').each(function() {
            const settingsSection = $(this).closest('td').find('.semantic-search-settings');
            if ($(this).is(':checked')) {
                settingsSection.show();
                // Initialize embedding model restrictions
                settingsSection.find('.embedding-model-select').trigger('change');
            } else {
                settingsSection.hide();
            }
        });

        // Handle semantic search toggle
        $('.semantic-search-toggle').change(function() {
            const settingsSection = $(this).closest('td').find('.semantic-search-settings');
            if ($(this).is(':checked')) {
                settingsSection.slideDown(300);

                // Initialize default values if empty
                const postTypesMode = settingsSection.find('input[name$="[post_types_mode]"]:checked').length === 0;
                if (postTypesMode) {
                    // Set default to 'all' if no option is selected
                    settingsSection.find('input[name$="[post_types_mode]"][value="all"]').prop('checked', true);
                }

                // Trigger change to ensure proper display of specific types container
                settingsSection.find('input[name$="[post_types_mode]"]:checked').trigger('change');

                // Initialize embedding model restrictions
                settingsSection.find('.embedding-model-select').trigger('change');
            } else {
                settingsSection.slideUp(300);
            }
        });

        $('.toggle-api-keys').click(function() {
            const button = $(this);
            const keysSection = button.next('.api-keys-section');

            keysSection.slideToggle(200, function() {
                button.toggleClass('active');

                if (button.hasClass('active')) {
                    button.html('<span class="dashicons dashicons-arrow-right"></span> Hide Access Keys');
                } else {
                    button.html('<span class="dashicons dashicons-arrow-right"></span> Show Access Keys');
                }
            });
        });

        // Toggle sections
        $('.plugin-title').click(function() {
            $(this).find('.dashicons').toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2');
            $(this).closest('.plugin-section').find('.plugin-content').slideToggle();
        });

        $('.category-title').click(function() {
            $(this).find('.dashicons').toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2');
            $(this).closest('.category-section').find('.category-content').slideToggle();
        });

        // Search functionality
        $('[id^="eventSearch-"]').on('input', function() {
            const searchText = $(this).val().toLowerCase();
            const appContainer = $(this).closest('td').find('.events-container');

            appContainer.find('.event-item').each(function() {
                const text = $(this).text().toLowerCase();
                const matches = text.includes(searchText);
                $(this).toggle(matches);

                if (searchText) {
                    if (matches) {
                        $(this).closest('.category-section').show()
                            .closest('.plugin-section').show()
                            .find('.plugin-content, .category-content').show();
                    }
                } else {
                    $('.plugin-content, .category-content').hide();
                }
            });
        });

        // Function to disable or enable inputs within an event-item
        function toggleEventInputs(eventItem, enable) {
            eventItem.find('input').not('.event-checkbox').prop('disabled', !enable);
            if (!enable) {
                // Uncheck wakeword checkbox and clear wakeword input
                eventItem.find('.wakeword-checkbox').prop('checked', false);
                eventItem.find('.wakeword-input').removeClass('active').val('');
            }
        }

        // Wakeword functionality tied to event checkbox
        $('.event-checkbox').change(function() {
            const eventItem = $(this).closest('.event-item');
            const wakewordSection = eventItem.find('.wakeword-section');
            if ($(this).is(':checked')) {
                wakewordSection.show();
                toggleEventInputs(eventItem, true);
            } else {
                wakewordSection.hide();
                toggleEventInputs(eventItem, false);
            }
        });

        // Initialize wakeword-section visibility and input states based on event checkbox state
        $('.event-checkbox').each(function() {
            const eventItem = $(this).closest('.event-item');
            const wakewordSection = eventItem.find('.wakeword-section');
            if ($(this).is(':checked')) {
                wakewordSection.show();
                toggleEventInputs(eventItem, true);
            } else {
                wakewordSection.hide();
                toggleEventInputs(eventItem, false);
            }
        });

        // Wakeword checkbox functionality
        $('.wakeword-checkbox').change(function() {
            const input = $(this).closest('.wakeword-section').find('.wakeword-input');
            if ($(this).is(':checked')) {
                input.addClass('active').prop('disabled', false);
                // Set default wakeword if empty
                if (input.val() === '') {
                    input.val('agent please');
                }
            } else {
                input.removeClass('active').prop('disabled', true).val('');
            }
        });

        // Initialize wakeword inputs
        $('.wakeword-checkbox').each(function() {
            const input = $(this).closest('.wakeword-section').find('.wakeword-input');
            if ($(this).is(':checked')) {
                input.addClass('active').prop('disabled', false);
            } else {
                input.removeClass('active').prop('disabled', true);
            }
        });

        // Open categories and plugins with checked events by default
        $('.event-checkbox:checked').each(function() {
            const categoryContent = $(this).closest('.category-content');
            const pluginContent = $(this).closest('.plugin-content');

            categoryContent.show();
            categoryContent.prev('.category-title').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');

            pluginContent.show();
            pluginContent.prev('.plugin-title').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
        });

        // Handle delete button click
        $('.delete-app').click(function() {
            const appId = $(this).data('app-id');
            const confirmed = window.confirm('Are you sure you want to delete this app?');

            if (confirmed) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_openkbs_app',
                        app_id: appId,
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to delete app: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while trying to delete the app.');
                    }
                });
            }
        });

        jQuery(document).ready(function($) {
            let hasUnsavedChanges = false;

            // Track changes on all form inputs
            $('form input, form select, form textarea').on('change', function() {
                hasUnsavedChanges = true;
                showUnsavedChangesNotice();
            });

            // Add notice container after the form
            $('form').append('<div id="unsaved-changes-notice" style="display: none;" class="notice notice-warning is-dismissible"><p><strong>You have unsaved changes!</strong> Don\'t forget to click the "Save All" button to apply your changes.</p></div>');

            function showUnsavedChangesNotice() {
                $('#unsaved-changes-notice').slideDown();

                // Highlight save buttons
                $('input[type="submit"]').addClass('button-primary-highlight').css({
                    'animation': 'pulse 2s infinite',
                    'box-shadow': '0 0 0 0 rgba(51, 122, 183, 1)'
                });
            }

            // Clear notice when form is submitted
            $('form').on('submit', function() {
                hasUnsavedChanges = false;
                $('#unsaved-changes-notice').slideUp();
            });

            // Warn user when leaving page with unsaved changes
            $(window).on('beforeunload', function() {
                if (hasUnsavedChanges) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        });
    });
    </script>
    <?php
}

function openkbs_register_settings() {
    register_setting('openkbs_filesystem_settings', 'openkbs_filesystem_api_enabled');
    register_setting('openkbs_settings', 'openkbs_apps');
    register_setting('openkbs_search_settings', 'openkbs_public_search_enabled');
}

function openkbs_get_default_config_function() {
    return file_get_contents(plugin_dir_path(__FILE__) . '../templates/default-get-config.php');
}


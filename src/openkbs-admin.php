<?php

 if (!defined('ABSPATH')) {
     exit; // Exit if accessed directly
 }

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

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

function openkbs_blueprints_page() {
    $is_localhost = explode(':', $_SERVER['HTTP_HOST'])[0] === 'localhost';
    $blueprints_url = $is_localhost 
        ? 'http://localhost:3002/wordpress-ai-plugin-blueprints/' 
        : 'https://openkbs.com/wordpress-ai-plugin-blueprints/';
    
    openkbs_render_iframe($blueprints_url, true);
}

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

function openkbs_get_available_wp_actions() {
    return [
        'WordPress' => [
            'Posts' => [
                'publish_post' => 'Post Published',
                'before_delete_post' => 'Post Deleted',
                'post_updated' => 'Post Updated'
            ],
            'Comments' => [
                'wp_insert_comment' => 'New Comment',
                'delete_comment' => 'Comment Deleted',
                'edit_comment' => 'Comment Edited'
            ],
            'Users' => [
                'user_register' => 'User Registered',
                'profile_update' => 'Profile Updated',
                'delete_user' => 'User Deleted'
            ],
            'Terms' => [
                'created_term' => 'Term Created',
                'edited_term' => 'Term Updated',
                'delete_term' => 'Term Deleted'
            ],
            'Media' => [
                'add_attachment' => 'Media Uploaded',
                'delete_attachment' => 'Media Deleted'
            ]
        ],
        'WooCommerce' => [
            'Orders' => [
                'woocommerce_new_order' => 'New Order',
                'woocommerce_order_status_changed' => 'Order Status Change',
            ],
            'Products' => [
                'woocommerce_update_product' => 'Product Update',
            ],
            'Customers' => [
                'woocommerce_created_customer' => 'New Customer Created'
            ],
            'Cart' => [
                'woocommerce_add_to_cart' => 'Item Added to Cart',
                'woocommerce_cart_item_removed' => 'Item Removed from Cart'
            ],
        ],
        'Contact Form 7' => [
            'Forms' => [
                'wpcf7_mail_failed' => 'Mail Failed',
                'wpcf7_mail_sent' => 'Mail Sent',
            ]
        ]
    ];
}

function openkbs_settings_page() {
    $apps = openkbs_get_apps();

    $available_actions = openkbs_get_available_wp_actions();
    ?>
    <div class="wrap">
        <form method="post" action="options.php">
        <h2 style="margin: 0;padding-bottom: 20px;">OpenKBS Apps Settings</h2>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">                
            <?php !empty($apps) && submit_button('Save Agents'); ?>    
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
                                                                                <p>If your wake word is set to "woo please", you can inject prompts like:</p>
                                                                                <div class="example-box">woo please, generate all product details</div>
                                                                            </div>
                                                                            
                                                                            <div class="tip">
                                                                                <p>Tip:</p>
                                                                                <p>Place the wake word strategically in titles or descriptions to control when and where AI assistance is needed. You can also enclose your prompt within HTML comments to ensure it remains invisible to the user during AI processing, like this:</p>
                                                                                <div class="example-box">iPhone 16 Pro &lt;!--woo please, generate a description for this product--&gt;</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <input type="text" 
                                                                        name="openkbs_apps[<?php echo $app_id; ?>][wp_actions][<?php echo esc_attr($action); ?>][wakeword]" 
                                                                        value="<?php echo esc_attr($action_data['wakeword'] ?? ''); ?>" 
                                                                        class="wakeword-input" 
                                                                        placeholder="woo please">
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

            <?php !empty($apps) && submit_button('Save Agents'); ?>    
        </form>


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

        <div class="search-test-interface" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
            <h3>Search API Settings</h3>

            <div style="max-width: 800px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Select Knowledge Base</label>
                    <select id="search-kb-select" style="width: 100%; max-width: 400px;">
                        <?php
                        $apps = openkbs_get_apps();
                        foreach ($apps as $app_id => $app) {
                            if (isset($app['semantic_search']['enabled']) && $app['semantic_search']['enabled'] === 'on') {
                                echo '<option value="' . esc_attr($app_id) . '" ' .
                                     'data-api-key="' . esc_attr($app['wpapiKey']) . '">' .
                                     esc_html($app['kbTitle']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Search Query</label>
                    <input type="text" id="search-query" style="width: 100%;" placeholder="Enter your search query">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Results Limit</label>
                    <input type="number" id="search-limit" value="10" min="1" max="100" style="width: 100px;">
                </div>

                <button type="button" id="run-search" class="button button-primary">Run Search</button>

                <div id="search-results" style="margin-top: 20px; display: none;">
                    <h4>Search Results</h4>
                    <div id="results-container"></div>
                </div>
            </div>

            <br />
            <div class="public-search-settings" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccc;">
                <h3>Public Search API Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Public Search API</th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="public-search-toggle"
                                    <?php checked(get_option('openkbs_public_search_enabled'), true); ?>>
                                <span class="slider round"></span>
                            </label>
                            <div class="security-notice" style="margin-top: 15px; padding: 15px; background: #fff8e5; border-left: 4px solid #ffb900;">
                                <h4 style="margin-top: 0; color: #826200;">ℹ️ Public Access Notice</h4>
                                <p style="margin-bottom: 10px;">
                                    This setting enables a public search endpoint that can be accessed without authentication.
                                    When enabled, anyone can search your public content through the API at:
                                </p>
                                <code style="display: block; padding: 10px; background: #f7f7f7; margin: 10px 0;">
                                    <?php echo esc_url(rest_url('openkbs/v1/search-public')); ?>
                                </code>
                                <p style="margin-bottom: 0; color: #826200;">
                                    Only enable this if you want to allow public access to your semantic search functionality.
                                </p>
                            </div>
                            <div id="public-search-status-message" style="display: none; margin-top: 10px; padding: 10px; border-radius: 4px;"></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>


        <script>
            jQuery(document).ready(function($) {
                // Add this to the existing jQuery document ready function
                $('#public-search-toggle').change(function() {
                    const isEnabled = $(this).is(':checked');
                    const statusMessage = $('#public-search-status-message');

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
                            action: 'toggle_public_search',
                            enabled: isEnabled,
                            nonce: '<?php echo wp_create_nonce("public_search_toggle"); ?>'
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
                                $('#public-search-toggle').prop('checked', !isEnabled);
                            }
                        },
                        error: function() {
                            statusMessage.html('Network error occurred').css({
                                'background-color': '#f2dede',
                                'color': '#a94442'
                            });
                            $('#public-search-toggle').prop('checked', !isEnabled);
                        },
                        complete: function() {
                            $('#public-search-toggle').prop('disabled', false);
                            setTimeout(function() {
                                statusMessage.fadeOut();
                            }, 3000);
                        }
                    });
                });
                $('#run-search').click(function() {
                    const button = $(this);
                    const resultsDiv = $('#search-results');
                    const resultsContainer = $('#results-container');

                    // Get search parameters
                    const query = $('#search-query').val();
                    const kbId = $('#search-kb-select').val();
                    const limit = $('#search-limit').val();

                    if (!query) {
                        alert('Please enter a search query');
                        return;
                    }

                    // Get the API key for the selected KB
                    const apiKey = $('#search-kb-select option:selected').data('api-key');

                    if (!apiKey) {
                        alert('No API key found for the selected knowledge base');
                        return;
                    }

                    // Show loading state
                    button.prop('disabled', true).text('Searching...');
                    resultsContainer.html('<div class="spinner is-active" style="float: none; margin: 0;"></div>');
                    resultsDiv.show();

                    // Make API request
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('openkbs/v1/search')); ?>',
                        method: 'GET',
                        data: {
                            query: query,
                            kbId: kbId,
                            limit: limit
                        },
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('WP-API-KEY', apiKey);
                        },
                        success: function(response) {
                            if (response.success && response.results) {
                                // Clear previous results
                                resultsContainer.empty();

                                if (response.results.length === 0) {
                                    resultsContainer.html('<p>No results found.</p>');
                                    return;
                                }

                                // Create results table
                                const table = $('<table class="wp-list-table widefat fixed striped">').appendTo(resultsContainer);

                                // Add table header
                                table.append(`
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">Score</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th style="width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                `);

                                // Add results
                                const tbody = $('<tbody>').appendTo(table);
                                response.results.forEach(function(result) {
                                    const score = (result.similarity * 100).toFixed(2);
                                    const row = $('<tr>').appendTo(tbody);

                                    row.append(`
                                        <td>${score}%</td>
                                        <td>${result.title}</td>
                                        <td>${result.post_type}</td>
                                        <td>
                                            <a href="${result.url}" target="_blank" class="button button-small">View</a>
                                        </td>
                                    `);
                                });
                            } else {
                                resultsContainer.html('<p>Error: ' + (response.message || 'Unknown error occurred') + '</p>');
                            }
                        },
                        error: function(xhr, status, error) {
                            let errorMessage = error;
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.message || error;
                            } catch (e) {}
                            resultsContainer.html('<p class="error" style="color: #dc3232;">Error: ' + errorMessage + '</p>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('Run Search');
                        }
                    });
                });

                // Allow Enter key to trigger search
                $('#search-query').keypress(function(e) {
                    if (e.which === 13) {
                        $('#run-search').click();
                    }
                });

                // Indexing functionality
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

    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }
        
        .wakeword-info {
            position: relative;
            display: inline-flex;
            align-items: center;
            margin-left: 5px;
        }

        .example-box {
            font-family: "Courier New", Courier, monospace;
            background-color: #f5f5f5;
            color: #333;
            padding: 5px; 
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-top: 5px;
            white-space: pre-wrap;
            font-size: 0.9em;
        }

        .wakeword-info .dashicons {
            color: #007cba;
            font-size: 16px;
            cursor: help;
        }

        .wakeword-info .tooltip {
            visibility: hidden;
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 280px;
            background-color: #333;
            color: #fff;
            text-align: left;
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.4;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .wakeword-info .tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .wakeword-info:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        /* Optional: Add a subtle animation */
        @keyframes tooltipFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .wakeword-info:hover .tooltip {
            animation: tooltipFade 0.3s ease-in-out;
        }
        .plugin-section, .category-section {
            border: 1px solid #eee;
            margin: 5px 0;
            padding: 5px;
            background: #fff;
        }

        .plugin-title, .category-title {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px 0;
        }

        .plugin-title:hover, .category-title:hover {
            color: #2271b1;
        }

        .plugin-content, .category-content {
            display: none;
            padding-left: 20px;
        }

        .event-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
        }

        .event-item:hover {
            background-color: #f5f5f5;
        }

        .event-main {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-label {
            font-size: 13px;
        }

        .wakeword-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wakeword-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #666;
        }

        .wakeword-input {
            display: none;
            width: 120px;
            padding: 2px 5px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .wakeword-input.active {
            display: inline-block;
        }
        .toggle-api-keys {
            display: flex !important;
            align-items: center;
            gap: 5px;
        }

        .toggle-api-keys .dashicons {
            transition: transform 0.3s ease;
        }

        .toggle-api-keys.active .dashicons {
            transform: rotate(90deg);
        }

        .api-keys-table td {
            padding: 8px 0;
        }

        .api-keys-table label {
            font-weight: 600;
            color: #666;
            margin-bottom: 4px;
            display: inline-block;
        }

        .post-types-container {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background: #fff;
        }

        .post-types-selection {
            margin-bottom: 10px;
        }

        .post-type-label {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 5px;
        }

        .post-types-input {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }

        .post-type-input {
            flex: 1;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .selected-types-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .post-type-item {
            display: inline-flex;
            align-items: center;
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 13px;
        }

        .remove-post-type {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 0 0 0 5px;
            font-size: 16px;
            line-height: 1;
        }

        .remove-post-type:hover {
            color: #dc3232;
        }
            </style>

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
                    input.val('woo please');
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
    });
    </script>
    <?php
}

function openkbs_register_settings() {
    register_setting('openkbs_filesystem_settings', 'openkbs_filesystem_api_enabled');
    register_setting('openkbs_settings', 'openkbs_apps');
    register_setting('openkbs_settings', 'openkbs_public_search_enabled');
}
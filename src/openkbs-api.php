<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

function openkbs_handle_filesystem_api_toggle() {
    // Verify nonce
    if (!check_ajax_referer('filesystem_api_toggle', 'nonce', false)) {
        wp_send_json_error('Invalid security token.');
        return;
    }

    // Verify user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to change this setting.');
        return;
    }

    // Get and validate the enabled parameter
    $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

    // Update the option
    $updated = update_option('openkbs_filesystem_api_enabled', $enabled);

    if ($updated) {
        wp_send_json_success([
            'message' => $enabled 
                ? 'Filesystem API has been enabled. Remember to disable it after completing your AI development tasks.' 
                : 'Filesystem API has been disabled.',
            'status' => $enabled ? 'enabled' : 'disabled'
        ]);
    } else {
        wp_send_json_error('Failed to update setting.');
    }
}

function openkbs_handle_callback(WP_REST_Request $request) {
    $params = $request->get_params();
    
    if (!isset($params['post_id'])) {
        return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
    }
    
    // Store the callback data in a transient with a unique key
    $transient_key = 'openkbs_callback_' . $params['post_id'];
    set_transient($transient_key, $params, 60);
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Callback received'
    ), 200);
}

function openkbs_handle_polling() {
    check_ajax_referer('openkbs_polling_nonce', 'nonce');
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    $transient_key = 'openkbs_callback_' . $post_id;

    $callback_data = get_transient($transient_key);
    
    if ($callback_data && $callback_data['type']) {
        // Clear the transient
        delete_transient($transient_key);
        
        wp_send_json_success(array(
            'callback_data' => $callback_data,
            'type' => $callback_data['type'],
            'success' => true
        ));
    } else {
        wp_send_json_success(array(
            'success' => false
        ));
    }
}

function openkbs_register_app() {
    if (isset($_POST['JWT']) && isset($_POST['kbId']) && isset($_POST['apiKey']) && isset($_POST['kbTitle']) && isset($_POST['AESKey']) && isset($_POST['walletPrivateKey']) && isset($_POST['walletPublicKey'])) {
        $jwt = sanitize_text_field($_POST['JWT']);
        $kbId = sanitize_text_field($_POST['kbId']);
        $apiKey = sanitize_text_field($_POST['apiKey']);
        $kbTitle = sanitize_text_field($_POST['kbTitle']);
        $AESKey = sanitize_text_field($_POST['AESKey']);
        $walletPrivateKey = sanitize_text_field($_POST['walletPrivateKey']);
        $walletPublicKey = sanitize_text_field($_POST['walletPublicKey']);
        $wpapiKey = wp_generate_password(20, true, false);        

        // First level encryption with an in-browser generated AES key
        $encrypted_wpapi_key = openkbs_encrypt_kb_item($wpapiKey, $AESKey);
        $encrypted_site_url = openkbs_encrypt_kb_item(get_site_url(), $AESKey);

        /*
        * Transmit to secret storage for second-level encryption with an asymmetric public key.
        * Only the code execution service can decrypt the second-level, 
        * as the storage service lacks the private key for security reasons.
        */
        $api_response = openkbs_store_secret('wpapiKey', $encrypted_wpapi_key, $jwt);
        $url_response = openkbs_store_secret('wpUrl', $encrypted_site_url, $jwt);
                
        if ($api_response === false || $url_response === false) {
            wp_send_json_error('Failed to create secret');
            return;
        }
        
        // If secret creation was successful, proceed with local storage
        $apps = openkbs_get_apps();


        if (!is_array($apps)) {
            $apps = array();
        }    
        
        $apps[$kbId] = array(
            'kbId' => $kbId,
            'apiKey' => $apiKey,
            'kbTitle' => $kbTitle,
            'AESKey' => $AESKey,
            'walletPrivateKey' => $walletPrivateKey,
            'walletPublicKey' => $walletPublicKey,
            'wpapiKey' => $wpapiKey
        );

        update_option('openkbs_apps', $apps);
        wp_send_json_success(array(
            'message' => 'App registered successfully',
            'appId' => $kbId,
            'redirect' => admin_url('admin.php?page=openkbs-app-' . $kbId)
        ));
    } else {
        wp_send_json_error('Incomplete data provided');
    }
}

function openkbs_delete_app() {
    if (isset($_POST['app_id'])) {
        $app_id = sanitize_text_field($_POST['app_id']);
        $apps = openkbs_get_apps();
        
        if (isset($apps[$app_id])) {
            unset($apps[$app_id]);
            update_option('openkbs_apps', $apps);
            wp_send_json_success('App deleted successfully');
        } else {
            wp_send_json_error('App not found');
        }
    } else {
        wp_send_json_error('No app ID provided');
    }
}

function openkbs_get_app() {
    $current_page = $_GET['page'];
    $app_id = str_replace('openkbs-app-', '', $current_page);
    $apps = openkbs_get_apps();
    return $apps[$app_id];
}

function openkbs_create_encrypted_payload($content, $chatTitle, $app) {
    return array(
        'kbId' => $app['kbId'],
        'apiKey' => $app['apiKey'],
        'chatTitle' => $chatTitle,
        'encrypted' => true,
        'message' => openkbs_encrypt_kb_item(json_encode(array(
            'type' => 'API_REQUEST',
            'data' => $content,
            '_meta_actions' => ['REQUEST_CHAT_MODEL']
        )), $app['AESKey'])
    );
}

function openkbs_publish($data, $chatTitle) {
    $apps = openkbs_get_apps();
    $event = $data['event'];

    $data['_wordpress']['post_id'] = get_the_ID() ?? null;

    if (empty($apps)) return;

    $dataJson = json_encode($data);

    // Send to all subscribers
    $request_sent = false;
    foreach ($apps as $app) {
        if (!isset($app['wp_actions']) || !is_array($app['wp_actions'])) {
            continue;
        }

        if (isset($app['wp_actions'][$event])) {
            $action = $app['wp_actions'][$event];
            $wakeword = isset($action['wakeword']) ? $action['wakeword'] : null;

            if ($wakeword) {
                // Clean up both the wakeword and the data string for comparison
                $cleanWakeword = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $wakeword));
                $cleanDataJson = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dataJson));

                if (strpos($cleanDataJson, $cleanWakeword) === false) {
                    continue;
                }
            }

            $payload = openkbs_create_encrypted_payload($data, $chatTitle, $app);

            $args = array(
                'body' => json_encode($payload),
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 0.01,
                'blocking' => false
            );

            // Send the request
            $response = wp_remote_post('https://chat.openkbs.com/', $args);
            $request_sent = true;
        }
    }

    // Set a transient flag for this specific post/action combination
    if ($request_sent && !empty($data['_wordpress']['post_id'])) {
        $polling_key = 'openkbs_polling_' . $data['_wordpress']['post_id'];
        set_transient($polling_key, true, 60);
    }
}

function openkbs_get_embedding($text, $app_id, $model = 'text-embedding-3-large', $dimension = 1536) {
    $apps = openkbs_get_apps();
    $models = openkbs_get_embedding_models();

    // Validate model
    if (!isset($models[$model])) {
        throw new Exception("Invalid embedding model specified");
    }

    $walletPrivateKey = $apps[$app_id]['walletPrivateKey'];
    $walletPublicKey = $apps[$app_id]['walletPublicKey'];
    $accountId = openkbs_create_account_id($walletPublicKey);

    $transactionJWT = openkbs_sign_payload([
      'operation' => 'transfer',
      'resourceId' => 'credits',
      'transactionId' => openkbs_generate_txn_id(),
      'fromAccountId' => $accountId,
      'fromAccountPublicKey' => $walletPublicKey,
      'toAccountId' => $models[$model]['accountId'],
      'message' => '',
      'maxAmount' => 100000, // one USD
      'iat' => round(microtime(true))
    ], $accountId, $walletPublicKey, $walletPrivateKey);

    $response = wp_remote_post('https://openai.openkbs.com/', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Transaction-JWT' => $transactionJWT
        ],
        'body' => json_encode([
            'action' => 'createEmbedding',
            'input' => $text,
            'model' => $model
        ])
    ]);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $embedding = $body['data'][0]['embedding'];

    if (count($embedding) > $dimension) {
        $embedding = array_slice($embedding, 0, $dimension);
    }

    return $embedding;
}

/**
 * Handle AJAX request for processing posts for indexing
 */
function openkbs_ajax_process_posts() {
    // Verify nonce and capabilities
    check_ajax_referer('process_posts_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }

    // Get parameters
    $app_id = isset($_POST['app_id']) ? sanitize_text_field($_POST['app_id']) : '';
    $only_unindexed = isset($_POST['only_unindexed']) ? $_POST['only_unindexed'] === 'true' : true;

    if (empty($app_id)) {
        wp_send_json_error('App ID is required');
    }

    try {
        // Process the posts
        $result = openkbs_process_all_posts_for_app($app_id, $only_unindexed);

        // Send success response
        wp_send_json_success($result);
    } catch (Exception $e) {
        wp_send_json_error('Error processing posts: ' . $e->getMessage());
    }
}

function openkbs_handle_search(WP_REST_Request $request) {
    $params = $request->get_params();

    if (!isset($params['query'])) {
        return new WP_Error('missing_params', 'Missing required parameter: query', array('status' => 400));
    }

    $limit = isset($params['limit']) ? intval($params['limit']) : 10;
    $itemTypes = isset($params['itemTypes']) ? (array)$params['itemTypes'] : null;
    $kbId = (isset($params['kbId']) && $params['kbId']) ? $params['kbId'] : null;
    $maxPrice = isset($params['maxPrice']) ? floatval($params['maxPrice']) : null;
    $minPrice = isset($params['minPrice']) ? floatval($params['minPrice']) : null;

    try {
        // Get all public post types
        $public_post_types = get_post_types(['public' => true]);

        // Filter itemTypes to ensure only public types are included
        if ($itemTypes !== null) {
            $itemTypes = array_intersect($itemTypes, $public_post_types);

            // If after filtering there are no valid post types, return an error
            if (empty($itemTypes)) {
                return new WP_Error(
                    'invalid_item_types',
                    'No valid public post types specified',
                    array('status' => 400)
                );
            }
        }

        $apps = openkbs_get_apps();

        if ($kbId === null) {
            foreach ($apps as $appId => $appData) {
                if (isset($appData['semantic_search']['enabled']) &&
                    $appData['semantic_search']['enabled'] === 'on') {
                    $kbId = $appId;
                    break;
                }
            }

            if ($kbId === null) {
                return new WP_Error(
                    'no_search_enabled',
                    'No application found with semantic search enabled',
                    array('status' => 400)
                );
            }
        } elseif (!isset($apps[$kbId])) {
            return new WP_Error('invalid_kb', 'Invalid kbId specified:', array('status' => 400));
        }

        $app = $apps[$kbId];

        if (!isset($app['semantic_search']['enabled']) || $app['semantic_search']['enabled'] !== 'on') {
            return new WP_Error(
                'search_disabled',
                'Semantic search is not enabled for this application',
                array('status' => 400)
            );
        }

        // Determine post types to search while ensuring only public types are included
        if ($itemTypes !== null) {
            $post_types = $itemTypes;
        } elseif ($app['semantic_search']['post_types_mode'] === 'all') {
            $post_types = $public_post_types;
        } elseif ($app['semantic_search']['post_types_mode'] === 'specific') {
            $post_types = array_intersect($app['semantic_search']['post_types'], $public_post_types);
        }

        // If no valid post types are available, return an error
        if (empty($post_types)) {
            return new WP_Error(
                'no_valid_post_types',
                'No valid public post types available for search',
                array('status' => 400)
            );
        }

        $query_embedding = openkbs_get_embedding(
            $params['query'],
            $kbId,
            $app['semantic_search']['embedding_model'],
            $app['semantic_search']['embedding_dimensions']
        );

        global $wpdb;
        $post_type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $chunk_size = 500;
        $offset = 0;
        $top_results = [];
        $min_similarity_threshold = -1;

        do {
            // Query with LIMIT and OFFSET for chunking
            $query = $wpdb->prepare(
                "SELECT ID, post_title, post_content, post_excerpt, post_type, openkbs_embedding
                FROM {$wpdb->posts}
                WHERE post_type IN ($post_type_placeholders)
                AND post_status = 'publish'
                AND openkbs_embedding IS NOT NULL
                LIMIT %d OFFSET %d",
                array_merge($post_types, [$chunk_size, $offset])
            );

            $posts = $wpdb->get_results($query);

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $post_embedding = json_decode($post->openkbs_embedding, true);
                if ($post_embedding) {
                    $similarity = openkbs_cosine_similarity($query_embedding, $post_embedding);

                    // Only process if this result has a chance to be in the top N
                    if (count($top_results) < $limit || $similarity > $min_similarity_threshold) {
                        $image_info = openkbs_get_post_image($post->ID);

                        // Get post excerpt
                        $excerpt = !empty($post->post_excerpt)
                            ? $post->post_excerpt
                            : wp_trim_words(strip_shortcodes($post->post_content), 55);

                        $full_url = get_permalink($post->ID);
                        $path = wp_parse_url($full_url, PHP_URL_PATH);

                        $result = [
                            'id' => $post->ID,
                            'title' => $post->post_title,
                            'excerpt' => $excerpt,
                            'similarity' => $similarity,
                            'url' => $full_url,
                            'path' => $path,
                            'post_type' => $post->post_type,
                            'image' => $image_info
                        ];

                        // Add price information if the post is a WooCommerce product
                        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                            $product = wc_get_product($post->ID);
                            if ($product) {
                                $price = floatval($product->get_price());

                                if ($maxPrice !== null && (empty($price) || $price > $maxPrice)) {
                                    continue;
                                }

                                if ($minPrice !== null && (empty($price) || $price < $minPrice)) {
                                    continue;
                                }

                                $result['price'] = [
                                    'regular_price' => $product->get_regular_price(),
                                    'sale_price' => $product->get_sale_price(),
                                    'current_price' => $product->get_price(),
                                    'formatted_price' => $product->get_price_html()
                                ];
                            }
                        }

                        $top_results[] = $result;

                        // Sort and trim to keep only top N results
                        usort($top_results, function($a, $b) {
                            return $b['similarity'] <=> $a['similarity'];
                        });

                        if (count($top_results) > $limit) {
                            array_pop($top_results); // Remove the lowest scoring result
                            $min_similarity_threshold = end($top_results)['similarity'];
                        }
                    }
                }
            }

            $offset += $chunk_size;

        } while (count($posts) === $chunk_size);

        return new WP_REST_Response([
            'success' => true,
            'results' => $top_results,
            'kbId' => $kbId
        ], 200);

    } catch (Exception $e) {
        return new WP_Error('search_error', $e->getMessage(), array('status' => 500));
    }
}


/**
 * Helper function to get post image information
 */
function openkbs_get_post_image($post_id) {
    $image_info = array(
        'thumbnail' => null,
        'medium' => null,
        'large' => null,
        'full' => null
    );

    // First try to get the featured image
    if (has_post_thumbnail($post_id)) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
    } else {
        // If no featured image, try to get the first image from the post
        $post = get_post($post_id);
        $first_image = openkbs_get_first_image_from_post($post->post_content);
        if ($first_image) {
            $thumbnail_id = attachment_url_to_postid($first_image);
        }
    }

    // If we found an image, get its various sizes
    if (!empty($thumbnail_id)) {
        $image_sizes = array('thumbnail', 'medium', 'large', 'full');
        foreach ($image_sizes as $size) {
            $image = wp_get_attachment_image_src($thumbnail_id, $size);
            if ($image) {
                $image_info[$size] = array(
                    'url' => $image[0],
                    'width' => $image[1],
                    'height' => $image[2]
                );
            }
        }
    }

    return $image_info;
}

/**
 * Helper function to extract the first image from post content
 */
function openkbs_get_first_image_from_post($content) {
    if (preg_match('/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', $content, $matches)) {
        return $matches[1];
    }
    return null;
}

function openkbs_handle_public_search_toggle() {
    // Verify nonce
    if (!check_ajax_referer('public_search_toggle', 'nonce', false)) {
        wp_send_json_error('Invalid security token.');
        return;
    }

    // Verify user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to change this setting.');
        return;
    }

    // Get and validate the enabled parameter
    $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

    // Update the option
    $updated = update_option('openkbs_public_search_enabled', $enabled);

    if ($updated) {
        wp_send_json_success([
            'message' => $enabled
                ? 'Public search API has been enabled.'
                : 'Public search API has been disabled.',
            'status' => $enabled ? 'enabled' : 'disabled'
        ]);
    } else {
        wp_send_json_error('Failed to update setting.');
    }
}

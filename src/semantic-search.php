<?php

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

// Make sure we have the necessary database column
function openkbs_add_embedding_columns() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
    $columns = [
        'openkbs_embedding' => 'LONGTEXT',
        'openkbs_embedding_model' => 'VARCHAR(255)'
    ];

    foreach ($columns as $column_name => $column_type) {
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            $column_name
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD `{$column_name}` {$column_type}");
        }
    }
}


// Handle post update/creation
function openkbs_handle_post_update($post_id, $post, $update) {
    // Don't process if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Don't process revisions
    if (wp_is_post_revision($post_id)) return;

    // Get all apps with semantic search enabled
    $apps = openkbs_get_apps();
    $activated_semantic_search = array_filter($apps, function($app) {
        return isset($app['semantic_search']['enabled']) && $app['semantic_search']['enabled'] === 'on';
    });

    if (empty($activated_semantic_search)) return;

    foreach ($activated_semantic_search as $app) {
        $semantic_search = $app['semantic_search'];
        $should_process = false;

        // Check if this post type should be processed
        if ($semantic_search['post_types_mode'] === 'all') {
            $should_process = true;
        } elseif ($semantic_search['post_types_mode'] === 'specific' &&
                  isset($semantic_search['post_types']) &&
                  in_array($post->post_type, $semantic_search['post_types'])) {
            $should_process = true;
        }

        if ($should_process) {
            // Prepare the content for embedding
            $content_for_embedding = $post->post_title . ' ' . strip_tags($post->post_content);

            // Get embedding
            $embedding = openkbs_get_embedding(
                $content_for_embedding,
                $app['kbId'],
                $semantic_search['embedding_model'],
                $semantic_search['embedding_dimensions']
            );

            if ($embedding) {
                // Store the embedding and model in the database
                openkbs_save_post_embedding(
                    $post_id,
                    $embedding,
                    $semantic_search['embedding_model']
                );
            }
        }
    }
}

// Save embedding to the database
function openkbs_save_post_embedding($post_id, $embedding, $model) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';

    // Convert embedding array to JSON string
    $embedding_json = json_encode($embedding);

    // Update the post with the new embedding and model
    $wpdb->update(
        $table_name,
        array(
            'openkbs_embedding' => $embedding_json,
            'openkbs_embedding_model' => $model
        ),
        array('ID' => $post_id),
        array('%s', '%s'),
        array('%d')
    );
}

// Hook into post update/creation
add_action('wp_insert_post', 'openkbs_handle_post_update', 10, 3);

// Optionally, add function to get embedding for a post
function openkbs_get_post_embedding($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';

    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT openkbs_embedding, openkbs_embedding_model FROM $table_name WHERE ID = %d",
        $post_id
    ));

    if (!$result) return null;

    return array(
        'embedding' => json_decode($result->openkbs_embedding, true),
        'model' => $result->openkbs_embedding_model
    );
}

// Add bulk processing function for existing posts
function openkbs_process_existing_posts($app) {
    $semantic_search = $app['semantic_search'];

    $args = array(
        'post_type' => $semantic_search['post_types_mode'] === 'all'
            ? 'any'
            : $semantic_search['post_types'],
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $posts = get_posts($args);

    foreach ($posts as $post) {
        $content_for_embedding = $post->post_title . ' ' . strip_tags($post->post_content);

        $embedding = openkbs_get_embedding(
            $content_for_embedding,
            $app['kbId'],
            $semantic_search['embedding_model'],
            $semantic_search['embedding_dimensions']
        );

        if ($embedding) {
            openkbs_save_post_embedding(
                $post->ID,
                $embedding,
                $semantic_search['embedding_model']
            );
        }
    }
}

// Optional: Add an admin action to process all existing posts
function openkbs_process_all_posts_for_app($app_id) {
    $apps = openkbs_get_apps();
    if (isset($apps[$app_id]) &&
        isset($apps[$app_id]['semantic_search']['enabled']) &&
        $apps[$app_id]['semantic_search']['enabled'] === 'on') {
        openkbs_process_existing_posts($apps[$app_id]);
    }
}
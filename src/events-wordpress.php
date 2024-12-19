<?php

/**
 * WordPress Native Event Handlers
 *
 * Each handler captures relevant data and sends it to OpenKBS via the openkbs_publish function.
 * Handlers provide detailed information about the event that will be processed by the AI Agent.
 *
 * openkbs_publish($event, $title) expects:
 *   - $event: { event: wp_action_name, ...props }
 *   - $title: Chat display title for this event instance
 */

 if (!defined('ABSPATH')) {
     exit; // Exit if accessed directly
 }

require_once plugin_dir_path(__FILE__) . 'openkbs-utils.php';

// Register WordPress hooks
function openkbs_hook_wordpress_events() {
    // Post events
    add_action('publish_post', 'openkbs_handle_post_published', 10, 2);
    add_action('before_delete_post', 'openkbs_handle_post_deleted', 10, 1);
    add_action('post_updated', 'openkbs_handle_post_updated', 10, 3);

    // Comment events
    add_action('wp_insert_comment', 'openkbs_handle_new_comment', 10, 2);
    add_action('delete_comment', 'openkbs_handle_delete_comment', 10, 2);
    add_action('edit_comment', 'openkbs_handle_edit_comment', 10, 2);

    // User events
    add_action('user_register', 'openkbs_handle_user_registered', 10, 1);
    add_action('profile_update', 'openkbs_handle_profile_updated', 10, 2);
    add_action('delete_user', 'openkbs_handle_user_deleted', 10, 1);

    // Category and tag events
    add_action('created_term', 'openkbs_handle_term_created', 10, 3);
    add_action('edited_term', 'openkbs_handle_term_updated', 10, 3);
    add_action('delete_term', 'openkbs_handle_term_deleted', 10, 4);

    // Media events
    add_action('add_attachment', 'openkbs_handle_media_uploaded', 10, 1);
    add_action('delete_attachment', 'openkbs_handle_media_deleted', 10, 1);
}

// Post Handlers
function openkbs_handle_post_published($post_ID, $post) {
    $event = array(
        'event' => 'publish_post',
        'post_id' => $post_ID,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'author' => get_the_author_meta('display_name', $post->post_author),
        'post_type' => $post->post_type,
        'post_date' => $post->post_date,
        'categories' => wp_get_post_categories($post_ID, array('fields' => 'names')),
        'tags' => wp_get_post_tags($post_ID, array('fields' => 'names')),
        'permalink' => get_permalink($post_ID)
    );

    openkbs_publish($event, 'post-published: ' . $post->post_title);
}

function openkbs_handle_post_deleted($post_ID) {
    $post = get_post($post_ID);
    if (!$post) return;

    $event = array(
        'event' => 'before_delete_post',
        'post_id' => $post_ID,
        'title' => $post->post_title,
        'post_type' => $post->post_type,
        'author' => get_the_author_meta('display_name', $post->post_author),
        'deletion_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'post-deleted: ' . $post->post_title);
}

function openkbs_handle_post_updated($post_ID, $post_after, $post_before) {

    $event = array(
        'event' => 'post_updated',
        'post_id' => $post_ID,
        'title' => $post_after->post_title,
        'old_title' => $post_before->post_title,
        'content_changed' => ($post_after->post_content !== $post_before->post_content),
        'author' => get_the_author_meta('display_name', $post_after->post_author),
        'post_type' => $post_after->post_type,
        'update_date' => current_time('Y-m-d H:i:s'),
        'categories' => wp_get_post_categories($post_ID, array('fields' => 'names')),
        'tags' => wp_get_post_tags($post_ID, array('fields' => 'names'))
    );

    openkbs_publish($event, 'post-updated: ' . $post_after->post_title);


}

// Comment Handlers
function openkbs_handle_new_comment($comment_ID, $comment) {
    $event = array(
        'event' => 'wp_insert_comment',
        'comment_id' => $comment_ID,
        'post_id' => $comment->comment_post_ID,
        'post_title' => get_the_title($comment->comment_post_ID),
        'author' => $comment->comment_author,
        'email' => $comment->comment_author_email,
        'content' => $comment->comment_content,
        'date' => $comment->comment_date,
        'approved' => $comment->comment_approved
    );

    openkbs_publish($event, 'comment-new: ' . $comment_ID);
}

function openkbs_handle_delete_comment($comment_ID, $comment) {
    $event = array(
        'event' => 'delete_comment',
        'comment_id' => $comment_ID,
        'post_id' => $comment->comment_post_ID,
        'post_title' => get_the_title($comment->comment_post_ID),
        'author' => $comment->comment_author,
        'deletion_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'comment-deleted: ' . $comment_ID);
}

function openkbs_handle_edit_comment($comment_ID, $comment_data) {
    $event = array(
        'event' => 'edit_comment',
        'comment_id' => $comment_ID,
        'post_id' => $comment_data['comment_post_ID'],
        'post_title' => get_the_title($comment_data['comment_post_ID']),
        'author' => $comment_data['comment_author'],
        'content' => $comment_data['comment_content'],
        'edit_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'comment-edited: ' . $comment_ID);
}

// User Handlers
function openkbs_handle_user_registered($user_id) {
    $user = get_userdata($user_id);

    $event = array(
        'event' => 'user_register',
        'user_id' => $user_id,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'role' => $user->roles,
        'registration_date' => $user->user_registered
    );

    openkbs_publish($event, 'user-registered: ' . $user->user_login);
}

function openkbs_handle_profile_updated($user_id, $old_user_data) {
    $user = get_userdata($user_id);

    $event = array(
        'event' => 'profile_update',
        'user_id' => $user_id,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'role' => $user->roles,
        'old_email' => $old_user_data->user_email,
        'update_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'user-updated: ' . $user->user_login);
}

function openkbs_handle_user_deleted($user_id) {
    $event = array(
        'event' => 'delete_user',
        'user_id' => $user_id,
        'deletion_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'user-deleted: ' . $user_id);
}

// Term Handlers (Categories and Tags)
function openkbs_handle_term_created($term_id, $tt_id, $taxonomy) {
    $term = get_term($term_id, $taxonomy);

    $event = array(
        'event' => 'created_term',
        'term_id' => $term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'taxonomy' => $taxonomy,
        'description' => $term->description
    );

    openkbs_publish($event, 'term-created: ' . $term->name);
}

function openkbs_handle_term_updated($term_id, $tt_id, $taxonomy) {
    $term = get_term($term_id, $taxonomy);

    $event = array(
        'event' => 'edited_term',
        'term_id' => $term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'taxonomy' => $taxonomy,
        'description' => $term->description,
        'update_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'term-updated: ' . $term->name);
}

function openkbs_handle_term_deleted($term_id, $tt_id, $taxonomy, $deleted_term) {
    $event = array(
        'event' => 'delete_term',
        'term_id' => $term_id,
        'name' => $deleted_term->name,
        'slug' => $deleted_term->slug,
        'taxonomy' => $taxonomy,
        'deletion_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'term-deleted: ' . $deleted_term->name);
}

// Media Handlers
function openkbs_handle_media_uploaded($attachment_id) {
    $attachment = get_post($attachment_id);
    $metadata = wp_get_attachment_metadata($attachment_id);

    $event = array(
        'event' => 'add_attachment',
        'attachment_id' => $attachment_id,
        'title' => $attachment->post_title,
        'filename' => basename(get_attached_file($attachment_id)),
        'file_type' => get_post_mime_type($attachment_id),
        'file_size' => filesize(get_attached_file($attachment_id)),
        'dimensions' => isset($metadata['width']) ? $metadata['width'] . 'x' . $metadata['height'] : null,
        'url' => wp_get_attachment_url($attachment_id),
        'upload_date' => $attachment->post_date
    );

    openkbs_publish($event, 'media-uploaded: ' . $attachment->post_title);
}

function openkbs_handle_media_deleted($attachment_id) {
    $attachment = get_post($attachment_id);

    $event = array(
        'event' => 'delete_attachment',
        'attachment_id' => $attachment_id,
        'title' => $attachment->post_title,
        'file_type' => get_post_mime_type($attachment_id),
        'deletion_date' => current_time('Y-m-d H:i:s')
    );

    openkbs_publish($event, 'media-deleted: ' . $attachment->post_title);
}
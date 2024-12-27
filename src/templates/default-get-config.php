<?
/**
 * Get dynamic chat configuration
 *
 * @return array Complete chat configuration
 */
function openkbs_get_config() {
    // Get user information
    $current_user = wp_get_current_user();
    $is_logged_in = !empty($current_user->ID);
    $guest_id = 'Guest_' . substr(md5(time()), 0, 6);

    // Set user details
    $user_name = $is_logged_in ? $current_user->display_name : $guest_id;
    $user_id = $is_logged_in ? $current_user->ID : 0;

    return [
        'chatTitle' => 'Chat with ' . $user_name,

        'variables' => [
            'wordpress_user' => $user_name,
            'wordpress_user_id' => $user_id,
            'wordpress_user_email' => $is_logged_in ? $current_user->user_email : '',
            'wordpress_site_language' => get_locale(),
        ],

        'maxMessages' => $is_logged_in ? 50 : 20, // maxMessages per chat
        'maxTokens' => $is_logged_in ? 64000 : 8000, // max LLM tokens per chat
        'tokenExpiration' => 1000 * 60 * 60, // one hour chat time
        'hello_msg' => $is_logged_in
            ? "Welcome {$user_name}! How can I assist you today?"
            : "Welcome! How can I assist you today?"
    ];
}
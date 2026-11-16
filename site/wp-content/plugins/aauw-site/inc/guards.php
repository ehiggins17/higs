<?php
if (!defined('ABSPATH')) exit;

/**
 * Safety guards for editors.
 * The Home page and the Programs calendar page hold the site together. Editors can change their
 * words, but cannot trash, delete, unpublish, password-lock, or re-template them by accident.
 */
function aauw_protected_page_ids() {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = array();
    $front = (int) get_option('page_on_front');
    if ($front) $ids[] = $front;
    foreach (get_posts(array('post_type' => 'page', 'post_status' => 'any', 'numberposts' => 20, 'fields' => 'ids', 'meta_key' => '_wp_page_template', 'meta_value' => 'templates/programs.php')) as $id) $ids[] = (int) $id;
    return $ids;
}

// No trash / delete for the protected pages (administrators included: it is never the right move).
add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    if ($cap === 'delete_post' && !empty($args[0]) && in_array((int) $args[0], aauw_protected_page_ids(), true)) return array('do_not_allow');
    return $caps;
}, 10, 4);

// Stop the trash and delete operations themselves, before WordPress touches anything.
add_filter('pre_trash_post', function ($check, $post) {
    return ($post && in_array((int) $post->ID, aauw_protected_page_ids(), true)) ? false : $check;
}, 10, 2);
add_filter('pre_delete_post', function ($check, $post) {
    return ($post && in_array((int) $post->ID, aauw_protected_page_ids(), true)) ? false : $check;
}, 10, 2);

// They always stay published, public, and at the same address, whatever the form sends.
add_filter('wp_insert_post_data', function ($data, $postarr) {
    $id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
    if (!$id || $data['post_type'] !== 'page' || !in_array($id, aauw_protected_page_ids(), true)) return $data;
    $before = get_post($id);
    if ($data['post_status'] !== 'publish' && $data['post_status'] !== 'inherit') $data['post_status'] = 'publish';
    $data['post_password'] = '';
    if ($before) { $data['post_name'] = $before->post_name; $data['post_parent'] = $before->post_parent; }
    return $data;
}, 10, 2);

// Keep their page template.
add_filter('update_post_metadata', function ($check, $object_id, $meta_key, $meta_value) {
    if ($meta_key !== '_wp_page_template' || !in_array((int) $object_id, aauw_protected_page_ids(), true)) return $check;
    $current = get_post_meta($object_id, '_wp_page_template', true);
    return ($current !== '' && $meta_value !== $current) ? true : $check; // true = "handled", so the change is skipped
}, 10, 4);

add_action('admin_notices', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || empty($_GET['post'])) return;
    if (!in_array((int) $_GET['post'], aauw_protected_page_ids(), true)) return;
    echo '<div class="notice notice-info"><p><strong>This page is protected.</strong> You can change its words and header, but it cannot be moved to the Trash, unpublished, renamed, or password-protected, because the rest of the site depends on it.</p></div>';
});

/* The site does not use remote publishing (XML-RPC), a common target for password guessing. */
add_filter('xmlrpc_enabled', '__return_false');

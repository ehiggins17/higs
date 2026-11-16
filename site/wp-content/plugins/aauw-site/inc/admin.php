<?php
if (!defined('ABSPATH')) exit;

/* Comments are not used on this site. */
add_filter('comments_open', '__return_false', 20);
add_filter('pings_open', '__return_false', 20);
add_action('admin_menu', function () { remove_menu_page('edit-comments.php'); }, 999);
add_action('wp_before_admin_bar_render', function () { global $wp_admin_bar; if ($wp_admin_bar) $wp_admin_bar->remove_menu('comments'); });
add_action('init', function () { remove_post_type_support('page', 'comments'); remove_post_type_support('post', 'comments'); }, 100);

/* A calmer dashboard for editors. */
add_action('admin_menu', function () {
    if (current_user_can('manage_options')) return;
    remove_menu_page('tools.php');
    foreach (array('hostinger', 'hostinger-ai-assistant', 'hostinger-reach') as $slug) remove_menu_page($slug);
}, 999);

add_action('wp_dashboard_setup', function () {
    global $wp_meta_boxes;
    if (!current_user_can('manage_options')) $wp_meta_boxes['dashboard'] = array();
    else {
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    }
    wp_add_dashboard_widget('aauw_welcome', 'AAUW Ann Arbor website: quick start', 'aauw_dashboard_widget');
}, 999);

function aauw_dashboard_widget() {
    $next = aauw_upcoming_programs(1, 'program');
    echo '<p style="font-size:14px">Everything on the public site is edited from the menu on the left.</p><ul style="list-style:disc;padding-left:1.4em;font-size:14px;line-height:1.7">';
    echo '<li><a href="' . esc_url(admin_url('post-new.php?post_type=program')) . '"><strong>Add a program</strong></a>: the homepage, calendar, and menu update on their own.</li>';
    echo '<li><a href="' . esc_url(admin_url('edit.php?post_type=page')) . '"><strong>Edit a page</strong></a>: find it in the list, click Edit, change the text, click Update.</li>';
    echo '<li><a href="' . esc_url(admin_url('upload.php')) . '"><strong>Add a photo or PDF</strong></a>: Media, then Add New.</li>';
    if (current_user_can('manage_aauw_settings')) echo '<li><a href="' . esc_url(admin_url('admin.php?page=aauw-settings')) . '"><strong>Site Settings</strong></a>: phone, addresses, links, and the contact form choices.</li>';
    if (current_user_can('edit_theme_options')) echo '<li><a href="' . esc_url(admin_url('nav-menus.php')) . '"><strong>Menus</strong></a>: add or move a page in the main menu.</li>';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=aauw-guide')) . '"><strong>Help and Guide</strong></a>: step-by-step instructions, answers, and a way to ask for help.</li></ul>';
    if ($next) echo '<p style="font-size:14px;background:#f0f6fc;padding:10px 12px;border-left:4px solid #246CB4">The homepage is currently featuring: <strong>' . esc_html($next[0]['title']) . '</strong> on ' . esc_html(date_i18n('l, F j', $next[0]['ts'])) . '.</p>';
    else echo '<p style="font-size:14px;background:#fcf0f1;padding:10px 12px;border-left:4px solid #C23A09"><strong>No upcoming program is entered.</strong> The homepage shows a general message until you <a href="' . esc_url(admin_url('post-new.php?post_type=program')) . '">add the next program</a>.</p>';
}

/* Help and Guide: the help section, placed last in the menu where "Theme Support" sits on the AAUW-hosted sites. */
add_action('admin_menu', function () {
    add_menu_page('Help and Guide', 'Help and Guide', 'edit_pages', 'aauw-guide', 'aauw_help_page', 'dashicons-sos', 99);
});

/* Menu order follows the AAUW-hosted sites: Dashboard, Posts, Programs, Media, Pages, ... Appearance, Profile, Site Settings, help last. */
add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', function ($order) {
    $want = array('index.php', 'edit.php', 'edit.php?post_type=program', 'upload.php', 'edit.php?post_type=page', 'edit.php?post_type=aauw_message', 'themes.php', 'profile.php', 'users.php', 'aauw-settings');
    $last = array('aauw-requests', 'aauw-guide');
    $rest = array_diff($order, $want, $last);
    return array_merge(array_intersect($want, $order), $rest, array_intersect($last, $order));
}, 99);

add_filter('admin_footer_text', function () { return 'AAUW Ann Arbor Inc. (MI) Branch website. Need help? Open Help and Guide in the left menu.'; });

/* Super Editors may save Site Settings and menus but not switch themes or edit code. */
add_action('admin_menu', function () {
    if (current_user_can('manage_options')) return;
    // Under Appearance, editors only ever need Menus.
    global $submenu;
    if (!empty($submenu['themes.php'])) foreach ($submenu['themes.php'] as $item) { if ($item[2] !== 'nav-menus.php') remove_submenu_page('themes.php', $item[2]); }
    remove_submenu_page('themes.php', 'themes.php');
    remove_submenu_page('themes.php', 'site-editor.php');
    remove_submenu_page('themes.php', 'customize.php?return=' . urlencode(remove_query_arg(wp_removable_query_args(), wp_unslash($_SERVER['REQUEST_URI']))));
}, 999);
if (!defined('DISALLOW_FILE_EDIT')) define('DISALLOW_FILE_EDIT', true);

/* On the Menus screen, keep the Program Types box in view (WordPress hides new boxes there by default). */
add_filter('get_user_option_metaboxhidden_nav-menus', function ($hidden) {
    return is_array($hidden) ? array_values(array_diff($hidden, array('add-program_type'))) : $hidden;
});

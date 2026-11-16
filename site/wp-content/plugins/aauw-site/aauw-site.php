<?php
/**
 * Plugin Name: AAUW Ann Arbor Site Features
 * Description: Programs, Site Settings, contact forms, editor roles, and dashboard help for the AAUW Ann Arbor branch website. Keep this plugin active.
 * Version: 1.0.0
 * Author: Built for AAUW Ann Arbor Inc. (MI) Branch
 */
if (!defined('ABSPATH')) exit;
define('AAUW_SITE_DIR', plugin_dir_path(__FILE__));
define('AAUW_SITE_URL', plugin_dir_url(__FILE__));

require AAUW_SITE_DIR . 'inc/settings.php';
require AAUW_SITE_DIR . 'inc/programs.php';
require AAUW_SITE_DIR . 'inc/pages.php';
require AAUW_SITE_DIR . 'inc/forms.php';
require AAUW_SITE_DIR . 'inc/editor.php';
require AAUW_SITE_DIR . 'inc/admin.php';
require AAUW_SITE_DIR . 'inc/help.php';
require AAUW_SITE_DIR . 'inc/redirects.php';
require AAUW_SITE_DIR . 'inc/cache.php';
require AAUW_SITE_DIR . 'inc/accounts.php';
require AAUW_SITE_DIR . 'inc/guards.php';
require AAUW_SITE_DIR . 'inc/extras.php';
require AAUW_SITE_DIR . 'inc/requests.php';

register_activation_hook(__FILE__, 'aauw_site_activate');
function aauw_site_activate() {
    aauw_register_program_type();
    aauw_register_roles();
    flush_rewrite_rules();
}

/** Roles: Super Editor = Editor + menus + Site Settings (mirrors AAUW National's Site Resources roles). */
function aauw_register_roles() {
    $editor = get_role('editor');
    if (!$editor) return;
    $caps = $editor->capabilities;
    $caps['edit_theme_options'] = true;
    $caps['manage_aauw_settings'] = true;
    $caps['view_koko_analytics'] = true;
    remove_role('super_editor');
    add_role('super_editor', 'Super Editor', $caps);
    $admin = get_role('administrator');
    if ($admin) $admin->add_cap('manage_aauw_settings');
}

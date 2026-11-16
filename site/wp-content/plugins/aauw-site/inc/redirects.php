<?php
if (!defined('ABSPATH')) exit;

/** Old AAUW-hosted site addresses → matching pages here. The list lives in the "aauw_redirects" option. */
add_action('template_redirect', function () {
    if (!is_404()) return;
    $map = get_option('aauw_redirects', array());
    if (!is_array($map) || !$map) return;
    $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    $path = '/' . trim((string) $path, '/') . '/';
    if (isset($map[$path])) { wp_safe_redirect(home_url($map[$path]), 301); exit; }
});

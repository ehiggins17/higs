<?php
if (!defined('ABSPATH')) exit;

/**
 * Pages must never be held by the host's CDN or a visitor's browser, or an editor's change
 * would not show up for up to a week. Images, CSS, and scripts are still cached as usual.
 */
add_filter('wp_headers', function ($headers) {
    if (is_admin()) return $headers;
    $headers['Cache-Control'] = 'no-cache, must-revalidate, max-age=0';
    $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
    // Same protective headers the Netlify version of the site sends.
    $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
    $headers['X-Content-Type-Options'] = 'nosniff';
    $headers['X-Frame-Options'] = 'SAMEORIGIN';
    $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
    return $headers;
});

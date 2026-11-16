<?php
if (!defined('ABSPATH')) exit;

/**
 * Account housekeeping.
 * - No automatic "your password was changed" or "your email was changed" notices to editors
 *   when an administrator sets up or adjusts their account. Password-reset emails that a
 *   person requests for themselves ("Lost your password?") still work.
 * - Stay logged in longer: 14 days normally, 30 days with "Remember Me" (National's site logs out quickly).
 */
add_filter('send_password_change_email', '__return_false');
add_filter('send_email_change_email', '__return_false');
add_filter('wp_send_new_user_notification_to_user', '__return_false');
add_filter('auth_cookie_expiration', function ($seconds, $user_id, $remember) {
    return $remember ? 30 * DAY_IN_SECONDS : 14 * DAY_IN_SECONDS;
}, 10, 3);

/** Set-password and reset links stay valid for 7 days instead of WordPress's default 24 hours. */
add_filter('password_reset_expiration', function () { return 7 * DAY_IN_SECONDS; });

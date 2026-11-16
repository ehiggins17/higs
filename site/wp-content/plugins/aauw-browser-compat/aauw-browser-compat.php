<?php
/**
 * Plugin Name: AAUW Browser Compatibility
 * Description: Fallback styles for older browsers (Safari and iPadOS 12 to 15, older Edge) so text sizes, spacing, image boxes and the mobile menu hold up where the theme's modern CSS is not understood. Modern browsers skip every rule.
 * Version: 1.0.0
 * Author: Eric Higgins
 * Requires at least: 6.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	$file = plugin_dir_path( __FILE__ ) . 'compat.css';
	wp_enqueue_style( 'aauw-browser-compat', plugins_url( 'compat.css', __FILE__ ), array(), file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0' );
}, 100 ); // After the theme's stylesheets.

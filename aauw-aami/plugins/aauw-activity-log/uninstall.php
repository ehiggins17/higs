<?php
// Runs only when the plugin is deleted from Plugins > Installed Plugins. Removes its tables and settings.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aauw_activity_events" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aauw_activity_sessions" );
delete_option( 'aauw_activity_log_settings' );
wp_clear_scheduled_hook( 'aauw_activity_log_cleanup' );

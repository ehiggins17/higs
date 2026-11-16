<?php
/**
 * Plugin Name: AAUW Editor Activity Log
 * Description: Records when each site editor logs in and out, how long they stay, which admin screens and pages they open, and every page they create, edit, publish, trash or upload to. Report under Users > Activity Log, with CSV export.
 * Version: 1.2.0
 * Author: Eric Higgins
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AAUW_Activity_Log {

	const VERSION        = '1.2.0';
	const OPTION         = 'aauw_activity_log_settings';
	const IDLE_MINUTES   = 30;   // A session with no activity for this long is treated as ended.
	const DEDUPE_SECONDS = 60;   // Don't log the same page for the same user twice within this window.

	private static $instance = null;
	private $events_table;
	private $sessions_table;
	private $session_cache = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->events_table   = $wpdb->prefix . 'aauw_activity_events';
		$this->sessions_table = $wpdb->prefix . 'aauw_activity_sessions';

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Sessions.
		add_action( 'set_logged_in_cookie', array( $this, 'on_login' ), 10, 6 );
		add_action( 'wp_logout', array( $this, 'on_logout' ), 10, 1 );
		add_filter( 'heartbeat_received', array( $this, 'on_heartbeat' ), 10, 2 );
		add_filter( 'heartbeat_nopriv_received', array( $this, 'on_heartbeat' ), 10, 2 );

		// Page views.
		add_action( 'admin_init', array( $this, 'on_admin_view' ), 999 );
		add_action( 'template_redirect', array( $this, 'on_front_view' ), 999 );

		// Content changes.
		add_action( 'wp_after_insert_post', array( $this, 'on_insert_post' ), 10, 4 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'on_trash' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'on_upload' ), 10, 1 );

		// Admin UI and housekeeping.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_csv' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_filter( 'all_plugins', array( $this, 'hide_from_plugins_list' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_action( 'aauw_activity_log_cleanup', array( $this, 'cleanup' ) );
	}

	/* ---------------------------------------------------------------------
	 * Setup
	 * ------------------------------------------------------------------ */

	public function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$this->sessions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			login_at DATETIME NOT NULL,
			last_seen_at DATETIME NOT NULL,
			logout_at DATETIME NULL,
			end_reason VARCHAR(16) NOT NULL DEFAULT 'open',
			ip VARCHAR(45) NOT NULL DEFAULT '',
			agent VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY session_key (session_key),
			KEY user_login (user_id, login_at)
		) $charset;" );

		dbDelta( "CREATE TABLE {$this->events_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key VARCHAR(64) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL,
			event VARCHAR(24) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			detail TEXT NULL,
			url VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_time (user_id, created_at),
			KEY session_key (session_key),
			KEY event (event)
		) $charset;" );

		$settings = get_option( self::OPTION );
		if ( ! is_array( $settings ) ) {
			$settings = array( 'exclude_users' => array(), 'retention_days' => 365 );
		}
		if ( empty( $settings['owner_id'] ) ) {
			// The site's first account (user ID 1, "admin", Eric) is the only person who can see this plugin,
			// whoever clicks Activate. Override with AAUW_ACTIVITY_LOG_OWNER in wp-config.php if that ever changes.
			$settings['owner_id'] = 1;
		}
		update_option( self::OPTION, $settings );
		if ( ! wp_next_scheduled( 'aauw_activity_log_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'aauw_activity_log_cleanup' );
		}
	}

	public function deactivate() {
		wp_clear_scheduled_hook( 'aauw_activity_log_cleanup' );
	}

	public function cleanup() {
		global $wpdb;
		$settings = $this->settings();
		$days     = max( 30, (int) $settings['retention_days'] );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->events_table} WHERE created_at < %s", $cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->sessions_table} WHERE login_at < %s", $cutoff ) );
	}

	private function settings() {
		$defaults = array( 'exclude_users' => array(), 'retention_days' => 365, 'owner_id' => 0 );
		$saved    = get_option( self::OPTION );
		return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}

	/**
	 * Only one person can see the report, the settings, the exports, or even the plugin's row on the Plugins screen:
	 * the user who activated the plugin, or the username set as AAUW_ACTIVITY_LOG_OWNER in wp-config.php.
	 */
	public function is_owner() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( defined( 'AAUW_ACTIVITY_LOG_OWNER' ) && AAUW_ACTIVITY_LOG_OWNER ) {
			return strtolower( $user->user_login ) === strtolower( (string) AAUW_ACTIVITY_LOG_OWNER )
				|| strtolower( $user->user_email ) === strtolower( (string) AAUW_ACTIVITY_LOG_OWNER );
		}
		$settings = $this->settings();
		return (int) $settings['owner_id'] > 0 && (int) $settings['owner_id'] === (int) $user->ID;
	}

	public function hide_from_plugins_list( $plugins ) {
		if ( ! $this->is_owner() ) {
			unset( $plugins[ plugin_basename( __FILE__ ) ] );
		}
		return $plugins;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private function now() {
		return current_time( 'mysql', true ); // UTC.
	}

	private function should_track( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$settings = $this->settings();
		if ( in_array( $user_id, array_map( 'intval', (array) $settings['exclude_users'] ), true ) ) {
			return false;
		}
		return user_can( $user_id, 'edit_posts' );
	}

	private function key_from_token( $token ) {
		return $token ? substr( hash( 'sha256', $token . wp_salt( 'auth' ) ), 0, 40 ) : '';
	}

	private function current_session_key() {
		if ( null !== $this->session_cache ) {
			return $this->session_cache;
		}
		$token               = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
		$this->session_cache = $this->key_from_token( $token );
		return $this->session_cache;
	}

	private function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return substr( esc_url_raw( $uri ), 0, 500 );
	}

	private function is_background_request() {
		return ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] );
	}

	private function log( $event, $detail = '', $post_id = 0, $user_id = 0, $session_key = null ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $this->should_track( $user_id ) ) {
			return;
		}
		$wpdb->insert(
			$this->events_table,
			array(
				'session_key' => null === $session_key ? $this->current_session_key() : $session_key,
				'user_id'     => $user_id,
				'event'       => substr( $event, 0, 24 ),
				'post_id'     => (int) $post_id,
				'detail'      => is_string( $detail ) ? $detail : wp_json_encode( $detail ),
				'url'         => $this->current_url(),
				'created_at'  => $this->now(),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	private function touch_session( $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$key     = $this->current_session_key();
		if ( ! $key || ! $this->should_track( $user_id ) ) {
			return;
		}
		$now     = $this->now();
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$this->sessions_table} SET last_seen_at = %s WHERE session_key = %s AND user_id = %d AND end_reason = 'open'",
			$now, $key, $user_id
		) );
		if ( ! $updated ) {
			// A session that started before the plugin was installed, or whose record was lost. Start one now.
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->sessions_table} WHERE session_key = %s", $key ) );
			if ( ! $exists ) {
				$this->open_session( $key, $user_id, 'resumed' );
			}
		}
		$this->close_stale_sessions( $user_id, $key );
	}

	private function open_session( $key, $user_id, $note = '' ) {
		global $wpdb;
		$now = $this->now();
		$wpdb->insert(
			$this->sessions_table,
			array(
				'session_key'  => $key,
				'user_id'      => (int) $user_id,
				'login_at'     => $now,
				'last_seen_at' => $now,
				'logout_at'    => null,
				'end_reason'   => 'open',
				'ip'           => isset( $_SERVER['REMOTE_ADDR'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 0, 45 ) : '',
				'agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$this->log( 'login', $note, 0, $user_id, $key );
	}

	private function close_stale_sessions( $user_id, $except_key = '' ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::IDLE_MINUTES * MINUTE_IN_SECONDS );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->sessions_table} SET end_reason = 'timeout', logout_at = last_seen_at
			 WHERE user_id = %d AND end_reason = 'open' AND last_seen_at < %s AND session_key <> %s",
			(int) $user_id, $cutoff, (string) $except_key
		) );
	}

	/* ---------------------------------------------------------------------
	 * Session hooks
	 * ------------------------------------------------------------------ */

	public function on_login( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		if ( ! $this->should_track( $user_id ) ) {
			return;
		}
		$key                 = $this->key_from_token( $token );
		$this->session_cache = $key;
		$this->close_stale_sessions( $user_id, $key );
		$this->open_session( $key, $user_id, $expire ? 'remember me' : '' );
	}

	public function on_logout( $user_id = 0 ) {
		global $wpdb;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $this->should_track( $user_id ) ) {
			return;
		}
		$key = $this->current_session_key();
		$now = $this->now();
		$done = 0;
		if ( $key ) {
			$done = $wpdb->query( $wpdb->prepare(
				"UPDATE {$this->sessions_table} SET end_reason = 'logout', logout_at = %s, last_seen_at = %s
				 WHERE session_key = %s AND user_id = %d AND end_reason = 'open'",
				$now, $now, $key, $user_id
			) );
		}
		if ( ! $done ) {
			// Cookie already gone: close the most recent open session for this user instead.
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$this->sessions_table} SET end_reason = 'logout', logout_at = %s, last_seen_at = %s
				 WHERE user_id = %d AND end_reason = 'open' ORDER BY login_at DESC LIMIT 1",
				$now, $now, $user_id
			) );
		}
		$this->log( 'logout', '', 0, $user_id, $key );
	}

	public function on_heartbeat( $response, $data ) {
		if ( is_user_logged_in() ) {
			$this->touch_session();
		}
		return $response;
	}

	/* ---------------------------------------------------------------------
	 * Page views
	 * ------------------------------------------------------------------ */

	public function on_admin_view() {
		if ( ! is_user_logged_in() || $this->is_background_request() ) {
			return;
		}
		$this->touch_session();
		$screen = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$label  = basename( isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : 'admin' );
		$title  = $label;
		if ( 'post.php' === $label && ! empty( $_GET['post'] ) ) {
			$p     = get_post( (int) $_GET['post'] );
			$title = $p ? 'Editing: ' . $p->post_title . ' (' . $p->post_type . ')' : 'Editing post #' . (int) $_GET['post'];
		} elseif ( 'post-new.php' === $label ) {
			$title = 'New ' . ( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post' );
		} elseif ( 'edit.php' === $label ) {
			$title = 'List: ' . ( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'posts' );
		} elseif ( 'upload.php' === $label ) {
			$title = 'Media library';
		} elseif ( 'index.php' === $label ) {
			$title = 'Dashboard';
		} elseif ( $screen ) {
			$title = 'Screen: ' . $screen;
		}
		$this->log_view( 'admin_view', $title );
	}

	public function on_front_view() {
		if ( ! is_user_logged_in() || $this->is_background_request() || is_admin() ) {
			return;
		}
		$this->touch_session();
		$title = wp_get_document_title();
		if ( is_singular() ) {
			$title .= ' [' . get_post_type() . ' #' . get_queried_object_id() . ']';
		} elseif ( is_404() ) {
			$title = '404: ' . $title;
		}
		$this->log_view( 'page_view', $title, is_singular() ? get_queried_object_id() : 0 );
	}

	private function log_view( $event, $title, $post_id = 0 ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$url     = $this->current_url();
		$since   = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_SECONDS );
		$dupe    = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$this->events_table} WHERE user_id = %d AND event = %s AND url = %s AND created_at > %s LIMIT 1",
			$user_id, $event, $url, $since
		) );
		if ( ! $dupe ) {
			$this->log( $event, $title, $post_id );
		}
	}

	/* ---------------------------------------------------------------------
	 * Content changes
	 * ------------------------------------------------------------------ */

	private function tracked_post( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return false;
		}
		return ! in_array( $post->post_type, array( 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' ), true );
	}

	private function words( $html ) {
		return str_word_count( wp_strip_all_tags( (string) $html ) );
	}

	public function on_insert_post( $post_id, $post, $update, $post_before ) {
		if ( $update || ! $this->tracked_post( $post ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$this->log( 'create', sprintf( 'Created %s "%s" (%s), %d words', $post->post_type, $post->post_title, $post->post_status, $this->words( $post->post_content ) ), $post_id );
	}

	public function on_post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->tracked_post( $post_after ) ) {
			return;
		}
		if ( 'auto-draft' === $post_before->post_status ) {
			return; // Handled as "create".
		}
		$changed = array();
		if ( $post_before->post_title !== $post_after->post_title ) {
			$changed[] = 'title';
		}
		if ( $post_before->post_content !== $post_after->post_content ) {
			$changed[] = sprintf( 'content (%d to %d words)', $this->words( $post_before->post_content ), $this->words( $post_after->post_content ) );
		}
		if ( $post_before->post_excerpt !== $post_after->post_excerpt ) {
			$changed[] = 'excerpt';
		}
		if ( $post_before->post_name !== $post_after->post_name ) {
			$changed[] = 'address (slug)';
		}
		if ( $post_before->post_parent !== $post_after->post_parent ) {
			$changed[] = 'parent page';
		}
		if ( $post_before->menu_order !== $post_after->menu_order ) {
			$changed[] = 'menu order';
		}
		if ( $post_before->post_status !== $post_after->post_status ) {
			$changed[] = 'status ' . $post_before->post_status . ' to ' . $post_after->post_status;
		}
		if ( ! $changed ) {
			return; // Nothing meaningful changed (e.g. a meta-only save).
		}
		$this->log( 'edit', sprintf( 'Edited %s "%s": %s', $post_after->post_type, $post_after->post_title, implode( ', ', $changed ) ), $post_id );
	}

	public function on_status_change( $new_status, $old_status, $post ) {
		if ( ! $this->tracked_post( $post ) || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			$this->log( 'publish', sprintf( 'Published %s "%s"', $post->post_type, $post->post_title ), $post->ID );
		} elseif ( 'publish' === $old_status && 'trash' !== $new_status ) {
			$this->log( 'unpublish', sprintf( 'Unpublished %s "%s" (now %s)', $post->post_type, $post->post_title, $new_status ), $post->ID );
		}
	}

	public function on_trash( $post_id ) {
		$post = get_post( $post_id );
		if ( $this->tracked_post( $post ) ) {
			$this->log( 'trash', sprintf( 'Moved %s "%s" to trash', $post->post_type, $post->post_title ), $post_id );
		}
	}

	public function on_delete( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( $this->tracked_post( $post ) ) {
			$this->log( 'delete', sprintf( 'Permanently deleted %s "%s"', $post->post_type, $post->post_title ), $post_id );
		}
	}

	public function on_upload( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			$file = get_attached_file( $post_id );
			$this->log( 'upload', sprintf( 'Uploaded "%s" (%s)', $post->post_title, $file ? basename( $file ) : $post->post_mime_type ), $post_id );
		}
	}

	/* ---------------------------------------------------------------------
	 * Private read-only API, for the owner account only (Application Password over HTTPS).
	 *   GET /wp-json/aauw-activity/v1/status                      who is logged in now, last login per editor
	 *   GET /wp-json/aauw-activity/v1/report?from=YYYY-MM-DD&to=YYYY-MM-DD&user=ID&limit=N
	 * ------------------------------------------------------------------ */

	public function register_rest() {
		register_rest_route( 'aauw-activity/v1', '/status', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_status' ),
			'permission_callback' => array( $this, 'is_owner' ),
		) );
		register_rest_route( 'aauw-activity/v1', '/report', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_report' ),
			'permission_callback' => array( $this, 'is_owner' ),
			'args'                => array(
				'from'  => array( 'type' => 'string', 'required' => false ),
				'to'    => array( 'type' => 'string', 'required' => false ),
				'user'  => array( 'type' => 'integer', 'required' => false, 'default' => 0 ),
				'limit' => array( 'type' => 'integer', 'required' => false, 'default' => 500 ),
			),
		) );
	}

	private function session_row( $s ) {
		$u      = get_userdata( $s->user_id );
		$end    = $this->effective_end( $s );
		$end_ts = $end ? strtotime( $end . ' UTC' ) : time();
		return array(
			'session'       => substr( $s->session_key, 0, 8 ),
			'user_id'       => (int) $s->user_id,
			'user'          => $u ? $u->display_name : 'User #' . $s->user_id,
			'username'      => $u ? $u->user_login : '',
			'login_at'      => $this->fmt_local( $s->login_at ),
			'last_activity' => $this->fmt_local( $s->last_seen_at ),
			'logout_at'     => $end ? $this->fmt_local( $end ) : null,
			'minutes'       => round( ( $end_ts - strtotime( $s->login_at . ' UTC' ) ) / 60, 1 ),
			'ended_by'      => $end ? ( 'logout' === $s->end_reason ? 'logout' : 'timeout' ) : 'still active',
			'login_at_utc'  => $s->login_at,
		);
	}

	public function rest_status( $request ) {
		global $wpdb;
		$editors = get_users( array( 'capability' => 'edit_posts', 'orderby' => 'display_name' ) );
		$out     = array( 'site_time' => wp_date( 'M j, Y g:i a T' ), 'editors' => array(), 'active_now' => array() );
		foreach ( $editors as $ed ) {
			$last = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->sessions_table} WHERE user_id = %d ORDER BY login_at DESC LIMIT 1", $ed->ID ) );
			$n    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->sessions_table} WHERE user_id = %d", $ed->ID ) );
			$edits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->events_table} WHERE user_id = %d AND event IN ('create','edit','publish','upload')", $ed->ID ) );
			$out['editors'][] = array(
				'user_id'      => (int) $ed->ID,
				'user'         => $ed->display_name,
				'username'     => $ed->user_login,
				'total_logins' => $n,
				'content_changes_total' => $edits,
				'last_session' => $last ? $this->session_row( $last ) : null,
			);
			if ( $last && null === $this->effective_end( $last ) ) {
				$out['active_now'][] = $ed->display_name;
			}
		}
		return rest_ensure_response( $out );
	}

	public function rest_report( $request ) {
		$from = $request->get_param( 'from' );
		$to   = $request->get_param( 'to' );
		$from = ( $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) ? $from : gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$to   = ( $to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) ? $to : gmdate( 'Y-m-d' );
		$user = (int) $request->get_param( 'user' );
		$lim  = min( 5000, max( 1, (int) $request->get_param( 'limit' ) ) );
		$sessions = $this->query_sessions( $user, $from . ' 00:00:00', $to . ' 23:59:59' );
		$events   = $this->query_events( $user, $from . ' 00:00:00', $to . ' 23:59:59', $lim );
		$summary  = array();
		foreach ( $this->summarize( $sessions, $this->query_events( $user, $from . ' 00:00:00', $to . ' 23:59:59', 100000 ) ) as $uid => $row ) {
			$u = get_userdata( $uid );
			$row['user']       = $u ? $u->display_name : 'User #' . $uid;
			$row['user_id']    = (int) $uid;
			$row['minutes']    = round( $row['seconds'] / 60, 1 );
			$row['last_login'] = $this->fmt_local( $row['last_login'] );
			unset( $row['seconds'] );
			$summary[] = $row;
		}
		$ev = array();
		foreach ( $events as $e ) {
			$u    = get_userdata( $e->user_id );
			$ev[] = array(
				'when'    => $this->fmt_local( $e->created_at ),
				'user'    => $u ? $u->display_name : 'User #' . $e->user_id,
				'event'   => $e->event,
				'detail'  => $e->detail,
				'url'     => $e->url,
				'post_id' => (int) $e->post_id,
				'session' => substr( $e->session_key, 0, 8 ),
			);
		}
		return rest_ensure_response( array(
			'from'     => $from,
			'to'       => $to,
			'summary'  => $summary,
			'sessions' => array_map( array( $this, 'session_row' ), $sessions ),
			'events'   => $ev,
		) );
	}

	/* ---------------------------------------------------------------------
	 * Admin report
	 * ------------------------------------------------------------------ */

	public function admin_menu() {
		if ( $this->is_owner() ) {
			add_users_page( 'Activity Log', 'Activity Log', 'read', 'aauw-activity-log', array( $this, 'render_page' ) );
		}
	}

	private function fmt_local( $utc ) {
		if ( ! $utc ) {
			return '';
		}
		return wp_date( 'M j, Y g:i a', strtotime( $utc . ' UTC' ) );
	}

	private function fmt_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( $seconds < 60 ) {
			return $seconds . ' sec';
		}
		$h = floor( $seconds / 3600 );
		$m = floor( ( $seconds % 3600 ) / 60 );
		return $h ? sprintf( '%dh %02dm', $h, $m ) : sprintf( '%d min', $m );
	}

	private function effective_end( $row ) {
		if ( $row->logout_at ) {
			return $row->logout_at;
		}
		$idle = time() - strtotime( $row->last_seen_at . ' UTC' );
		return $idle > self::IDLE_MINUTES * MINUTE_IN_SECONDS ? $row->last_seen_at : null; // null = still active.
	}

	private function filters() {
		$user = isset( $_GET['user'] ) ? (int) $_GET['user'] : 0;
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : gmdate( 'Y-m-d' );
		$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ? $to : gmdate( 'Y-m-d' );
		return array( $user, $from . ' 00:00:00', $to . ' 23:59:59', $from, $to );
	}

	private function query_sessions( $user, $from, $to ) {
		global $wpdb;
		$sql = "SELECT * FROM {$this->sessions_table} WHERE login_at BETWEEN %s AND %s";
		$args = array( $from, $to );
		if ( $user ) {
			$sql   .= ' AND user_id = %d';
			$args[] = $user;
		}
		$sql .= ' ORDER BY login_at DESC LIMIT 1000';
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	private function query_events( $user, $from, $to, $limit = 500 ) {
		global $wpdb;
		$sql  = "SELECT * FROM {$this->events_table} WHERE created_at BETWEEN %s AND %s";
		$args = array( $from, $to );
		if ( $user ) {
			$sql   .= ' AND user_id = %d';
			$args[] = $user;
		}
		$sql   .= ' ORDER BY created_at DESC LIMIT %d';
		$args[] = (int) $limit;
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	private function summarize( $sessions, $events ) {
		$by = array();
		foreach ( $sessions as $s ) {
			$u = (int) $s->user_id;
			if ( ! isset( $by[ $u ] ) ) {
				$by[ $u ] = array( 'sessions' => 0, 'seconds' => 0, 'last_login' => '', 'admin_view' => 0, 'page_view' => 0, 'create' => 0, 'edit' => 0, 'publish' => 0, 'upload' => 0, 'trash' => 0, 'delete' => 0 );
			}
			$end = $this->effective_end( $s );
			$end_ts = $end ? strtotime( $end . ' UTC' ) : time();
			$by[ $u ]['sessions']++;
			$by[ $u ]['seconds'] += max( 0, $end_ts - strtotime( $s->login_at . ' UTC' ) );
			if ( $s->login_at > $by[ $u ]['last_login'] ) {
				$by[ $u ]['last_login'] = $s->login_at;
			}
		}
		foreach ( $events as $e ) {
			$u = (int) $e->user_id;
			if ( ! isset( $by[ $u ] ) ) {
				$by[ $u ] = array( 'sessions' => 0, 'seconds' => 0, 'last_login' => '', 'admin_view' => 0, 'page_view' => 0, 'create' => 0, 'edit' => 0, 'publish' => 0, 'upload' => 0, 'trash' => 0, 'delete' => 0 );
			}
			if ( isset( $by[ $u ][ $e->event ] ) ) {
				$by[ $u ][ $e->event ]++;
			}
		}
		return $by;
	}

	public function render_page() {
		if ( ! $this->is_owner() ) {
			wp_die( 'You do not have permission to view this page.' );
		}
		list( $user, $from, $to, $from_d, $to_d ) = $this->filters();
		$sessions = $this->query_sessions( $user, $from, $to );
		$events   = $this->query_events( $user, $from, $to, 5000 );
		$summary  = $this->summarize( $sessions, $events );
		$settings = $this->settings();
		$editors  = get_users( array( 'capability' => 'edit_posts', 'orderby' => 'display_name' ) );
		$base     = admin_url( 'users.php?page=aauw-activity-log' );
		$export   = wp_nonce_url( add_query_arg( array( 'user' => $user, 'from' => $from_d, 'to' => $to_d ), $base ), 'aauw_export' );
		?>
		<div class="wrap">
			<h1>Editor Activity Log</h1>
			<p>Logins, time on the site, screens and pages opened, and every page created, edited, published, trashed or uploaded. Times are shown in the site's time zone (<?php echo esc_html( wp_timezone_string() ); ?>). A session with no activity for <?php echo (int) self::IDLE_MINUTES; ?> minutes is counted as ended at its last activity.</p>

			<form method="get" style="margin:1em 0;padding:12px;background:#fff;border:1px solid #c3c4c7;display:flex;gap:12px;align-items:end;flex-wrap:wrap">
				<input type="hidden" name="page" value="aauw-activity-log">
				<label>Editor<br><select name="user"><option value="0">Everyone</option>
					<?php foreach ( $editors as $ed ) : ?>
						<option value="<?php echo (int) $ed->ID; ?>" <?php selected( $user, $ed->ID ); ?>><?php echo esc_html( $ed->display_name ); ?></option>
					<?php endforeach; ?>
				</select></label>
				<label>From<br><input type="date" name="from" value="<?php echo esc_attr( $from_d ); ?>"></label>
				<label>To<br><input type="date" name="to" value="<?php echo esc_attr( $to_d ); ?>"></label>
				<button class="button button-primary">Show</button>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'aauw_export', 'sessions', $export ) ); ?>">Export sessions (CSV)</a>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'aauw_export', 'events', $export ) ); ?>">Export events (CSV)</a>
			</form>

			<h2>Summary</h2>
			<table class="widefat striped">
				<thead><tr><th>Editor</th><th>Logins</th><th>Time logged in</th><th>Avg session</th><th>Last login</th><th>Admin screens</th><th>Site pages viewed</th><th>Pages created</th><th>Edits</th><th>Published</th><th>Uploads</th><th>Trashed</th></tr></thead>
				<tbody>
				<?php if ( ! $summary ) : ?>
					<tr><td colspan="12">Nothing recorded for this period yet.</td></tr>
				<?php endif; ?>
				<?php foreach ( $summary as $uid => $row ) : $u = get_userdata( $uid ); ?>
					<tr>
						<td><strong><?php echo esc_html( $u ? $u->display_name : 'User #' . $uid ); ?></strong></td>
						<td><?php echo (int) $row['sessions']; ?></td>
						<td><?php echo esc_html( $this->fmt_duration( $row['seconds'] ) ); ?></td>
						<td><?php echo esc_html( $row['sessions'] ? $this->fmt_duration( $row['seconds'] / $row['sessions'] ) : '' ); ?></td>
						<td><?php echo esc_html( $this->fmt_local( $row['last_login'] ) ); ?></td>
						<td><?php echo (int) $row['admin_view']; ?></td>
						<td><?php echo (int) $row['page_view']; ?></td>
						<td><?php echo (int) $row['create']; ?></td>
						<td><?php echo (int) $row['edit']; ?></td>
						<td><?php echo (int) $row['publish']; ?></td>
						<td><?php echo (int) $row['upload']; ?></td>
						<td><?php echo (int) ( $row['trash'] + $row['delete'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:2em">Sessions</h2>
			<table class="widefat striped">
				<thead><tr><th>Editor</th><th>Logged in</th><th>Last activity</th><th>Logged out</th><th>Duration</th><th>Ended by</th><th>Activity in this session</th></tr></thead>
				<tbody>
				<?php if ( ! $sessions ) : ?>
					<tr><td colspan="7">No sessions in this period.</td></tr>
				<?php endif; ?>
				<?php
				$counts = array();
				foreach ( $events as $e ) {
					if ( ! isset( $counts[ $e->session_key ] ) ) {
						$counts[ $e->session_key ] = array();
					}
					$counts[ $e->session_key ][ $e->event ] = isset( $counts[ $e->session_key ][ $e->event ] ) ? $counts[ $e->session_key ][ $e->event ] + 1 : 1;
				}
				foreach ( $sessions as $s ) :
					$u      = get_userdata( $s->user_id );
					$end    = $this->effective_end( $s );
					$end_ts = $end ? strtotime( $end . ' UTC' ) : time();
					$dur    = $end_ts - strtotime( $s->login_at . ' UTC' );
					$reason = $end ? ( 'logout' === $s->end_reason ? 'Logged out' : 'Timed out (idle)' ) : 'Still active';
					$parts  = array();
					if ( isset( $counts[ $s->session_key ] ) ) {
						foreach ( $counts[ $s->session_key ] as $ev => $n ) {
							if ( in_array( $ev, array( 'login', 'logout' ), true ) ) {
								continue;
							}
							$parts[] = $n . ' ' . str_replace( '_', ' ', $ev ) . ( $n > 1 ? 's' : '' );
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $u ? $u->display_name : 'User #' . $s->user_id ); ?></td>
						<td><?php echo esc_html( $this->fmt_local( $s->login_at ) ); ?></td>
						<td><?php echo esc_html( $this->fmt_local( $s->last_seen_at ) ); ?></td>
						<td><?php echo esc_html( $end ? $this->fmt_local( $end ) : '' ); ?></td>
						<td><?php echo esc_html( $this->fmt_duration( $dur ) ); ?></td>
						<td><?php echo esc_html( $reason ); ?></td>
						<td><?php echo esc_html( $parts ? implode( ', ', $parts ) : 'no activity recorded' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:2em">Events <span style="font-weight:normal;color:#646970">(most recent 500 shown; export for all)</span></h2>
			<table class="widefat striped">
				<thead><tr><th>When</th><th>Editor</th><th>What</th><th>Detail</th><th>Address</th></tr></thead>
				<tbody>
				<?php if ( ! $events ) : ?>
					<tr><td colspan="5">No events in this period.</td></tr>
				<?php endif; ?>
				<?php foreach ( array_slice( $events, 0, 500 ) as $e ) : $u = get_userdata( $e->user_id ); ?>
					<tr>
						<td style="white-space:nowrap"><?php echo esc_html( $this->fmt_local( $e->created_at ) ); ?></td>
						<td><?php echo esc_html( $u ? $u->display_name : 'User #' . $e->user_id ); ?></td>
						<td><?php echo esc_html( str_replace( '_', ' ', $e->event ) ); ?></td>
						<td><?php echo esc_html( $e->detail ); ?></td>
						<td style="max-width:320px;overflow-wrap:anywhere"><code><?php echo esc_html( $e->url ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:2em">Settings</h2>
			<form method="post">
				<?php wp_nonce_field( 'aauw_activity_settings' ); ?>
				<table class="form-table">
					<tr><th>Don't log these users</th><td>
						<?php foreach ( $editors as $ed ) : ?>
							<label style="display:block"><input type="checkbox" name="aauw_exclude[]" value="<?php echo (int) $ed->ID; ?>" <?php checked( in_array( $ed->ID, array_map( 'intval', (array) $settings['exclude_users'] ), true ) ); ?>> <?php echo esc_html( $ed->display_name ); ?> (<?php echo esc_html( $ed->user_login ); ?>)</label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th>Keep records for</th><td><input type="number" min="30" name="aauw_retention" value="<?php echo (int) $settings['retention_days']; ?>"> days</td></tr>
				</table>
				<p><button class="button" name="aauw_save_settings" value="1">Save settings</button></p>
			</form>
			<p style="color:#646970">Good practice: tell every editor this log exists and what it records. It records activity by logged-in editors only, never by site visitors.</p>
		</div>
		<?php
	}

	public function maybe_save_settings() {
		if ( empty( $_POST['aauw_save_settings'] ) || ! $this->is_owner() ) {
			return;
		}
		check_admin_referer( 'aauw_activity_settings' );
		$exclude   = isset( $_POST['aauw_exclude'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['aauw_exclude'] ) ) : array();
		$retention = isset( $_POST['aauw_retention'] ) ? max( 30, (int) $_POST['aauw_retention'] ) : 365;
		$current = $this->settings();
		update_option( self::OPTION, array( 'exclude_users' => $exclude, 'retention_days' => $retention, 'owner_id' => (int) $current['owner_id'] ) );
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>Activity log settings saved.</p></div>';
		} );
	}

	public function maybe_export_csv() {
		if ( empty( $_GET['aauw_export'] ) || empty( $_GET['page'] ) || 'aauw-activity-log' !== $_GET['page'] ) {
			return;
		}
		if ( ! $this->is_owner() ) {
			return;
		}
		check_admin_referer( 'aauw_export' );
		list( $user, $from, $to, $from_d, $to_d ) = $this->filters();
		$type = sanitize_key( wp_unslash( $_GET['aauw_export'] ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="aauw-activity-' . $type . '-' . $from_d . '-to-' . $to_d . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( 'sessions' === $type ) {
			fputcsv( $out, array( 'Editor', 'Username', 'Logged in', 'Last activity', 'Logged out', 'Duration (minutes)', 'Ended by', 'IP', 'Browser' ) );
			foreach ( $this->query_sessions( $user, $from, $to ) as $s ) {
				$u      = get_userdata( $s->user_id );
				$end    = $this->effective_end( $s );
				$end_ts = $end ? strtotime( $end . ' UTC' ) : time();
				fputcsv( $out, array(
					$u ? $u->display_name : $s->user_id,
					$u ? $u->user_login : '',
					$this->fmt_local( $s->login_at ),
					$this->fmt_local( $s->last_seen_at ),
					$end ? $this->fmt_local( $end ) : '',
					round( ( $end_ts - strtotime( $s->login_at . ' UTC' ) ) / 60, 1 ),
					$end ? ( 'logout' === $s->end_reason ? 'Logged out' : 'Timed out' ) : 'Still active',
					$s->ip,
					$s->agent,
				) );
			}
		} else {
			fputcsv( $out, array( 'When', 'Editor', 'Username', 'Event', 'Detail', 'Address', 'Post ID', 'Session' ) );
			foreach ( $this->query_events( $user, $from, $to, 100000 ) as $e ) {
				$u = get_userdata( $e->user_id );
				fputcsv( $out, array(
					$this->fmt_local( $e->created_at ),
					$u ? $u->display_name : $e->user_id,
					$u ? $u->user_login : '',
					$e->event,
					$e->detail,
					$e->url,
					$e->post_id,
					substr( $e->session_key, 0, 8 ),
				) );
			}
		}
		fclose( $out );
		exit;
	}
}

AAUW_Activity_Log::instance();

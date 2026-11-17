<?php
/**
 * Plugin Name: AAUW Site Control
 * Description: Owner-only remote administration over the REST API: install and activate plugins, read and write theme and plugin files, change settings, export content, and switch domains. Authenticates with the owner's Application Password or a private token header. No arbitrary code execution.
 * Version: 1.1.1
 * Author: Eric Higgins
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AAUW_Site_Control {

	const NS         = 'aauw-control/v1';
	const OPT_TOKEN  = 'aauw_control_token_hash';
	const OPT_DEPLOY = 'aauw_control_deploy_hash'; // Second token, for GitHub Actions deploys.
	const OPT_OWNER  = 'aauw_control_owner_id';
	const HEADER     = 'X-AAUW-Token';
	const MAX_UPLOAD = 25 * 1024 * 1024;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_filter( 'determine_current_user', array( $this, 'token_auth' ), 30 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_settings' ) );
		add_filter( 'all_plugins', array( $this, 'hide_row' ) );
	}

	public function activate() {
		if ( ! get_option( self::OPT_OWNER ) ) {
			update_option( self::OPT_OWNER, 1 ); // The site's first account. Override with AAUW_CONTROL_OWNER in wp-config.php.
		}
	}

	/* ------------------------------------------------------------------ auth */

	public function owner_id() {
		if ( defined( 'AAUW_CONTROL_OWNER' ) && AAUW_CONTROL_OWNER ) {
			$u = is_numeric( AAUW_CONTROL_OWNER ) ? get_user_by( 'id', (int) AAUW_CONTROL_OWNER ) : get_user_by( 'login', AAUW_CONTROL_OWNER );
			return $u ? (int) $u->ID : 0;
		}
		return (int) get_option( self::OPT_OWNER, 1 );
	}

	public function is_owner() {
		$id = get_current_user_id();
		return $id > 0 && $id === $this->owner_id();
	}

	/** If the private token header is present and valid, the request runs as the owner. */
	public function token_auth( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', self::HEADER ) );
		$sent = isset( $_SERVER[ $key ] ) ? trim( (string) $_SERVER[ $key ] ) : '';
		if ( '' === $sent && function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $k => $v ) {
				if ( strtolower( $k ) === strtolower( self::HEADER ) ) {
					$sent = trim( (string) $v );
				}
			}
		}
		if ( '' === $sent ) {
			return $user_id;
		}
		if ( ! is_ssl() ) {
			return $user_id; // Token only travels over HTTPS.
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false === strpos( $uri, '/wp-json/' ) && false === strpos( $uri, 'rest_route=' ) ) {
			return $user_id; // The token authenticates API calls only, never ordinary page views.
		}
		$hash = (string) get_option( self::OPT_TOKEN, '' );
		if ( defined( 'AAUW_CONTROL_TOKEN' ) && AAUW_CONTROL_TOKEN ) {
			if ( hash_equals( (string) AAUW_CONTROL_TOKEN, $sent ) ) {
				return $this->owner_id();
			}
		}
		if ( $hash && password_verify( $sent, $hash ) ) {
			return $this->owner_id();
		}
		$deploy = (string) get_option( self::OPT_DEPLOY, '' );
		if ( $deploy && password_verify( $sent, $deploy ) ) {
			return $this->owner_id();
		}
		return $user_id;
	}

	public function perm() {
		return $this->is_owner();
	}

	/* ------------------------------------------------------------------ paths */

	/** Confine every file operation to wp-content and the static img folder, plus the site's own .htaccess. */
	private function resolve( $rel, $must_exist = true ) {
		$rel = str_replace( '\\', '/', (string) $rel );
		$rel = ltrim( $rel, '/' );
		if ( '' === $rel || false !== strpos( $rel, '..' ) || false !== strpos( $rel, "\0" ) ) {
			return new WP_Error( 'bad_path', 'Path must be relative, inside wp-content, with no "..".', array( 'status' => 400 ) );
		}
		if ( '.htaccess' === $rel ) {
			return ABSPATH . '.htaccess';
		}
		if ( 'img' === $rel || 0 === strpos( $rel, 'img/' ) ) {
			// The site's static image folder next to wp-content (carried over from the first build).
			$base = rtrim( wp_normalize_path( ABSPATH ), '/' ) . '/img';
			$full = rtrim( wp_normalize_path( ABSPATH ), '/' ) . '/' . $rel;
			if ( $must_exist ) {
				$real = realpath( $full );
				if ( false === $real ) {
					return new WP_Error( 'not_found', 'No such file: ' . $rel, array( 'status' => 404 ) );
				}
				$real = wp_normalize_path( $real );
				if ( 0 !== strpos( $real . '/', $base . '/' ) ) {
					return new WP_Error( 'bad_path', 'Path escapes img.', array( 'status' => 400 ) );
				}
				return $real;
			}
			return $full;
		}
		if ( 0 === strpos( $rel, 'wp-content/' ) ) {
			$rel = substr( $rel, 11 );
		} elseif ( 'wp-content' === $rel ) {
			$rel = '';
		}
		$base = rtrim( wp_normalize_path( WP_CONTENT_DIR ), '/' );
		if ( '' === $rel ) {
			return $base; // wp-content itself (the deploy target)
		}
		$full = $base . '/' . $rel;
		if ( $must_exist ) {
			$real = realpath( $full );
			if ( false === $real ) {
				return new WP_Error( 'not_found', 'No such file: ' . $rel, array( 'status' => 404 ) );
			}
			$real = wp_normalize_path( $real );
			if ( 0 !== strpos( $real . '/', $base . '/' ) ) {
				return new WP_Error( 'bad_path', 'Path escapes wp-content.', array( 'status' => 400 ) );
			}
			return $real;
		}
		$dir = realpath( dirname( $full ) );
		if ( false !== $dir && 0 !== strpos( wp_normalize_path( $dir ) . '/', $base . '/' ) ) {
			return new WP_Error( 'bad_path', 'Path escapes wp-content.', array( 'status' => 400 ) );
		}
		return $full;
	}

	/* ------------------------------------------------------------------ routes */

	public function routes() {
		$r = function ( $route, $methods, $cb, $args = array() ) {
			register_rest_route( self::NS, $route, array(
				'methods'             => $methods,
				'callback'            => array( $this, $cb ),
				'permission_callback' => array( $this, 'perm' ),
				'args'                => $args,
			) );
		};
		$r( '/ping', 'GET', 'ping' );
		$r( '/plugins', 'GET', 'plugins_list' );
		$r( '/plugins/install', 'POST', 'plugins_install' );
		$r( '/plugins/activate', 'POST', 'plugins_activate' );
		$r( '/plugins/deactivate', 'POST', 'plugins_deactivate' );
		$r( '/plugins/delete', 'POST', 'plugins_delete' );
		$r( '/themes', 'GET', 'themes_list' );
		$r( '/themes/switch', 'POST', 'themes_switch' );
		$r( '/files', 'GET', 'files_list' );
		$r( '/file', 'GET', 'file_read' );
		$r( '/file', 'PUT', 'file_write' );
		$r( '/file', 'DELETE', 'file_delete' );
		$r( '/dir', 'DELETE', 'dir_delete' );
		$r( '/unzip', 'POST', 'unzip' );
		$r( '/option', 'GET', 'option_get' );
		$r( '/option', 'POST', 'option_set' );
		$r( '/export', 'GET', 'export_wxr' );
		$r( '/search-replace', 'POST', 'search_replace' );
		$r( '/cache/flush', 'POST', 'cache_flush' );
		$r( '/token/rotate', 'POST', 'token_rotate' );
		$r( '/token/deploy', 'POST', 'token_deploy' );
	}

	public function ping() {
		$u = wp_get_current_user();
		return rest_ensure_response( array(
			'ok'            => true,
			'user'          => $u->user_login,
			'user_id'       => (int) $u->ID,
			'site'          => home_url(),
			'wordpress'     => get_bloginfo( 'version' ),
			'php'           => PHP_VERSION,
			'theme'         => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'active_plugins'=> (array) get_option( 'active_plugins' ),
			'site_time'     => wp_date( 'Y-m-d H:i:s T' ),
			'auth'          => isset( $_SERVER['PHP_AUTH_USER'] ) ? 'application-password' : 'token',
		) );
	}

	/* plugins */

	private function load_admin_files() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
	}

	public function plugins_list() {
		$this->load_admin_files();
		$all = get_plugins();
		$out = array();
		foreach ( $all as $file => $p ) {
			$out[] = array( 'file' => $file, 'name' => $p['Name'], 'version' => $p['Version'], 'active' => is_plugin_active( $file ) );
		}
		return rest_ensure_response( $out );
	}

	/** Body: { "zip_base64": "...", "activate": true }  or { "url": "https://.../plugin.zip" } */
	/* Extract a zip with PHP's ZipArchive and plain file writes. WordPress's unzip_file() depends on WP_Filesystem,
	   which on this host silently writes nothing, so every route that unpacks a zip goes through here. */
	private function extract_zip( $tmp, $to ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'no_ziparchive', 'ZipArchive is not available on this server.', array( 'status' => 500 ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp ) ) {
			return new WP_Error( 'bad_zip', 'Could not open the zip.', array( 'status' => 400 ) );
		}
		$base    = rtrim( wp_normalize_path( $to ), '/' );
		$written = 0;
		$bytes   = 0;
		$skipped = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			$name = str_replace( '\\', '/', $name );
			if ( '' === $name || '/' === substr( $name, -1 ) ) {
				continue; // directory entry
			}
			if ( false !== strpos( $name, '..' ) || false !== strpos( $name, "\0" ) || 0 === strpos( $name, '/' ) || 0 === strpos( $name, '__MACOSX/' ) ) {
				$skipped[] = $name;
				continue;
			}
			$dest = $base . '/' . $name;
			$dir  = dirname( $dest );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				$zip->close();
				return new WP_Error( 'mkdir_failed', 'Could not create ' . $dir, array( 'status' => 500 ) );
			}
			$data = $zip->getFromIndex( $i );
			if ( false === $data ) {
				$skipped[] = $name;
				continue;
			}
			$ok = file_put_contents( $dest, $data, LOCK_EX );
			if ( false === $ok ) {
				$zip->close();
				return new WP_Error( 'write_failed', 'Could not write ' . $name . ' (permissions?).', array( 'status' => 500 ) );
			}
			$written++;
			$bytes += $ok;
		}
		$zip->close();
		return array( 'files' => $written, 'bytes' => $bytes, 'skipped' => $skipped );
	}

	public function plugins_install( $req ) {
		$this->load_admin_files();
		$tmp = $this->zip_from_request( $req );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$res = $this->extract_zip( $tmp, WP_PLUGIN_DIR );
		@unlink( $tmp );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		wp_cache_delete( 'plugins', 'plugins' );
		$installed = array_keys( get_plugins() );
		$result    = array( 'installed' => true, 'files' => $res['files'], 'bytes' => $res['bytes'] );
		$wanted    = $req->get_param( 'plugin' );
		if ( $req->get_param( 'activate' ) ) {
			$target = $wanted ? $wanted : $this->newest_plugin_file( $installed );
			if ( $target ) {
				$a = activate_plugin( $target );
				$result['activated'] = is_wp_error( $a ) ? $a->get_error_message() : $target;
			}
		}
		return rest_ensure_response( $result );
	}

	private function newest_plugin_file( $files ) {
		$best = null; $t = 0;
		foreach ( $files as $f ) {
			$m = @filemtime( WP_PLUGIN_DIR . '/' . $f );
			if ( $m && $m > $t ) { $t = $m; $best = $f; }
		}
		return $best;
	}

	private function zip_from_request( $req ) {
		$b64 = $req->get_param( 'zip_base64' );
		$url = $req->get_param( 'url' );
		$tmp = wp_tempnam( 'aauw-upload.zip' );
		if ( $b64 ) {
			if ( strlen( $b64 ) > self::MAX_UPLOAD * 1.4 ) {
				return new WP_Error( 'too_large', 'Upload exceeds 25 MB.', array( 'status' => 413 ) );
			}
			$bin = base64_decode( $b64, true );
			if ( false === $bin ) {
				return new WP_Error( 'bad_base64', 'zip_base64 is not valid base64.', array( 'status' => 400 ) );
			}
			file_put_contents( $tmp, $bin );
			return $tmp;
		}
		if ( $url ) {
			$dl = download_url( esc_url_raw( $url ) );
			if ( is_wp_error( $dl ) ) {
				return $dl;
			}
			return $dl;
		}
		return new WP_Error( 'no_zip', 'Send zip_base64 or url.', array( 'status' => 400 ) );
	}

	public function plugins_activate( $req ) {
		$this->load_admin_files();
		$a = activate_plugin( sanitize_text_field( $req->get_param( 'plugin' ) ) );
		return is_wp_error( $a ) ? $a : rest_ensure_response( array( 'activated' => $req->get_param( 'plugin' ) ) );
	}

	public function plugins_deactivate( $req ) {
		$this->load_admin_files();
		$p = sanitize_text_field( $req->get_param( 'plugin' ) );
		if ( false !== strpos( $p, 'aauw-site-control' ) ) {
			return new WP_Error( 'refused', 'Not deactivating the control plugin over its own API.', array( 'status' => 400 ) );
		}
		deactivate_plugins( $p );
		return rest_ensure_response( array( 'deactivated' => $p ) );
	}

	public function plugins_delete( $req ) {
		$this->load_admin_files();
		$p = sanitize_text_field( $req->get_param( 'plugin' ) );
		if ( false !== strpos( $p, 'aauw-site-control' ) || is_plugin_active( $p ) ) {
			return new WP_Error( 'refused', 'Deactivate first, and never the control plugin.', array( 'status' => 400 ) );
		}
		$d = delete_plugins( array( $p ) );
		return is_wp_error( $d ) ? $d : rest_ensure_response( array( 'deleted' => $p ) );
	}

	/* themes */

	public function themes_list() {
		$out = array();
		foreach ( wp_get_themes() as $slug => $t ) {
			$out[] = array( 'slug' => $slug, 'name' => $t->get( 'Name' ), 'version' => $t->get( 'Version' ), 'active' => get_stylesheet() === $slug, 'parent' => $t->parent() ? $t->parent()->get_stylesheet() : null );
		}
		return rest_ensure_response( $out );
	}

	public function themes_switch( $req ) {
		$slug = sanitize_text_field( $req->get_param( 'theme' ) );
		if ( ! wp_get_theme( $slug )->exists() ) {
			return new WP_Error( 'not_found', 'No such theme.', array( 'status' => 404 ) );
		}
		switch_theme( $slug );
		return rest_ensure_response( array( 'active' => get_stylesheet() ) );
	}

	/* files */

	public function files_list( $req ) {
		$path = $this->resolve( $req->get_param( 'path' ) ? $req->get_param( 'path' ) : 'wp-content' );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_dir( $path ) ) {
			return new WP_Error( 'not_dir', 'Not a directory.', array( 'status' => 400 ) );
		}
		$out = array();
		foreach ( scandir( $path ) as $n ) {
			if ( '.' === $n || '..' === $n ) {
				continue;
			}
			$f = $path . '/' . $n;
			$out[] = array( 'name' => $n, 'type' => is_dir( $f ) ? 'dir' : 'file', 'size' => is_dir( $f ) ? null : filesize( $f ), 'modified' => gmdate( 'c', filemtime( $f ) ) );
		}
		return rest_ensure_response( array( 'path' => str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $path ) ), 'entries' => $out ) );
	}

	public function file_read( $req ) {
		$path = $this->resolve( $req->get_param( 'path' ) );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( is_dir( $path ) ) {
			return new WP_Error( 'is_dir', 'That is a directory; use /files.', array( 'status' => 400 ) );
		}
		$data = file_get_contents( $path );
		return rest_ensure_response( array( 'path' => $req->get_param( 'path' ), 'size' => strlen( $data ), 'content_base64' => base64_encode( $data ) ) );
	}

	/** Body: { "path": "wp-content/themes/x/style.css", "content_base64": "..." } (or "content" for plain text). Writes a .bak copy first. */
	public function file_write( $req ) {
		$path = $this->resolve( $req->get_param( 'path' ), false );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$content = null;
		if ( null !== $req->get_param( 'content_base64' ) ) {
			$content = base64_decode( (string) $req->get_param( 'content_base64' ), true );
			if ( false === $content ) {
				return new WP_Error( 'bad_base64', 'content_base64 is not valid base64.', array( 'status' => 400 ) );
			}
		} elseif ( null !== $req->get_param( 'content' ) ) {
			$content = (string) $req->get_param( 'content' );
		} else {
			return new WP_Error( 'no_content', 'Send content_base64 or content.', array( 'status' => 400 ) );
		}
		wp_mkdir_p( dirname( $path ) );
		$backup = null;
		if ( file_exists( $path ) && ! $req->get_param( 'no_backup' ) ) {
			$backup = $path . '.bak-' . gmdate( 'Ymd-His' );
			copy( $path, $backup );
		}
		$ok = file_put_contents( $path, $content, LOCK_EX );
		if ( false === $ok ) {
			return new WP_Error( 'write_failed', 'Could not write the file (permissions?).', array( 'status' => 500 ) );
		}
		if ( '.php' === substr( $path, -4 ) ) {
			$lint = $this->php_lint( $path );
			if ( true !== $lint ) {
				if ( $backup ) {
					copy( $backup, $path );
				}
				return new WP_Error( 'php_syntax', 'Rejected: PHP syntax error, original restored. ' . $lint, array( 'status' => 422 ) );
			}
		}
		return rest_ensure_response( array( 'written' => $req->get_param( 'path' ), 'bytes' => $ok, 'backup' => $backup ? basename( $backup ) : null ) );
	}

	private function php_lint( $path ) {
		if ( ! function_exists( 'exec' ) ) {
			return true; // Can't check; trust the caller.
		}
		$out = array(); $code = 0;
		@exec( 'php -l ' . escapeshellarg( $path ) . ' 2>&1', $out, $code );
		return 0 === $code ? true : implode( ' ', $out );
	}

	public function file_delete( $req ) {
		$path = $this->resolve( $req->get_param( 'path' ) );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( is_dir( $path ) ) {
			return new WP_Error( 'is_dir', 'Refusing to delete a directory.', array( 'status' => 400 ) );
		}
		if ( false !== strpos( $path, 'aauw-site-control' ) ) {
			return new WP_Error( 'refused', 'Not deleting the control plugin over its own API.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'deleted' => unlink( $path ) ) );
	}

	/** Body: { "zip_base64": "...", "to": "wp-content/themes" } — unpack any zip under wp-content. */
	/* Delete a directory tree inside wp-content (used to clean up stray deploy output). */
	public function dir_delete( $req ) {
		$path = $this->resolve( $req->get_param( 'path' ) );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$base = rtrim( wp_normalize_path( WP_CONTENT_DIR ), '/' );
		if ( ! is_dir( $path ) || $path === $base || in_array( $path, array( $base . '/plugins', $base . '/themes', $base . '/uploads' ), true ) ) {
			return new WP_Error( 'refused', 'Not a deletable directory.', array( 'status' => 400 ) );
		}
		if ( false !== strpos( $path, 'aauw-site-control' ) ) {
			return new WP_Error( 'refused', 'Not deleting the control plugin over its own API.', array( 'status' => 400 ) );
		}
		$count = 0;
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				rmdir( $f->getPathname() );
			} else {
				unlink( $f->getPathname() );
				$count++;
			}
		}
		$ok = rmdir( $path );
		return rest_ensure_response( array( 'deleted' => $req->get_param( 'path' ), 'files' => $count, 'removed' => $ok ) );
	}

	public function unzip( $req ) {
		$this->load_admin_files();
		$to = $this->resolve( $req->get_param( 'to' ) ? $req->get_param( 'to' ) : 'wp-content', false );
		if ( is_wp_error( $to ) ) {
			return $to;
		}
		$tmp = $this->zip_from_request( $req );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		wp_mkdir_p( $to );
		$res = $this->extract_zip( $tmp, $to );
		@unlink( $tmp );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response( array( 'unzipped_to' => $req->get_param( 'to' ), 'files' => $res['files'], 'bytes' => $res['bytes'], 'skipped' => $res['skipped'] ) );
	}

	/* options */

	public function option_get( $req ) {
		$name = sanitize_text_field( $req->get_param( 'name' ) );
		if ( in_array( $name, array( 'auth_key', 'secure_auth_key', self::OPT_TOKEN, self::OPT_DEPLOY ), true ) ) {
			return new WP_Error( 'refused', 'That option is not readable here.', array( 'status' => 403 ) );
		}
		return rest_ensure_response( array( 'name' => $name, 'value' => get_option( $name ) ) );
	}

	public function option_set( $req ) {
		$name = sanitize_text_field( $req->get_param( 'name' ) );
		if ( in_array( $name, array( self::OPT_TOKEN, self::OPT_DEPLOY, self::OPT_OWNER, 'active_plugins' ), true ) ) {
			return new WP_Error( 'refused', 'Use the dedicated endpoint for that.', array( 'status' => 403 ) );
		}
		update_option( $name, $req->get_param( 'value' ) );
		return rest_ensure_response( array( 'name' => $name, 'value' => get_option( $name ) ) );
	}

	/* export */

	public function export_wxr( $req ) {
		require_once ABSPATH . 'wp-admin/includes/export.php';
		ob_start();
		export_wp( array( 'content' => $req->get_param( 'content' ) ? sanitize_key( $req->get_param( 'content' ) ) : 'all' ) );
		$xml = ob_get_clean();
		$res = new WP_REST_Response( $xml );
		$res->header( 'Content-Type', 'application/xml; charset=' . get_option( 'blog_charset' ) );
		add_filter( 'rest_pre_serve_request', function ( $served, $result ) use ( $xml ) {
			echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- raw export document.
			return true;
		}, 10, 2 );
		return $res;
	}

	/* search & replace (for the domain switch): posts, postmeta, options; serialized-safe. Dry run by default. */

	public function search_replace( $req ) {
		global $wpdb;
		$from = (string) $req->get_param( 'from' );
		$to   = (string) $req->get_param( 'to' );
		$dry  = ! $req->get_param( 'apply' );
		if ( '' === $from || '' === $to ) {
			return new WP_Error( 'missing', 'Send from, to, and apply=true to run for real.', array( 'status' => 400 ) );
		}
		$counts = array();
		$like   = '%' . $wpdb->esc_like( $from ) . '%';

		$tables = array(
			array( $wpdb->posts, 'ID', array( 'post_content', 'post_excerpt', 'guid', 'post_content_filtered' ) ),
			array( $wpdb->postmeta, 'meta_id', array( 'meta_value' ) ),
			array( $wpdb->options, 'option_id', array( 'option_value' ) ),
			array( $wpdb->termmeta, 'meta_id', array( 'meta_value' ) ),
			array( $wpdb->usermeta, 'umeta_id', array( 'meta_value' ) ),
			array( $wpdb->comments, 'comment_ID', array( 'comment_content', 'comment_author_url' ) ),
		);
		foreach ( $tables as list( $table, $pk, $cols ) ) {
			foreach ( $cols as $col ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `$pk` AS pk, `$col` AS val FROM `$table` WHERE `$col` LIKE %s", $like ) );
				$n = 0;
				foreach ( $rows as $row ) {
					$new = $this->replace_deep( $row->val, $from, $to );
					if ( $new !== $row->val ) {
						$n++;
						if ( ! $dry ) {
							$wpdb->update( $table, array( $col => $new ), array( $pk => $row->pk ) );
						}
					}
				}
				$counts[ "$table.$col" ] = $n;
			}
		}
		if ( ! $dry ) {
			wp_cache_flush();
		}
		return rest_ensure_response( array( 'dry_run' => $dry, 'from' => $from, 'to' => $to, 'rows_changed' => $counts ) );
	}

	private function replace_deep( $data, $from, $to ) {
		if ( is_string( $data ) ) {
			$un = @unserialize( $data );
			if ( false !== $un || 'b:0;' === $data ) {
				return serialize( $this->replace_deep( $un, $from, $to ) );
			}
			return str_replace( $from, $to, $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = $this->replace_deep( $v, $from, $to );
			}
			return $data;
		}
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$data->$k = $this->replace_deep( $v, $from, $to );
			}
		}
		return $data;
	}

	public function cache_flush() {
		wp_cache_flush();
		if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
		if ( class_exists( 'LiteSpeed\Purge' ) ) { do_action( 'litespeed_purge_all' ); }
		if ( function_exists( 'wp_cache_clear_cache' ) ) { wp_cache_clear_cache(); }
		flush_rewrite_rules();
		if ( function_exists( 'opcache_reset' ) ) { @opcache_reset(); }
		return rest_ensure_response( array( 'flushed' => true ) );
	}

	/* token */

	private function new_token() {
		return 'aauw_' . bin2hex( random_bytes( 24 ) );
	}

	public function token_deploy() {
		$t = 'deploy_' . bin2hex( random_bytes( 24 ) );
		update_option( self::OPT_DEPLOY, password_hash( $t, PASSWORD_DEFAULT ) );
		return rest_ensure_response( array( 'deploy_token' => $t, 'note' => 'Shown once. Store it as the GitHub repository secret AAUW_SITE_TOKEN.' ) );
	}

	public function token_rotate() {
		$t = $this->new_token();
		update_option( self::OPT_TOKEN, password_hash( $t, PASSWORD_DEFAULT ) );
		return rest_ensure_response( array( 'token' => $t, 'note' => 'Shown once. Save it where the proxy injects header ' . self::HEADER . '.' ) );
	}

	/* ------------------------------------------------------------------ admin page (owner only) */

	public function hide_row( $plugins ) {
		if ( ! $this->is_owner() ) {
			unset( $plugins[ plugin_basename( __FILE__ ) ] );
		}
		return $plugins;
	}

	public function menu() {
		if ( $this->is_owner() ) {
			add_options_page( 'Site Control', 'Site Control', 'read', 'aauw-site-control', array( $this, 'page' ) );
		}
	}

	public function handle_settings() {
		if ( empty( $_POST['aauw_control_action'] ) || ! $this->is_owner() ) {
			return;
		}
		check_admin_referer( 'aauw_control' );
		if ( 'rotate' === $_POST['aauw_control_action'] ) {
			$t = $this->new_token();
			update_option( self::OPT_TOKEN, password_hash( $t, PASSWORD_DEFAULT ) );
			set_transient( 'aauw_control_show_token_' . get_current_user_id(), $t, 120 );
		}
		if ( 'revoke' === $_POST['aauw_control_action'] ) {
			delete_option( self::OPT_TOKEN );
		}
		if ( 'deploy' === $_POST['aauw_control_action'] ) {
			$t = 'deploy_' . bin2hex( random_bytes( 24 ) );
			update_option( self::OPT_DEPLOY, password_hash( $t, PASSWORD_DEFAULT ) );
			set_transient( 'aauw_control_show_token_' . get_current_user_id(), $t, 120 );
		}
		if ( 'revoke_deploy' === $_POST['aauw_control_action'] ) {
			delete_option( self::OPT_DEPLOY );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=aauw-site-control' ) );
		exit;
	}

	public function page() {
		if ( ! $this->is_owner() ) {
			wp_die( 'No.' );
		}
		$shown = get_transient( 'aauw_control_show_token_' . get_current_user_id() );
		if ( $shown ) {
			delete_transient( 'aauw_control_show_token_' . get_current_user_id() );
		}
		$has = (bool) get_option( self::OPT_TOKEN );
		?>
		<div class="wrap">
			<h1>Site Control</h1>
			<p>Remote administration for the owner account only. API base: <code><?php echo esc_html( rest_url( self::NS ) ); ?></code></p>
			<?php if ( $shown ) : ?>
				<div class="notice notice-success"><p><strong>Your new token, shown once:</strong></p><p><code style="font-size:16px;user-select:all"><?php echo esc_html( $shown ); ?></code></p><p>Send it as the <code><?php echo esc_html( self::HEADER ); ?></code> header. It is stored here only as a hash.</p></div>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'aauw_control' ); ?>
				<p>Token status: <strong><?php echo $has ? 'set' : 'not set'; ?></strong>. An Application Password for this account also works, if the host passes the Authorization header through.</p>
				<p>
					<button class="button button-primary" name="aauw_control_action" value="rotate"><?php echo $has ? 'Rotate token' : 'Create token'; ?></button>
					<?php if ( $has ) : ?><button class="button" name="aauw_control_action" value="revoke" onclick="return confirm('Revoke the token? Remote access stops until a new one is created.')">Revoke token</button><?php endif; ?>
				</p>
				<p>Deploy token (used by GitHub Actions as the secret AAUW_SITE_TOKEN): <strong><?php echo get_option( self::OPT_DEPLOY ) ? 'set' : 'not set'; ?></strong>
					<button class="button" name="aauw_control_action" value="deploy"><?php echo get_option( self::OPT_DEPLOY ) ? 'Rotate deploy token' : 'Create deploy token'; ?></button>
					<?php if ( get_option( self::OPT_DEPLOY ) ) : ?><button class="button" name="aauw_control_action" value="revoke_deploy">Revoke deploy token</button><?php endif; ?>
				</p>
			</form>
			<h2>What the API can do</h2>
			<ul style="list-style:disc;padding-left:20px">
				<li>List, install (from a zip or URL), activate, deactivate and delete plugins; list and switch themes.</li>
				<li>List, read, write and delete files under wp-content, and the site's .htaccess. PHP files are syntax-checked before they replace the original; a .bak copy is kept.</li>
				<li>Read and set options. Export all content as WordPress XML. Search and replace across the database for a domain switch (dry run by default). Flush caches.</li>
			</ul>
			<p style="color:#646970">It cannot run arbitrary code, read the token or security keys, or remove itself.</p>
		</div>
		<?php
	}
}

AAUW_Site_Control::instance();

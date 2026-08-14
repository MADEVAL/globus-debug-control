<?php
/**
 * GLOBUS Debug Control - MU-Plugin Dropin Trait
 *
 * Generates, installs and removes the optional early-loader mu-plugin
 * that intercepts errors before regular plugins are loaded.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait GDC_Mu_Dropin {

	/**
	 * Returns the expected filesystem path of the mu-plugin dropin file.
	 *
	 * @return string Absolute path.
	 */
	private function get_mu_plugin_path(): string {
		$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' );
		return $dir . '/' . self::MU_FILE;
	}

	// -------------------------------------------------------------------------
	// Filesystem helpers (WP_Filesystem with direct-operation fallback)
	// -------------------------------------------------------------------------

	/**
	 * Returns an initialised WP_Filesystem instance, or null when unavailable.
	 *
	 * @return object|null
	 */
	private function get_wp_filesystem() {
		global $wp_filesystem;

		if ( ! empty( $wp_filesystem ) ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() && ! empty( $wp_filesystem ) ) {
			return $wp_filesystem;
		}

		return null;
	}

	/**
	 * Reads a file via WP_Filesystem, falling back to direct file_get_contents.
	 *
	 * @param string $path Absolute path.
	 * @return string|false File contents, or false on failure.
	 */
	private function read_file_contents( string $path ) {
		$fs = $this->get_wp_filesystem();

		if ( null !== $fs && method_exists( $fs, 'get_contents' ) ) {
			$content = $fs->get_contents( $path );
			if ( false !== $content ) {
				return $content;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fallback when WP_Filesystem is unavailable (e.g. FTP-only hosts).
		return @file_get_contents( $path );
	}

	/**
	 * Writes a file via WP_Filesystem, falling back to direct file_put_contents.
	 *
	 * @param string $path    Absolute path.
	 * @param string $content File contents.
	 * @return bool True on success.
	 */
	private function write_file_contents( string $path, string $content ): bool {
		$fs = $this->get_wp_filesystem();

		if ( null !== $fs && method_exists( $fs, 'put_contents' ) && $fs->put_contents( $path, $content, FS_CHMOD_FILE ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fallback when WP_Filesystem is unavailable.
		$result = @file_put_contents( $path, $content );
		if ( false !== $result ) {
			@chmod( $path, FS_CHMOD_FILE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			return true;
		}
		return false;
	}

	/**
	 * Deletes a file via WP_Filesystem, falling back to direct unlink.
	 *
	 * @param string $path Absolute path.
	 * @return bool True when the file is gone.
	 */
	private function delete_file_path( string $path ): bool {
		$fs = $this->get_wp_filesystem();

		if ( null !== $fs && method_exists( $fs, 'delete' ) && $fs->delete( $path ) ) {
			return ! file_exists( $path );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Fallback when WP_Filesystem is unavailable.
		return @unlink( $path );
	}

	// -------------------------------------------------------------------------
	// Dropin lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Cached dropin-installed state and the file mtime it was computed from.
	 *
	 * @var array{installed: bool, mtime: int|false}|null
	 */
	private static $mu_dropin_cache = null;

	/**
	 * Checks whether the early-loader mu-plugin is installed and owned by this plugin.
	 *
	 * The result is cached per request keyed by file mtime so the settings page
	 * can call this method multiple times without re-reading the file.
	 *
	 * @return bool True when the dropin exists and contains the GLOBUS marker.
	 */
	public function is_mu_dropin_installed(): bool {
		$path  = $this->get_mu_plugin_path();
		$mtime = @filemtime( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime -- Per-request cache key.

		if ( null !== self::$mu_dropin_cache && self::$mu_dropin_cache['mtime'] === $mtime ) {
			return self::$mu_dropin_cache['installed'];
		}

		if ( ! file_exists( $path ) ) {
			self::$mu_dropin_cache = array(
				'installed' => false,
				'mtime'     => $mtime,
			);
			return false;
		}

		$content               = $this->read_file_contents( $path );
		$installed             = false !== $content && false !== strpos( $content, 'GLOBUS Debug Control' );
		self::$mu_dropin_cache = array(
			'installed' => $installed,
			'mtime'     => $mtime,
		);
		return $installed;
	}

	/**
	 * Generates the PHP source code for the early-loader mu-plugin.
	 *
	 * @return string Complete PHP file content.
	 */
	private function generate_mu_content(): string {
		$lines = array(
			'<?php',
			'/**',
			' * GLOBUS Debug Control - Early Loader (mu-plugin)',
			' *',
			' * Sets error_reporting BEFORE regular plugins are loaded,',
			' * intercepting compile-time errors (E_DEPRECATED, E_NOTICE, etc.)',
			' * from third-party plugins loaded earlier in alphabetical order.',
			' *',
			' * Auto-generated by GLOBUS Debug Control v' . self::VERSION . '. Do not edit manually.',
			' */',
			'if ( ! defined( \'ABSPATH\' ) ) { exit; }',
			'',
			'$gdc_opts = get_option( \'globus_debug_control_options\', [] );',
			'',
			'// Migrate from legacy option key.',
			'if ( empty( $gdc_opts ) ) {',
			"\t" . '$gdc_legacy = get_option( \'gdc_options\', [] );',
			"\t" . 'if ( ! empty( $gdc_legacy ) ) {',
			"\t\t" . 'update_option( \'globus_debug_control_options\', $gdc_legacy );',
			"\t\t" . 'delete_option( \'gdc_options\' );',
			"\t\t" . '$gdc_opts = $gdc_legacy;',
			"\t" . '}',
			'}',
			'',
			'if ( empty( $gdc_opts ) || empty( $gdc_opts[\'enabled\'] ) ) {',
			"\treturn;",
			'}',
			'',
			'$gdc_mask = 0;',
			'',
			'if ( ! empty( $gdc_opts[\'suppress_deprecated\'] ) ) {',
			"\t" . '$gdc_mask |= E_DEPRECATED | E_USER_DEPRECATED;',
			'}',
			'if ( ! empty( $gdc_opts[\'suppress_notices\'] ) ) {',
			"\t" . '$gdc_mask |= E_NOTICE | E_USER_NOTICE;',
			'}',
			'if ( ! empty( $gdc_opts[\'suppress_warnings\'] ) ) {',
			"\t" . '$gdc_mask |= E_WARNING | E_USER_WARNING;',
			'}',
			'if ( ! empty( $gdc_opts[\'suppress_strict\'] ) && version_compare( PHP_VERSION, \'8.0.0\', \'<\' ) && defined( \'E_STRICT\' ) ) {',
			"\t" . '$gdc_mask |= E_STRICT;',
			'}',
			'',
			'if ( $gdc_mask > 0 ) {',
			"\t" . 'error_reporting( E_ALL & ~$gdc_mask );',
			'}',
			'',
			'// Prevent wp_debug_mode() from overriding our settings if called again.',
			'add_filter( \'enable_wp_debug_mode_checks\', \'__return_false\', 1 );',
		);
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Writes the early-loader mu-plugin to the mu-plugins directory.
	 *
	 * Aborts without changes if a foreign file already exists at the target path.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function install_mu_dropin(): bool {
		$path = $this->get_mu_plugin_path();
		$dir  = dirname( $path );

		if ( file_exists( $path ) ) {
			$existing = $this->read_file_contents( $path );
			if ( false !== $existing && false === strpos( $existing, 'GLOBUS Debug Control' ) ) {
				return false;
			}
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		return $this->write_file_contents( $path, $this->generate_mu_content() );
	}

	/**
	 * Removes the early-loader mu-plugin if it is owned by this plugin.
	 *
	 * @return bool True when the file is absent (never existed or successfully removed).
	 */
	public function remove_mu_dropin(): bool {
		$path = $this->get_mu_plugin_path();
		if ( ! file_exists( $path ) ) {
			return true;
		}

		$content = $this->read_file_contents( $path );
		if ( false === $content || false === strpos( $content, 'GLOBUS Debug Control' ) ) {
			return false;
		}

		return $this->delete_file_path( $path );
	}

	/**
	 * Fires on plugin deactivation and removes the mu-plugin dropin.
	 */
	public function on_deactivation() {
		$this->remove_mu_dropin();
	}
}

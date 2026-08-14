<?php
/**
 * GLOBUS Debug Control - Error Control Trait
 *
 * Handles all runtime error-reporting and log configuration:
 * suppress mask, display_errors, log rotation, and API-context detection.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait GDC_Error_Control {

	// -------------------------------------------------------------------------
	// Bootstrap / lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Applies error control settings at plugin load time.
	 */
	private function early_init() {
		$opts = $this->get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		$this->apply_error_reporting( $opts );
		$this->apply_display_settings( $opts );
		$this->apply_log_settings( $opts );
	}

	/**
	 * Re-applies error control settings after all plugins have loaded.
	 */
	public function plugins_loaded() {
		$opts = $this->get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		$this->apply_error_reporting( $opts );
		$this->apply_display_settings( $opts );
		$this->apply_log_settings( $opts );

		register_shutdown_function( array( $this, 'capture_fatal_error' ) );
	}

	/**
	 * Records the last fatal PHP error at shutdown for display in the log viewer.
	 *
	 * @param array{type?: int, message?: string, file?: string, line?: int}|null $last_error Optional payload (defaults to error_get_last()).
	 */
	public function capture_fatal_error( ?array $last_error = null ): void {
		if ( null === $last_error ) {
			$last_error = error_get_last();
		}

		if ( ! is_array( $last_error ) || empty( $last_error['type'] ) || empty( $last_error['message'] ) ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

		if ( ! in_array( (int) $last_error['type'], $fatal_types, true ) ) {
			return;
		}

		update_option(
			'gdc_last_fatal',
			array(
				'ts'      => time(),
				'message' => (string) $last_error['message'],
				'file'    => isset( $last_error['file'] ) ? (string) $last_error['file'] : '',
				'line'    => isset( $last_error['line'] ) ? (int) $last_error['line'] : 0,
			),
			false
		);
	}

	/**
	 * Re-applies error reporting once the WP environment is fully loaded.
	 *
	 * Also installs a custom error handler to suppress error types set in options.
	 */
	public function on_wp_loaded() {
		$opts = $this->get_options();

		if ( empty( $opts['enabled'] ) ) {
			return;
		}

		// Re-apply in case other plugins/themes overrode error_reporting.
		$this->apply_error_reporting( $opts );
		$this->install_error_handler( $opts );
	}

	// -------------------------------------------------------------------------
	// Options
	// -------------------------------------------------------------------------

	/**
	 * Returns the default option values.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'enabled'                    => 1,
			'suppress_notices'           => 1,
			'suppress_deprecated'        => 1,
			'suppress_warnings'          => 0,
			'suppress_strict'            => 1,
			'display_errors_mode'        => 'inherit',
			'log_enabled'                => 1,
			'log_path'                   => '',
			'log_max_size_mb'            => 10,
			'remove_php_error_bodyclass' => 1,
			'force_in_api_contexts'      => 0,
			'install_mu_dropin'          => 0,
		);
	}

	/**
	 * Returns the merged and sanitised plugin options.
	 *
	 * @param bool $force_refresh When true, bypasses the in-memory static cache.
	 * @return array<string, mixed>
	 */
	public function get_options( bool $force_refresh = false ): array {
		static $cached_options = null;

		if ( null !== $cached_options && ! $force_refresh ) {
			return $cached_options;
		}

		$saved = get_option( self::OPT, null );

		// Migrate from legacy option key 'gdc_options'.
		if ( null === $saved ) {
			$legacy = get_option( 'gdc_options', null );
			if ( is_array( $legacy ) ) {
				update_option( self::OPT, $legacy );
				delete_option( 'gdc_options' );
				$saved = $legacy;
			} else {
				$saved = array();
			}
		}

		$opts = wp_parse_args( $saved, $this->defaults() );

		$bool_keys = array(
			'enabled',
			'suppress_notices',
			'suppress_deprecated',
			'suppress_warnings',
			'suppress_strict',
			'log_enabled',
			'remove_php_error_bodyclass',
			'force_in_api_contexts',
			'install_mu_dropin',
		);

		foreach ( $bool_keys as $key ) {
			$opts[ $key ] = (int) ! empty( $opts[ $key ] );
		}

		$allowed_modes = array( 'inherit', 'on', 'off' );
		if ( ! in_array( $opts['display_errors_mode'], $allowed_modes, true ) ) {
			$opts['display_errors_mode'] = 'inherit';
		}

		$opts['log_max_size_mb'] = max( 1, min( 100, (int) $opts['log_max_size_mb'] ) );
		$opts['log_path']        = trim( $opts['log_path'] );

		$cached_options = $opts;
		return $opts;
	}

	// -------------------------------------------------------------------------
	// Error reporting
	// -------------------------------------------------------------------------

	/**
	 * Builds the bitmask of error types to suppress.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 * @return int Bitmask of suppressed PHP error constants.
	 */
	private function build_suppress_mask( array $opts ): int {
		$mask = 0;

		if ( $opts['suppress_deprecated'] ) {
			$mask |= E_DEPRECATED | E_USER_DEPRECATED;
		}

		if ( $opts['suppress_notices'] ) {
			$mask |= E_NOTICE | E_USER_NOTICE;
		}

		if ( $opts['suppress_warnings'] ) {
			$mask |= E_WARNING | E_USER_WARNING;
		}

		if ( $opts['suppress_strict'] && version_compare( PHP_VERSION, '8.0.0', '<' ) && defined( 'E_STRICT' ) ) {
			$mask |= E_STRICT;
		}

		return $mask;
	}

	/**
	 * Calls error_reporting() with the calculated suppress mask applied.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 */
	private function apply_error_reporting( array $opts ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- Core debug control functionality.
		error_reporting( E_ALL & ~$this->build_suppress_mask( $opts ) );
	}

	/**
	 * Installs a custom error handler that silently swallows suppressed error types.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 */
	private function install_error_handler( array $opts ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$suppress_mask = $this->build_suppress_mask( $opts );

		if ( 0 === $suppress_mask ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Core debug control functionality.
		$previous = set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) use ( $suppress_mask, &$previous ) {
				if ( $errno & $suppress_mask ) {
						return true;
				}

				if ( is_callable( $previous ) ) {
					return call_user_func( $previous, $errno, $errstr, $errfile, $errline );
				}

				return false;
			}
		);
	}

	// -------------------------------------------------------------------------
	// Display & log settings
	// -------------------------------------------------------------------------

	/**
	 * Sets display_errors and display_startup_errors via ini_set.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 */
	private function apply_display_settings( array $opts ) {
		$is_api_context = $this->is_api_context();

		$display = '0';

		if ( $is_api_context && empty( $opts['force_in_api_contexts'] ) ) {
			$display = '0';
		} elseif ( 'on' === $opts['display_errors_mode'] ) {
				$display = '1';
		} elseif ( 'off' === $opts['display_errors_mode'] ) {
			$display = '0';
		} else {
			$display = ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) ? '1' : '0';
		}

		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for debug display control.
		ini_set( 'display_errors', $display );
		// Note: display_startup_errors is intentionally not set here - it only
		// affects errors emitted before script start, so ini_set() at runtime
		// would have no effect.
	}

	/**
	 * Configures log_errors and error_log ini settings.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 */
	private function apply_log_settings( array $opts ) {
		if ( empty( $opts['log_enabled'] ) ) {
			ini_set( 'log_errors', '0' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for log control.
			return;
		}

		$log_path = $this->resolve_log_path( $opts );

		if ( empty( $log_path ) ) {
			$log_path = WP_CONTENT_DIR . '/debug.log';
		}

		$this->rotate_log_if_needed( $log_path, $opts['log_max_size_mb'] );

		if ( $this->ensure_log_writable( $log_path ) ) {
			ini_set( 'log_errors', '1' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for log control.
			ini_set( 'error_log', $log_path ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for log control.
		}
	}

	// -------------------------------------------------------------------------
	// Log path helpers
	// -------------------------------------------------------------------------

	/**
	 * Determines the active log file path from options or WordPress constants.
	 *
	 * @param array<string, mixed> $opts Plugin options.
	 * @return string Absolute path to the log file, or empty string if not configured.
	 */
	public function resolve_log_path( array $opts ): string {
		if ( ! empty( $opts['log_path'] ) ) {
			$path = $opts['log_path'];

			if ( ! $this->is_absolute_path( $path ) ) {
				$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
			}

			return $this->sanitize_path( $path );
		}

		if ( defined( 'WP_DEBUG_LOG' ) ) {
			if ( true === WP_DEBUG_LOG || 1 === WP_DEBUG_LOG ) {
				return WP_CONTENT_DIR . '/debug.log';
			}

			if ( is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
				$path = WP_DEBUG_LOG;

				if ( ! $this->is_absolute_path( $path ) ) {
					$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
				}

				return $this->sanitize_path( $path );
			}
		}

		return '';
	}

	/**
	 * Returns true when the given path is absolute on Unix or Windows.
	 *
	 * @param string $path Path to test.
	 * @return bool
	 */
	private function is_absolute_path( string $path ): bool {
		return ( isset( $path[0] ) && '/' === $path[0] ) || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path );
	}

	/**
	 * Normalises a filesystem path by removing null bytes and standardising slashes.
	 *
	 * Returns an empty string when the path contains a parent-traversal ("..")
	 * component, preventing the log file from being pointed at critical files
	 * such as wp-config.php.
	 *
	 * @param string $path Raw path.
	 * @return string Sanitised path, or '' when the path is unsafe.
	 */
	private function sanitize_path( string $path ): string {
		$path = str_replace( array( "\0", "\r", "\n" ), '', $path );
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );

		foreach ( explode( '/', $path ) as $part ) {
			if ( '..' === $part ) {
				return '';
			}
		}

		return $path;
	}

	/**
	 * Ensures the log file and its parent directory exist and are writable.
	 *
	 * @param string $log_path Absolute path to the log file.
	 * @return bool True when the log file is ready for writing.
	 */
	private function ensure_log_writable( string $log_path ): bool {
		$dir = dirname( $log_path );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Low-level check before WP_Filesystem is available.
		if ( ! is_writable( $dir ) ) {
			return false;
		}

		if ( ! file_exists( $log_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Low-level log file creation before WP_Filesystem is available.
			$handle = @fopen( $log_path, 'a' );
			if ( false === $handle ) {
				return false;
			}
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			@chmod( $log_path, 0644 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Low-level check before WP_Filesystem is available.
		return is_writable( $log_path );
	}

	/**
	 * Renames the log file with a date suffix when it exceeds the size limit.
	 *
	 * @param string $log_path    Absolute path to the log file.
	 * @param int    $max_size_mb Maximum allowed size in megabytes.
	 */
	private function rotate_log_if_needed( string $log_path, int $max_size_mb ) {
		if ( ! file_exists( $log_path ) ) {
			return;
		}

		$size_bytes = @filesize( $log_path );

		if ( false === $size_bytes ) {
			return;
		}

		$max_bytes = $max_size_mb * 1024 * 1024;

		if ( $size_bytes > $max_bytes ) {
			$backup = $log_path . '.' . gmdate( 'Y-m-d_H-i-s' ) . '.old';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Low-level log rotation before WP_Filesystem is available.
			@rename( $log_path, $backup );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
			@touch( $log_path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			@chmod( $log_path, 0644 );
		}
	}

	// -------------------------------------------------------------------------
	// API context detection
	// -------------------------------------------------------------------------

	/**
	 * Returns true when the current request is an automated or API context.
	 *
	 * Includes AJAX, REST API, XML-RPC, WP-Cron, WP-CLI, and heartbeat.
	 *
	 * @return bool
	 */
	private function is_api_context(): bool {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			( defined( 'MS_FILES_REQUEST' ) && MS_FILES_REQUEST ) ||
			( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ||
			( defined( 'DOING_CRON' ) && DOING_CRON ) ||
			( defined( 'WP_CLI' ) && WP_CLI ) ||
			( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ||
			( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ||
		     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only context detection, not form processing.
			( isset( $_POST['action'] ) && is_string( $_POST['action'] ) && 'heartbeat' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) ) {
			return true;
		}

		return false;
	}
}

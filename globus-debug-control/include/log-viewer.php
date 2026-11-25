<?php
/**
 * GLOBUS Debug Control - Log Viewer
 *
 * Displays, paginates, clears, and serves the WordPress debug log.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and manages the WordPress debug log viewer.
 *
 * @package GlobusDebugControl
 */
final class GDC_Log_Viewer {

	private const LINES_PER_PAGE = 200;
	private const NONCE_ACTION   = 'gdc_log_viewer';
	private const NONCE_CLEAR    = 'gdc_log_clear';
	private const NONCE_GENERATE = 'gdc_generate_error';

	/**
	 * Returns the resolved absolute path to the active debug log file.
	 *
	 * @return string Absolute path to the log file.
	 */
	public static function get_log_path(): string {
		$plugin = Globus_Debug_Control::instance();
		$opts   = $plugin->get_options();
		$path   = $plugin->resolve_log_path( $opts );

		if ( empty( $path ) ) {
			$path = WP_CONTENT_DIR . '/debug.log';
		}

		return $path;
	}

	/**
	 * Handles the "Clear Log" form submission, truncates the log file, and redirects.
	 */
	public static function handle_clear_action(): void {
		if ( ! isset( $_POST['gdc_clear_log'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE_CLEAR );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			return;
		}

		$path = self::get_log_path();

		if ( file_exists( $path ) && wp_is_writable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, '' );
		}

		wp_safe_redirect( GDC_UI::module_url( 'log', array( 'cleared' => '1' ) ) );
		exit;
	}

	/**
	 * Handles the log file download request, streaming the file as a text attachment.
	 */
	public static function handle_download_action(): void {
		if ( ! isset( $_GET['gdc_dl'] ) || '1' !== $_GET['gdc_dl'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		check_admin_referer( 'gdc_log_download' );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'globus-debug-control' ) );
		}

		$path = self::get_log_path();

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Log file not found.', 'globus-debug-control' ) );
		}

		$filename = basename( $path );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . (int) filesize( $path ) );
		header( 'Pragma: no-cache' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a local log file for download; readfile is the correct low-level API here.
		readfile( $path );
		exit;
	}

	/**
	 * Returns the list of error type keys that are currently suppressed by plugin settings.
	 *
	 * @return string[] E.g. ['notice', 'deprecated'].
	 */
	public static function get_suppressed_types(): array {
		$opts       = Globus_Debug_Control::instance()->get_options();
		$map        = array(
			'notice'     => 'suppress_notices',
			'warning'    => 'suppress_warnings',
			'deprecated' => 'suppress_deprecated',
		);
		$suppressed = array();
		foreach ( $map as $type => $opt_key ) {
			if ( ! empty( $opts[ $opt_key ] ) ) {
				$suppressed[] = $type;
			}
		}
		return $suppressed;
	}

	/**
	 * Returns the effective debug/log state used by the viewer UI.
	 *
	 * @return array{wp_debug: bool, wp_debug_log: bool|string|null, plugin_enabled: bool, log_enabled: bool, logging_active: bool, log_path: string}
	 */
	public static function get_debug_state(): array {
		$opts = Globus_Debug_Control::instance()->get_options();

		$wp_debug       = GDC_WpConfig_Debug::is_wp_debug_on();
		$plugin_enabled = ! empty( $opts['enabled'] );
		$log_enabled    = ! empty( $opts['log_enabled'] );

		return array(
			'wp_debug'       => $wp_debug,
			'wp_debug_log'   => GDC_WpConfig_Debug::get_wp_debug_log_value(),
			'plugin_enabled' => $plugin_enabled,
			'log_enabled'    => $log_enabled,
			'logging_active' => $wp_debug && $plugin_enabled && $log_enabled,
			'log_path'       => self::get_log_path(),
		);
	}

	/**
	 * Registers AJAX action hooks for the log viewer.
	 */
	public static function register_ajax_hooks(): void {
		add_action( 'wp_ajax_gdc_log_status', array( self::class, 'ajax_log_status' ) );
		add_action( 'wp_ajax_gdc_generate_error', array( self::class, 'ajax_generate_error' ) );
	}

	/**
	 * AJAX handler: returns current log file modification time and size as JSON.
	 */
	public static function ajax_log_status(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}
		$path  = self::get_log_path();
		$mtime = file_exists( $path ) ? (int) @filemtime( $path ) : 0;
		$size  = file_exists( $path ) ? (int) @filesize( $path ) : 0;
		wp_send_json_success(
			array(
				'mtime' => $mtime,
				'size'  => $size,
			)
		);
	}

	/**
	 * AJAX handler: triggers a test PHP error of the requested type and writes it to the log.
	 *
	 * Accepts POST param `type`: notice | warning | deprecated | error | exception | fatal | parse.
	 *
	 * @throws \RuntimeException Intentionally thrown when type is 'exception' (caught internally).
	 */
	public static function ajax_generate_error(): void {
		check_ajax_referer( self::NONCE_GENERATE );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		if ( ! self::get_debug_state()['logging_active'] ) {
			wp_send_json_error( 'Error logging is inactive. Enable WP_DEBUG and error logging first.', 409 );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'notice';

		$trigger_map = array(
			'notice'     => E_USER_NOTICE,
			'warning'    => E_USER_WARNING,
			'deprecated' => E_USER_DEPRECATED,
			'error'      => E_USER_ERROR,
		);

		$valid_types = array( 'notice', 'warning', 'deprecated', 'error', 'exception', 'fatal', 'parse' );

		if ( ! in_array( $type, $valid_types, true ) ) {
			wp_send_json_error( 'Invalid error type.' );
		}

		$label = '[GLOBUS Debug Control] Test ' . strtoupper( $type ) . ' generated by admin.'; // Value is validated against a whitelist above.

		if ( array_key_exists( $type, $trigger_map ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error,WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional test error for debug log verification; $label is built from a validated whitelist.
			trigger_error( $label, $trigger_map[ $type ] );
		} elseif ( 'exception' === $type ) {
			try {
				throw new \RuntimeException( $label );
			} catch ( \RuntimeException $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional test error for debug log verification.
				error_log( 'PHP Fatal error: Uncaught RuntimeException: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
		} elseif ( 'fatal' === $type ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional test error for debug log verification.
			error_log( 'PHP Fatal error: ' . $label . ' in ' . __FILE__ . ':' . __LINE__ );
		} elseif ( 'parse' === $type ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional test error for debug log verification.
			error_log( 'PHP Parse error: ' . $label . ' in ' . __FILE__ . ':' . __LINE__ );
		}

		wp_send_json_success( array( 'type' => $type ) );
	}

	/**
	 * Renders the full log viewer UI with pagination, toolbar, and line list.
	 */
	public static function render(): void {
		$path         = self::get_log_path();
		$debug_state  = self::get_debug_state();
		$can_generate = $debug_state['logging_active'];
		$page         = max( 1, isset( $_GET['logpage'] ) ? (int) $_GET['logpage'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.

		$exists = file_exists( $path );
		$size   = $exists ? @filesize( $path ) : 0;
		$mtime  = $exists ? (int) @filemtime( $path ) : 0;
		$lines  = array();
		$total  = 0;

		/** Maximum bytes to read for log rendering (2 MB). Prevents OOM on very large log files. */
		$max_render_bytes = 2 * 1024 * 1024;

		if ( $exists && $size > 0 ) {
			if ( $size <= $max_render_bytes ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = @file_get_contents( $path );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Low-level tail read for large log files.
				$handle = @fopen( $path, 'r' );
				if ( false !== $handle ) {
					fseek( $handle, -$max_render_bytes, SEEK_END );
					fgets( $handle ); // Discard partial first line.
					$content = fread( $handle, $max_render_bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
					fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				} else {
					$content = false;
				}
			}

			$all    = false !== $content ? array_reverse( explode( "\n", rtrim( $content ) ) ) : array();
			$total  = count( $all );
			$offset = ( $page - 1 ) * self::LINES_PER_PAGE;
			$lines  = array_slice( $all, $offset, self::LINES_PER_PAGE );
		}

		$total_pages = $total > 0 ? (int) ceil( $total / self::LINES_PER_PAGE ) : 1;

		$error_type_options = array(
			'notice'     => 'E_USER_NOTICE',
			'warning'    => 'E_USER_WARNING',
			'deprecated' => 'E_USER_DEPRECATED',
			'error'      => 'E_USER_ERROR',
			'exception'  => 'Uncaught Exception',
			'fatal'      => 'Fatal Error (sim.)',
			'parse'      => 'Parse Error (sim.)',
		);
		$suppressed_types   = self::get_suppressed_types();
		?>
		<div class="gdc-log-viewer">

			<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag. ?>
				<?php echo GDC_UI::alert( esc_html__( 'Log file cleared.', 'globus-debug-control' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
			<?php endif; ?>

			<div class="gdc-dependency gdc-mb-3h">
				<?php GDC_WpConfig_Debug::render_dependency_notice( 'log' ); ?>
			</div>

			<?php if ( ! $can_generate ) : ?>
				<div class="gdc-alert gdc-alert--info gdc-mb-3h gdc-log-generate-disabled">
					<?php esc_html_e( 'Test errors are disabled because WP_DEBUG or error logging is off. Enable both to confirm that log writing works.', 'globus-debug-control' ); ?>
				</div>
			<?php endif; ?>

			<?php
			$last_fatal = get_option( 'gdc_last_fatal', null );
			if ( is_array( $last_fatal ) && ! empty( $last_fatal['message'] ) ) :
				$fatal_time = isset( $last_fatal['ts'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last_fatal['ts'] ) : '';
				$fatal_file = isset( $last_fatal['file'] ) ? (string) $last_fatal['file'] : '';
				$fatal_line = isset( $last_fatal['line'] ) ? (int) $last_fatal['line'] : 0;
				?>
				<div class="gdc-alert gdc-alert--danger gdc-mb-3h gdc-last-fatal">
					<p><strong><?php esc_html_e( 'Last fatal error:', 'globus-debug-control' ); ?></strong> <?php echo esc_html( (string) $last_fatal['message'] ); ?></p>
					<?php if ( '' !== $fatal_file ) : ?>
						<p class="gdc-mono gdc-fs-2xs gdc-mt-2"><?php echo esc_html( $fatal_file . ':' . $fatal_line ); ?>
							<?php if ( '' !== $fatal_time ) : ?>
								<span class="gdc-dim">(<?php echo esc_html( $fatal_time ); ?>)</span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<script>var gdcLog=
			<?php
			echo wp_json_encode(
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( self::NONCE_GENERATE ),
					'statusNonce'     => wp_create_nonce( self::NONCE_ACTION ),
					'mtime'           => $mtime,
					'suppressedTypes' => $suppressed_types,
				)
			);
			?>
							;</script>

			<div class="gdc-card gdc-mb-3h gdc-log-toolbar">
				<div class="gdc-card-pad gdc-toolbar gdc-mb-0">
					<div class="gdc-console-meta">
						<?php if ( $exists ) : ?>
							<code title="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( $path ); ?></code>
							<button type="button" class="gdc-btn gdc-btn--sm gdc-btn--icon" data-gdc-copy="<?php echo esc_attr( $path ); ?>" aria-label="<?php esc_attr_e( 'Copy log path', 'globus-debug-control' ); ?>" title="<?php esc_attr_e( 'Copy log path', 'globus-debug-control' ); ?>">
								<?php echo GDC_UI::icon( 'copy', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
							</button>
							<span class="gdc-chip"><?php echo esc_html( size_format( (int) $size ) ); ?></span>
							<?php /* translators: %s: number of log lines */ ?>
							<span class="gdc-chip"><?php echo esc_html( sprintf( _n( '%s line', '%s lines', $total, 'globus-debug-control' ), number_format_i18n( $total ) ) ); ?></span>
						<?php else : ?>
							<span><?php esc_html_e( 'Log file does not exist.', 'globus-debug-control' ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( $exists && $size > 0 ) : ?>
						<div class="gdc-console-actions">
							<label class="gdc-check gdc-log-wrap-label">
								<input type="checkbox" id="gdc-log-wrap-toggle">
								<?php esc_html_e( 'Word wrap', 'globus-debug-control' ); ?>
							</label>
							<a href="
							<?php
							echo esc_url(
								wp_nonce_url(
									GDC_UI::module_url( 'log', array( 'gdc_dl' => '1' ) ),
									'gdc_log_download'
								)
							);
							?>
									" class="gdc-btn gdc-btn--surface">
								<?php echo GDC_UI::icon( 'cloud-download', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
								<?php esc_html_e( 'Download', 'globus-debug-control' ); ?>
							</a>
							<form method="post" class="gdc-inline">
								<?php wp_nonce_field( self::NONCE_CLEAR ); ?>
								<input type="hidden" name="gdc_clear_log" value="1">
								<button type="submit" class="gdc-btn gdc-btn--danger" data-gdc-confirm="<?php esc_attr_e( 'Clear the log file?', 'globus-debug-control' ); ?>">
									<?php echo GDC_UI::icon( 'trash', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
									<?php esc_html_e( 'Clear log', 'globus-debug-control' ); ?>
								</button>
							</form>
						</div>
					<?php endif; ?>
				</div>

				<div class="gdc-divider gdc-mb-0"></div>

				<div class="gdc-card-pad gdc-toolbar gdc-mb-0">
					<div class="gdc-toolbar-group">
						<span class="gdc-toolbar-label"><?php esc_html_e( 'Test error', 'globus-debug-control' ); ?></span>
						<select id="gdc-error-type" class="gdc-input" <?php disabled( ! $can_generate ); ?>>
							<?php foreach ( $error_type_options as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( in_array( $val, $suppressed_types, true ) ? $label . ' - suppressed' : $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" id="gdc-generate-error" class="gdc-btn gdc-btn--surface" <?php disabled( ! $can_generate ); ?> <?php echo $can_generate ? '' : 'title="' . esc_attr__( 'Enable WP_DEBUG and error logging first.', 'globus-debug-control' ) . '"'; ?>>
							<?php echo GDC_UI::icon( 'bolt', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
							<?php esc_html_e( 'Generate', 'globus-debug-control' ); ?>
						</button>
					</div>
					<div class="gdc-toolbar-group">
						<button type="button" id="gdc-live-toggle" class="gdc-btn gdc-btn--surface" title="<?php esc_attr_e( 'Auto-refresh when log file changes', 'globus-debug-control' ); ?>">
							<?php echo GDC_UI::icon( 'refresh', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
							<?php esc_html_e( 'Live tail', 'globus-debug-control' ); ?>
						</button>
					</div>
				</div>
			</div>

			<?php if ( $exists && $size > $max_render_bytes ) : ?>
				<div class="gdc-alert gdc-alert--warning gdc-mb-3h gdc-log-tail-notice">
					<?php esc_html_e( 'The log file is larger than the render limit - only the last 2 MB are shown. Download the full file to inspect everything.', 'globus-debug-control' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $lines ) ) : ?>
				<?php
				$line_data  = array();
				$text_lines = array();
				foreach ( $lines as $i => $line ) {
					$text_lines[] = $line;
					if ( '' !== trim( $line ) ) {
						$cls = self::classify_line( $line );
						if ( 'gdc-log-info' !== $cls ) {
							$line_data[] = array(
								'l' => $i,
								'c' => $cls,
							);
						}
					}
				}
				?>
				<div class="gdc-console" id="gdc-log-wrap">
					<textarea id="gdc-log-code" readonly><?php echo esc_textarea( implode( "\n", $text_lines ) ); ?></textarea>
				</div>
				<script type="application/json" id="gdc-log-line-data"><?php echo wp_json_encode( $line_data ); ?></script>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="gdc-row gdc-row--between gdc-mt-3">
						<span class="gdc-dim gdc-fs-xs">
							<?php /* translators: %s: number of log lines */ ?>
							<?php echo esc_html( sprintf( _n( '%s line', '%s lines', $total, 'globus-debug-control' ), number_format_i18n( $total ) ) ); ?>
						</span>
						<div class="gdc-pager">
							<?php if ( $page > 1 ) : ?>
								<a class="gdc-btn gdc-btn--sm gdc-btn--icon gdc-pager-prev" href="<?php echo esc_url( GDC_UI::module_url( 'log', array( 'logpage' => $page - 1 ) ) ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'globus-debug-control' ); ?>">
									<?php echo GDC_UI::icon( 'chevronR', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
								</a>
							<?php endif; ?>
							<span class="gdc-chip"><?php echo esc_html( $page . ' / ' . $total_pages ); ?></span>
							<?php if ( $page < $total_pages ) : ?>
								<a class="gdc-btn gdc-btn--sm gdc-btn--icon" href="<?php echo esc_url( GDC_UI::module_url( 'log', array( 'logpage' => $page + 1 ) ) ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'globus-debug-control' ); ?>">
									<?php echo GDC_UI::icon( 'chevronR', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<div class="gdc-card gdc-card-pad gdc-log-empty-state">
					<?php
					if ( ! $debug_state['wp_debug'] ) {
						$gdc_empty_text = __( 'Log file is empty. WP_DEBUG is off, so PHP does not report new errors.', 'globus-debug-control' );
					} elseif ( ! $debug_state['log_enabled'] ) {
						$gdc_empty_text = __( 'Log file is empty. Error logging is disabled in the plugin settings.', 'globus-debug-control' );
					} else {
						$gdc_empty_text = __( 'Log file is empty.', 'globus-debug-control' );
					}

					echo GDC_UI::empty_state( $gdc_empty_text, 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Returns a CSS class name corresponding to the severity of a log line.
	 *
	 * @param string $line Single log line text.
	 * @return string CSS class handle (e.g. "gdc-log-fatal", "gdc-log-warning").
	 */
	public static function classify_line( string $line ): string {
		$lower = strtolower( $line );

		if ( strpos( $lower, 'fatal error' ) !== false || strpos( $lower, 'parse error' ) !== false ) {
			return 'gdc-log-fatal';
		}

		if ( strpos( $lower, 'php warning' ) !== false ) {
			return 'gdc-log-warning';
		}

		if ( strpos( $lower, 'php notice' ) !== false || strpos( $lower, 'php deprecated' ) !== false ) {
			return 'gdc-log-notice';
		}

		if ( strpos( $lower, 'wp-cron' ) !== false || strpos( $lower, 'cron' ) !== false ) {
			return 'gdc-log-cron';
		}

		return 'gdc-log-info';
	}
}


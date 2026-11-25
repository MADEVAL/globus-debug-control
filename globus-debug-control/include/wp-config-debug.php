<?php
/**
 * GLOBUS Debug Control - WP-Config Debug
 *
 * Reads and writes WP_DEBUG-related constants in the active wp-config.php file.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and modifies WP_DEBUG constants in wp-config.php.
 *
 * @package GlobusDebugControl
 */
final class GDC_WpConfig_Debug {

	private const NONCE_ACTION = 'gdc_wp_config_debug';
	private const QUICK_NONCE  = 'gdc_quick_debug_enable';

	private const CONSTANTS = array(
		'WP_DEBUG'         => array(
			'type'  => 'bool',
			'label' => 'WP_DEBUG',
		),
		'WP_DEBUG_LOG'     => array(
			'type'  => 'bool_or_string',
			'label' => 'WP_DEBUG_LOG',
		),
		'WP_DEBUG_DISPLAY' => array(
			'type'  => 'bool',
			'label' => 'WP_DEBUG_DISPLAY',
		),
		'SCRIPT_DEBUG'     => array(
			'type'  => 'bool',
			'label' => 'SCRIPT_DEBUG',
		),
		'SAVEQUERIES'      => array(
			'type'  => 'bool',
			'label' => 'SAVEQUERIES',
		),
	);

	/**
	 * Locates the active wp-config.php file.
	 *
	 * @return string Absolute path to the file, or empty string if not found.
	 */
	public static function find_wp_config(): string {
		$path = ABSPATH . 'wp-config.php';

		if ( file_exists( $path ) ) {
			return $path;
		}

		$parent = dirname( ABSPATH ) . '/wp-config.php';

		if ( file_exists( $parent ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent;
		}

		return '';
	}

	/**
	 * Parses WP_DEBUG-related define() calls from the given config file.
	 *
	 * @param string $config_path Absolute path to wp-config.php.
	 * @return array<string, array<string, mixed>> Keyed by constant name.
	 */
	public static function read_constants( string $config_path ): array {
		if ( ! file_exists( $config_path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = @file_get_contents( $config_path );

		if ( false === $content ) {
			return array();
		}

		$active = preg_replace( '#/\*.*?\*/#s', '', $content );
		if ( null === $active ) {
			$active = $content;
		}

		$result = array();

		foreach ( self::CONSTANTS as $name => $meta ) {
			$pattern = '/^\s*define\s*\(\s*[\'"\']' . preg_quote( $name, '/' ) . '[\'"\']\s*,\s*(.+?)\s*\)\s*;/m';

			if ( preg_match( $pattern, $active, $m ) ) {
				$raw             = trim( $m[1] );
				$result[ $name ] = array(
					'defined' => true,
					'raw'     => $raw,
					'value'   => self::parse_raw_value( $raw ),
					'label'   => $meta['label'],
					'type'    => $meta['type'],
				);
			} else {
				$result[ $name ] = array(
					'defined' => false,
					'raw'     => '',
					'value'   => null,
					'label'   => $meta['label'],
					'type'    => $meta['type'],
				);
			}
		}

		return $result;
	}

	/**
	 * Updates or inserts a define() call in wp-config.php.
	 *
	 * @param string $config_path   Absolute path to wp-config.php.
	 * @param string $name          Constant name to set.
	 * @param string $new_raw_value Raw PHP value string (e.g. "true", "'some/path'").
	 * @return bool True on success.
	 */
	public static function set_constant( string $config_path, string $name, string $new_raw_value ): bool {
		if ( ! array_key_exists( $name, self::CONSTANTS ) ) {
			return false;
		}

		if ( ! file_exists( $config_path ) || ! wp_is_writable( $config_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = @file_get_contents( $config_path );

		if ( false === $content ) {
			return false;
		}

		$new_line_body  = "define( '" . $name . "', " . $new_raw_value . ' );';
		$define_pattern = '/^(\s*)define\s*\(\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]\s*,\s*.+?\s*\)\s*;/';
		$lines          = explode( "\n", $content );
		$in_block       = false;
		$replaced       = false;
		$out            = array();

		foreach ( $lines as $line ) {
			if ( $in_block ) {
				$out[] = $line;
				if ( strpos( $line, '*/' ) !== false ) {
					$in_block = false;
				}
				continue;
			}

			$open  = strpos( $line, '/*' );
			$close = strpos( $line, '*/' );
			if ( false !== $open && ( false === $close || $close < $open ) ) {
				$in_block = true;
				$out[]    = $line;
				continue;
			}

			$trimmed = ltrim( $line );
			if ( substr( $trimmed, 0, 2 ) === '//' || substr( $trimmed, 0, 1 ) === '#' ) {
				$out[] = $line;
				continue;
			}

			if ( ! $replaced && preg_match( $define_pattern, $line, $match ) ) {
				$out[]    = $match[1] . $new_line_body;
				$replaced = true;
				continue;
			}

			$out[] = $line;
		}

		$output = implode( "\n", $out );

		if ( ! $replaced ) {
			$stop = "/* That's all, stop editing!";
			$absp = '/** Absolute path to the WordPress directory.';

			if ( strpos( $output, $stop ) !== false ) {
				$output = str_replace( $stop, $new_line_body . "\n" . $stop, $output );
			} elseif ( strpos( $output, $absp ) !== false ) {
				$output = str_replace( $absp, $new_line_body . "\n" . $absp, $output );
			} else {
				$output .= "\n" . $new_line_body;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== @file_put_contents( $config_path, $output );
	}

	/**
	 * Creates a timestamped backup copy of wp-config.php before a write.
	 *
	 * @param string $config_path Absolute path to wp-config.php.
	 * @return string Backup path, or empty string when no backup was created.
	 */
	public static function backup_wp_config( string $config_path ): string {
		if ( '' === $config_path || ! file_exists( $config_path ) ) {
			return '';
		}

		$backup_path = $config_path . '.gdc-backup-' . gmdate( 'Ymd-His' );

		if ( file_exists( $backup_path ) ) {
			return $backup_path;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_copy -- Backup copy of wp-config.php before modification.
		return @copy( $config_path, $backup_path ) ? $backup_path : '';
	}

	/**
	 * Parses a raw define() value string to a boolean.
	 *
	 * @param string $raw Raw value from source (e.g. "true", "1", "false").
	 * @return bool Parsed boolean value.
	 */
	public static function parse_raw_value( string $raw ): bool {
		$lower = strtolower( trim( $raw ) );

		if ( 'true' === $lower || '1' === $lower ) {
			return true;
		}

		return false;
	}

	/**
	 * Handles the settings form POST, validates the nonce, and saves constants.
	 */
	public static function handle_action(): void {
		if ( ! isset( $_POST['gdc_wpcfg_save'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			return;
		}

		$config_path = self::find_wp_config();

		if ( empty( $config_path ) || ! wp_is_writable( $config_path ) ) {
			wp_safe_redirect(
				GDC_UI::module_url(
					'wpcfg',
					array( 'gdc_cfg_error' => 'nowrite' )
				)
			);
			exit;
		}

		$fields = isset( $_POST['gdc_wpcfg'] ) && is_array( $_POST['gdc_wpcfg'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['gdc_wpcfg'] ) )
			: array();

		$current_constants = self::read_constants( $config_path );

		// Keep a timestamped backup copy before the first write so the config
		// can always be restored if a mistake is made.
		self::backup_wp_config( $config_path );

		foreach ( self::CONSTANTS as $name => $meta ) {
			$is_checked = isset( $fields[ $name ] ) && 'true' === $fields[ $name ];
			$is_defined = ! empty( $current_constants[ $name ]['defined'] );

			if ( ! $is_checked && ! $is_defined ) {
				continue;
			}

			if ( 'WP_DEBUG_LOG' === $name && $is_checked && isset( $fields['WP_DEBUG_LOG_value'] ) ) {
				$str = sanitize_text_field( wp_unslash( $fields['WP_DEBUG_LOG_value'] ) );
				if ( '' !== $str ) {
					self::set_constant( $config_path, $name, "'" . addslashes( $str ) . "'" );
					continue;
				}
			}

			self::set_constant( $config_path, $name, $is_checked ? 'true' : 'false' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'globus-debug-control',
					'tab'           => 'wpcfg',
					'gdc_cfg_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Returns true when PHP currently reports errors, i.e. WP_DEBUG is on.
	 *
	 * @return bool
	 */
	public static function is_wp_debug_on(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Returns the raw WP_DEBUG_LOG value, or null when the constant is absent.
	 *
	 * @return bool|string|null
	 */
	public static function get_wp_debug_log_value() {
		return defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : null;
	}

	/**
	 * Returns true when WP_DEBUG_LOG points at a file (true or a path).
	 *
	 * @return bool
	 */
	public static function is_wp_debug_log_on(): bool {
		$value = self::get_wp_debug_log_value();

		return true === $value || 1 === $value || ( is_string( $value ) && '' !== $value );
	}

	/**
	 * Enables WP_DEBUG and WP_DEBUG_LOG in wp-config.php.
	 *
	 * Shared by the admin one-click action and the MCP ability. Creates a
	 * timestamped backup first, preserves an existing custom WP_DEBUG_LOG
	 * path, and turns WP_DEBUG_DISPLAY off only on the initial switch.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function enable_debug_logging() {
		$config_path = self::find_wp_config();

		if ( '' === $config_path || ! wp_is_writable( $config_path ) ) {
			return new \WP_Error( 'gdc_wp_config_not_writable', __( 'wp-config.php is missing or not writable.', 'globus-debug-control' ) );
		}

		$was_debug_on = self::is_wp_debug_on();
		$log_was_on   = self::is_wp_debug_log_on();
		$backup       = self::backup_wp_config( $config_path );
		$updated      = array();

		if ( ! self::set_constant( $config_path, 'WP_DEBUG', 'true' ) ) {
			return new \WP_Error( 'gdc_wp_config_write_failed', __( 'Could not update WP_DEBUG in wp-config.php.', 'globus-debug-control' ) );
		}
		$updated['WP_DEBUG'] = true;

		if ( ! $log_was_on ) {
			if ( ! self::set_constant( $config_path, 'WP_DEBUG_LOG', 'true' ) ) {
				return new \WP_Error( 'gdc_wp_config_write_failed', __( 'Could not update WP_DEBUG_LOG in wp-config.php.', 'globus-debug-control' ) );
			}
			$updated['WP_DEBUG_LOG'] = true;
		}

		// Only hide on-screen errors on the initial switch. Do not overwrite an
		// explicit WP_DEBUG_DISPLAY choice the user already made.
		if ( ! $was_debug_on && self::set_constant( $config_path, 'WP_DEBUG_DISPLAY', 'false' ) ) {
			$updated['WP_DEBUG_DISPLAY'] = false;
		}

		return array(
			'path'      => $config_path,
			'backup'    => $backup,
			'updated'   => $updated,
			'constants' => self::read_constants( $config_path ),
		);
	}

	/**
	 * Handles the one-click "enable debug logging" form.
	 */
	public static function handle_quick_enable_action(): void {
		if ( ! isset( $_POST['gdc_quick_debug'] ) ) {
			return;
		}

		check_admin_referer( self::QUICK_NONCE );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			return;
		}

		$return_tab = isset( $_POST['gdc_return_tab'] ) ? sanitize_key( wp_unslash( $_POST['gdc_return_tab'] ) ) : 'wpcfg';
		if ( ! in_array( $return_tab, array( 'settings', 'wpcfg', 'log' ), true ) ) {
			$return_tab = 'wpcfg';
		}

		$result = self::enable_debug_logging();

		$args = array();

		if ( is_wp_error( $result ) ) {
			$args['gdc_cfg_error'] = 'nowrite';
		} else {
			$args['gdc_debug_enabled'] = '1';
		}

		wp_safe_redirect( GDC_UI::module_url( $return_tab, $args ) );
		exit;
	}

	/**
	 * Renders a warning plus a one-click fix when debug constants are missing.
	 *
	 * Safe to call outside the settings <form>: the action is its own form.
	 *
	 * @param string $return_tab Tab to return to after the action.
	 */
	public static function render_dependency_notice( string $return_tab = 'wpcfg' ): void {
		$wp_debug     = self::is_wp_debug_on();
		$wp_debug_log = self::is_wp_debug_log_on();

		if ( $wp_debug && $wp_debug_log ) {
			return;
		}

		$config_path = self::find_wp_config();
		$writable    = ! empty( $config_path ) && wp_is_writable( $config_path );
		$wpcfg_url   = GDC_UI::module_url( 'wpcfg' );
		?>
		<div class="notice <?php echo $wp_debug ? 'notice-info' : 'notice-warning'; ?> gdc-debug-dependency">
			<p>
				<strong>
					<?php
					if ( ! $wp_debug ) {
						esc_html_e( 'WP_DEBUG is off.', 'globus-debug-control' );
					} else {
						esc_html_e( 'WP_DEBUG_LOG is off.', 'globus-debug-control' );
					}
					?>
				</strong>
				<?php
				if ( ! $wp_debug ) {
					esc_html_e( 'PHP does not report debug errors, so error filtering and test errors have no effect until WP_DEBUG is enabled.', 'globus-debug-control' );
				} else {
					esc_html_e( 'WordPress does not write debug.log on its own. The plugin can still log to the path configured below.', 'globus-debug-control' );
				}
				?>
			</p>
			<?php if ( $writable ) : ?>
				<form method="post" class="gdc-quick-debug-form">
					<?php wp_nonce_field( self::QUICK_NONCE ); ?>
					<input type="hidden" name="gdc_quick_debug" value="1">
					<input type="hidden" name="gdc_return_tab" value="<?php echo esc_attr( $return_tab ); ?>">
					<button type="submit" class="button button-primary">
						<?php
						if ( ! $wp_debug ) {
							esc_html_e( 'Enable WP_DEBUG + WP_DEBUG_LOG', 'globus-debug-control' );
						} else {
							esc_html_e( 'Enable WP_DEBUG_LOG', 'globus-debug-control' );
						}
						?>
					</button>
					<span class="description">
						<?php esc_html_e( 'Writes to wp-config.php after creating a backup. WP_DEBUG_DISPLAY is switched off when debug mode is enabled for the first time.', 'globus-debug-control' ); ?>
					</span>
				</form>
			<?php else : ?>
				<p>
					<a href="<?php echo esc_url( $wpcfg_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Open WP_DEBUG settings', 'globus-debug-control' ); ?>
					</a>
					<span class="description"><?php esc_html_e( 'wp-config.php is not writable, so the constants must be enabled manually.', 'globus-debug-control' ); ?></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prints compact WP_DEBUG / WP_DEBUG_LOG status badges.
	 */
	public static function render_status_badges(): void {
		$wp_debug     = self::is_wp_debug_on();
		$wp_debug_log = self::is_wp_debug_log_on();
		?>
		<span class="gdc-wpcfg-status">
			<span class="gdc-badge <?php echo $wp_debug ? 'gdc-badge-success' : 'gdc-badge-warning'; ?>">
				WP_DEBUG: <?php echo $wp_debug ? esc_html__( 'On', 'globus-debug-control' ) : esc_html__( 'Off', 'globus-debug-control' ); ?>
			</span>
			<span class="gdc-badge <?php echo $wp_debug_log ? 'gdc-badge-success' : 'gdc-badge-warning'; ?>">
				WP_DEBUG_LOG: <?php echo $wp_debug_log ? esc_html__( 'On', 'globus-debug-control' ) : esc_html__( 'Off', 'globus-debug-control' ); ?>
			</span>
		</span>
		<?php
	}

	/**
	 * Renders the WP-Config Debug settings panel.
	 */
	public static function render(): void {
		$config_path = self::find_wp_config();
		$writable    = ! empty( $config_path ) && wp_is_writable( $config_path );
		$constants   = ! empty( $config_path ) ? self::read_constants( $config_path ) : array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after a verified redirect.
		if ( isset( $_GET['gdc_cfg_saved'] ) ) {
			echo GDC_UI::alert( esc_html__( 'wp-config.php updated.', 'globus-debug-control' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after a verified redirect.
		if ( isset( $_GET['gdc_cfg_error'] ) ) {
			echo GDC_UI::alert( esc_html__( 'wp-config.php is not writable.', 'globus-debug-control' ), 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
		}

		if ( ! self::is_wp_debug_on() || ! self::is_wp_debug_log_on() ) {
			echo '<div class="gdc-alert-wrap gdc-dependency">';
			self::render_dependency_notice( 'wpcfg' );
			echo '</div>';
		}
		?>
		<?php if ( empty( $config_path ) ) : ?>
			<div class="gdc-alert gdc-alert--warning gdc-mb-3h">
				<?php esc_html_e( 'wp-config.php not found. The constants cannot be read or changed.', 'globus-debug-control' ); ?>
			</div>
		<?php elseif ( ! $writable ) : ?>
			<div class="gdc-alert gdc-alert--warning gdc-mb-3h">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: file path */
						__( '%s is not writable. Changes cannot be saved until the file permissions are fixed.', 'globus-debug-control' ),
						'<code>' . esc_html( $config_path ) . '</code>'
					),
					array( 'code' => array() )
				);
				?>
			</div>
		<?php endif; ?>

		<div class="gdc-card gdc-mb-3h">
			<div class="gdc-card-pad gdc-row gdc-row--between gdc-g-3 gdc-row--wrap">
				<div class="gdc-fill">
					<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'wp-config.php constants', 'globus-debug-control' ); ?></h2>
					<?php if ( ! empty( $config_path ) ) : ?>
						<div class="gdc-fs-2xs gdc-dim gdc-mono gdc-mt-1"><?php echo esc_html( $config_path ); ?></div>
					<?php endif; ?>
				</div>
				<?php echo GDC_UI::chip( $writable ? __( 'Writable', 'globus-debug-control' ) : __( 'Read-only', 'globus-debug-control' ), $writable ? 'green' : 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
			</div>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="gdc_wpcfg_save" value="1">

				<div class="gdc-divider"></div>

				<div class="gdc-col">
					<?php
					foreach ( $constants as $name => $info ) :
						$raw           = (string) ( $info['raw'] ?? '' );
						$is_string_log = ( 'WP_DEBUG_LOG' === $name && '' !== $raw && ( "'" === $raw[0] || '"' === $raw[0] ) );
						$is_true       = ! empty( $info['defined'] ) && ( ! empty( $info['value'] ) || $is_string_log );
						?>
						<div class="gdc-togglerow">
							<div class="gdc-togglerow-main">
								<div class="gdc-togglerow-label"><code><?php echo esc_html( (string) $name ); ?></code></div>
								<div class="gdc-togglerow-desc">
									<?php if ( empty( $info['defined'] ) ) : ?>
										<?php esc_html_e( 'Not defined yet. It will be added on save.', 'globus-debug-control' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Current:', 'globus-debug-control' ); ?> <code><?php echo esc_html( $raw ); ?></code>
									<?php endif; ?>
								</div>

								<?php if ( 'WP_DEBUG_LOG' === $name && $writable ) : ?>
									<label class="gdc-field gdc-mt-2h" for="gdc-wpcfg-log-path">
										<?php echo GDC_UI::field_label( __( 'Custom log file path', 'globus-debug-control' ), __( 'Relative to wp-content or an absolute path. Leave empty to write to the default debug.log.', 'globus-debug-control' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
										<input
											type="text"
											id="gdc-wpcfg-log-path"
											class="gdc-input gdc-input--mono"
											name="gdc_wpcfg[WP_DEBUG_LOG_value]"
											value=""
											placeholder="<?php esc_attr_e( 'e.g. custom/debug.log - leave empty for true', 'globus-debug-control' ); ?>"
										/>
									</label>
								<?php endif; ?>
							</div>

							<?php echo GDC_UI::switch_control( 'gdc_wpcfg[' . $name . ']', $is_true, 'gdc-wpcfg-' . strtolower( (string) $name ), '', ! $writable, (string) $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $writable ) : ?>
					<div class="gdc-divider"></div>
					<div class="gdc-card-pad gdc-row gdc-row--between gdc-g-3 gdc-row--wrap">
						<span class="gdc-dim gdc-fs-xs"><?php esc_html_e( 'A timestamped backup is created before every write.', 'globus-debug-control' ); ?></span>
						<button type="submit" class="gdc-btn gdc-btn--pri">
							<?php echo GDC_UI::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
							<?php esc_html_e( 'Save to wp-config.php', 'globus-debug-control' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</form>
		</div>

		<div class="gdc-grid gdc-grid--2">
			<div class="gdc-card gdc-card-pad">
				<h2 class="gdc-section-title gdc-card-title gdc-mb-3"><?php esc_html_e( 'How writes work', 'globus-debug-control' ); ?></h2>
				<div class="gdc-col gdc-g-2 gdc-fs-sm">
					<div class="gdc-row gdc-g-2 gdc-row--top">
						<?php echo GDC_UI::icon( 'shieldCheck', 'gdc-ico--sm gdc-tx-green' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<span><?php esc_html_e( 'A backup copy named wp-config.php.gdc-backup-YYYYmmdd-HHMMSS is written next to the file.', 'globus-debug-control' ); ?></span>
					</div>
					<div class="gdc-row gdc-g-2 gdc-row--top">
						<?php echo GDC_UI::icon( 'eye', 'gdc-ico--sm gdc-tx-blue' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<span><?php esc_html_e( 'Commented-out defines are never touched. Only the first active define is updated.', 'globus-debug-control' ); ?></span>
					</div>
					<div class="gdc-row gdc-g-2 gdc-row--top">
						<?php echo GDC_UI::icon( 'lock', 'gdc-ico--sm gdc-tx2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<span><?php esc_html_e( 'Missing constants are inserted before the "stop editing" marker.', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			</div>

			<div class="gdc-card gdc-card-pad">
				<h2 class="gdc-section-title gdc-card-title gdc-mb-3"><?php esc_html_e( 'Related screens', 'globus-debug-control' ); ?></h2>
				<div class="gdc-col gdc-g-1">
					<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>"><?php echo GDC_UI::icon( 'code', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?> <?php esc_html_e( 'Error log', 'globus-debug-control' ); ?></a>
					<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'constants' ) ); ?>"><?php echo GDC_UI::icon( 'list', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?> <?php esc_html_e( 'All PHP constants', 'globus-debug-control' ); ?></a>
					<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'settings' ) ); ?>"><?php echo GDC_UI::icon( 'gear', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?> <?php esc_html_e( 'Plugin settings', 'globus-debug-control' ); ?></a>
				</div>
				<div class="gdc-divider gdc-mt-3 gdc-fs-xs gdc-dim">
					<?php esc_html_e( 'Runtime values are read from the constants above, not from the plugin options.', 'globus-debug-control' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns the current value of WP_DEBUG as read from wp-config.php.
	 *
	 * @return bool|null True/false for the defined value, null if constant is absent.
	 */
	public static function get_wp_debug_status(): ?bool {
		$config_path = self::find_wp_config();

		if ( empty( $config_path ) ) {
			return null;
		}

		$constants = self::read_constants( $config_path );

		if ( empty( $constants['WP_DEBUG']['defined'] ) ) {
			return null;
		}

		return $constants['WP_DEBUG']['value'];
	}
}

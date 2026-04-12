<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDC_WpConfig_Debug {

	private const NONCE_ACTION = 'gdc_wp_config_debug';

	private const CONSTANTS = [
		'WP_DEBUG'         => [ 'type' => 'bool', 'label' => 'WP_DEBUG' ],
		'WP_DEBUG_LOG'     => [ 'type' => 'bool_or_string', 'label' => 'WP_DEBUG_LOG' ],
		'WP_DEBUG_DISPLAY' => [ 'type' => 'bool', 'label' => 'WP_DEBUG_DISPLAY' ],
		'SCRIPT_DEBUG'     => [ 'type' => 'bool', 'label' => 'SCRIPT_DEBUG' ],
		'SAVEQUERIES'      => [ 'type' => 'bool', 'label' => 'SAVEQUERIES' ],
	];

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

	public static function read_constants( string $config_path ): array {
		if ( ! file_exists( $config_path ) ) {
			return [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = @file_get_contents( $config_path );

		if ( false === $content ) {
			return [];
		}

		$active = preg_replace( '#/\*.*?\*/#s', '', $content );
		if ( null === $active ) {
			$active = $content;
		}

		$result = [];

		foreach ( self::CONSTANTS as $name => $meta ) {
			$pattern = '/^\s*define\s*\(\s*[\'"\']' . preg_quote( $name, '/' ) . '[\'"\']\s*,\s*(.+?)\s*\)\s*;/m';

			if ( preg_match( $pattern, $active, $m ) ) {
				$raw = trim( $m[1] );
				$result[ $name ] = [
					'defined' => true,
					'raw'     => $raw,
					'value'   => self::parse_raw_value( $raw ),
					'label'   => $meta['label'],
					'type'    => $meta['type'],
				];
			} else {
				$result[ $name ] = [
					'defined' => false,
					'raw'     => '',
					'value'   => null,
					'label'   => $meta['label'],
					'type'    => $meta['type'],
				];
			}
		}

		return $result;
	}

	public static function set_constant( string $config_path, string $name, string $new_raw_value ): bool {
		if ( ! array_key_exists( $name, self::CONSTANTS ) ) {
			return false;
		}

		if ( ! file_exists( $config_path ) || ! is_writable( $config_path ) ) {
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
		$out            = [];

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
			if ( $open !== false && ( $close === false || $close < $open ) ) {
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

	public static function parse_raw_value( string $raw ): bool {
		$lower = strtolower( trim( $raw ) );

		if ( 'true' === $lower || '1' === $lower ) {
			return true;
		}

		return false;
	}

	public static function handle_action(): void {
		if ( ! isset( $_POST['gdc_wpcfg_save'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config_path = self::find_wp_config();

		if ( empty( $config_path ) || ! is_writable( $config_path ) ) {
			wp_safe_redirect(
				add_query_arg(
					[ 'page' => 'globus-debug-control', 'tab' => 'wpcfg', 'gdc_cfg_error' => 'nowrite' ],
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		$fields = isset( $_POST['gdc_wpcfg'] ) && is_array( $_POST['gdc_wpcfg'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['gdc_wpcfg'] ) )
			: [];

		$current_constants = self::read_constants( $config_path );

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
				[ 'page' => 'globus-debug-control', 'tab' => 'wpcfg', 'gdc_cfg_saved' => '1' ],
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function render(): void {
		$config_path = self::find_wp_config();
		$writable    = ! empty( $config_path ) && is_writable( $config_path );
		$constants   = ! empty( $config_path ) ? self::read_constants( $config_path ) : [];

		if ( isset( $_GET['gdc_cfg_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'wp-config.php updated.', 'globus-debug-control' ) . '</p></div>';
		}

		if ( isset( $_GET['gdc_cfg_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html__( 'wp-config.php is not writable.', 'globus-debug-control' ) . '</p></div>';
		}

		?>
		<div class="gdc-wpcfg-wrap">
			<?php if ( empty( $config_path ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'wp-config.php not found.', 'globus-debug-control' ); ?></p></div>
			<?php elseif ( ! $writable ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: file path */
								__( '<strong>%s</strong> is not writable. Changes cannot be saved.', 'globus-debug-control' ),
								esc_html( $config_path )
							),
							[ 'strong' => [] ]
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: file path */
							__( 'Editing: <code>%s</code>', 'globus-debug-control' ),
							esc_html( $config_path )
						),
						[ 'code' => [] ]
					);
					?>
				</p>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="gdc_wpcfg_save" value="1">

				<table class="form-table" role="presentation">
					<?php foreach ( $constants as $name => $info ) :
						$is_true = $info['defined'] && $info['value'];
						?>
					<tr>
						<th scope="row">
							<label>
								<code><?php echo esc_html( $name ); ?></code>
							</label>
						</th>
						<td>
							<label class="gdc-toggle-label">
								<input
									type="checkbox"
									name="gdc_wpcfg[<?php echo esc_attr( $name ); ?>]"
									value="true"
									<?php checked( $is_true ); ?>
									<?php disabled( ! $writable ); ?>
									class="gdc-wpcfg-checkbox"
								>
								<?php if ( $is_true ) : ?>
									<span class="gdc-status-badge gdc-status-on">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e( 'Enabled', 'globus-debug-control' ); ?>
									</span>
								<?php else : ?>
									<span class="gdc-status-badge gdc-status-off">
										<span class="dashicons dashicons-no"></span>
										<?php esc_html_e( 'Disabled', 'globus-debug-control' ); ?>
									</span>
								<?php endif; ?>
							</label>
							<?php if ( ! $info['defined'] ) : ?>
								<span style="color:#646970;margin-left:10px;font-size:12px;"><?php esc_html_e( '(not defined, will be added)', 'globus-debug-control' ); ?></span>
							<?php else : ?>
								<span style="color:#646970;margin-left:10px;font-size:12px;"><?php echo esc_html__( 'Current:', 'globus-debug-control' ) . ' <code>' . esc_html( $info['raw'] ) . '</code>'; ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<?php if ( $writable ) : ?>
					<p class="submit">
						<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save to wp-config.php', 'globus-debug-control' ); ?>">
					</p>
				<?php endif; ?>
			</form>
		</div>
		<style>
			.gdc-status-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600; }
			.gdc-status-badge .dashicons { font-size:16px;width:16px;height:16px; }
			.gdc-status-on { background:#edfaef;color:#1a7c36;border:1px solid #a7d9aa; }
			.gdc-status-off { background:#f6f7f7;color:#646970;border:1px solid #dcdcde; }
			.gdc-toggle-label { cursor:pointer;display:inline-flex;align-items:center;gap:8px; }
		</style>
		<script>
		(function(){
			document.querySelectorAll('.gdc-wpcfg-checkbox').forEach(function(cb){
				cb.addEventListener('change', function(){
					var badge = this.nextElementSibling;
					if (this.checked) {
						badge.className = 'gdc-status-badge gdc-status-on';
						badge.innerHTML = '<span class="dashicons dashicons-yes-alt"></span><?php echo esc_js( __( 'Enabled', 'globus-debug-control' ) ); ?>';
					} else {
						badge.className = 'gdc-status-badge gdc-status-off';
						badge.innerHTML = '<span class="dashicons dashicons-no"></span><?php echo esc_js( __( 'Disabled', 'globus-debug-control' ) ); ?>';
					}
				});
			});
		})();
		</script>
		<?php
	}

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

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDC_Log_Viewer {

	private const LINES_PER_PAGE = 200;
	private const NONCE_ACTION   = 'gdc_log_viewer';
	private const NONCE_CLEAR    = 'gdc_log_clear';

	public static function get_log_path(): string {
		$plugin  = Globus_Debug_Control::instance();
		$opts    = $plugin->get_options();
		$reflect = new ReflectionMethod( $plugin, 'resolve_log_path' );
		$path    = $reflect->invoke( $plugin, $opts );

		if ( empty( $path ) ) {
			$path = WP_CONTENT_DIR . '/debug.log';
		}

		return $path;
	}

	public static function handle_clear_action(): void {
		if ( ! isset( $_POST['gdc_clear_log'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE_CLEAR );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$path = self::get_log_path();

		if ( file_exists( $path ) && is_writable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, '' );
		}

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'globus-debug-control', 'tab' => 'log', 'cleared' => '1' ],
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function render(): void {
		$path  = self::get_log_path();
		$page  = max( 1, isset( $_GET['logpage'] ) ? (int) $_GET['logpage'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$exists = file_exists( $path );
		$size   = $exists ? @filesize( $path ) : 0;
		$lines  = [];
		$total  = 0;

		if ( $exists && $size > 0 ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content  = @file_get_contents( $path );
			$all      = $content !== false ? array_reverse( explode( "\n", rtrim( $content ) ) ) : [];
			$total    = count( $all );
			$offset   = ( $page - 1 ) * self::LINES_PER_PAGE;
			$lines    = array_slice( $all, $offset, self::LINES_PER_PAGE );
		}

		$total_pages = $total > 0 ? (int) ceil( $total / self::LINES_PER_PAGE ) : 1;

		if ( isset( $_GET['cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log file cleared.', 'globus-debug-control' ) . '</p></div>';
		}
		?>
		<div class="gdc-log-viewer">
			<div class="gdc-log-toolbar">
				<div class="gdc-log-info">
					<?php if ( $exists ) : ?>
						<span class="gdc-log-path"><code><?php echo esc_html( $path ); ?></code></span>
						<span class="gdc-log-size"><?php echo esc_html( size_format( (int) $size ) ); ?></span>
						<span class="gdc-log-lines"><?php echo esc_html( sprintf( _n( '%s line', '%s lines', $total, 'globus-debug-control' ), number_format_i18n( $total ) ) ); ?></span>
					<?php else : ?>
						<span class="gdc-log-empty"><?php esc_html_e( 'Log file does not exist.', 'globus-debug-control' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $exists && $size > 0 ) : ?>
				<form method="post" class="gdc-clear-form">
					<?php wp_nonce_field( self::NONCE_CLEAR ); ?>
					<input type="hidden" name="gdc_clear_log" value="1">
					<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Clear the log file?', 'globus-debug-control' ); ?>')">
						<?php esc_html_e( 'Clear Log', 'globus-debug-control' ); ?>
					</button>
				</form>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $lines ) ) : ?>
			<div class="gdc-log-entries">
				<?php foreach ( $lines as $line ) : ?>
					<?php if ( trim( $line ) === '' ) continue; ?>
					<div class="gdc-log-line <?php echo esc_attr( self::classify_line( $line ) ); ?>">
						<span class="gdc-log-text"><?php echo esc_html( $line ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="gdc-log-pagination tablenav">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php echo esc_html( sprintf( _n( '%s line', '%s lines', $total, 'globus-debug-control' ), number_format_i18n( $total ) ) ); ?>
					</span>
					<?php
					$base_url = add_query_arg( [ 'page' => 'globus-debug-control', 'tab' => 'log' ], admin_url( 'options-general.php' ) );
					if ( $page > 1 ) {
						echo '<a class="prev-page button" href="' . esc_url( add_query_arg( 'logpage', $page - 1, $base_url ) ) . '">‹</a>';
					}
					echo '<span class="paging-input">' . esc_html( $page ) . ' / ' . esc_html( $total_pages ) . '</span>';
					if ( $page < $total_pages ) {
						echo '<a class="next-page button" href="' . esc_url( add_query_arg( 'logpage', $page + 1, $base_url ) ) . '">›</a>';
					}
					?>
				</div>
			</div>
			<?php endif; ?>

			<?php else : ?>
			<div class="gdc-log-empty-state">
				<p><?php esc_html_e( 'Log file is empty.', 'globus-debug-control' ); ?></p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

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

	public static function get_inline_styles(): string {
		return '
			.gdc-log-viewer { margin-top: 16px; }
			.gdc-log-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #f6f7f7; border: 1px solid #dcdcde; border-bottom: none; border-radius: 2px 2px 0 0; flex-wrap: wrap; gap: 8px; }
			.gdc-log-info { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 13px; }
			.gdc-log-size, .gdc-log-lines { color: #646970; }
			.gdc-log-empty-state { padding: 24px; text-align: center; background: #fff; border: 1px solid #dcdcde; color: #646970; }
			.gdc-log-entries { border: 1px solid #dcdcde; background: #1d2327; border-radius: 0 0 2px 2px; max-height: 600px; overflow-y: auto; font-family: Consolas, "Courier New", monospace; font-size: 12px; line-height: 1.6; }
			.gdc-log-line { padding: 2px 12px; border-bottom: 1px solid #2c3338; word-break: break-all; }
			.gdc-log-line:last-child { border-bottom: none; }
			.gdc-log-text { color: #c3c4c7; }
			.gdc-log-fatal { background: #3b1212; }
			.gdc-log-fatal .gdc-log-text { color: #f86368; }
			.gdc-log-warning { background: #2a1f00; }
			.gdc-log-warning .gdc-log-text { color: #f0c33c; }
			.gdc-log-notice { background: #141e28; }
			.gdc-log-notice .gdc-log-text { color: #72aee6; }
			.gdc-log-cron { background: #0b2012; }
			.gdc-log-cron .gdc-log-text { color: #68de7c; }
			.gdc-log-pagination { padding: 8px 0; }
			.gdc-log-pagination .tablenav-pages { display: flex; align-items: center; gap: 8px; }
			.gdc-clear-form { display: inline; }
		';
	}
}

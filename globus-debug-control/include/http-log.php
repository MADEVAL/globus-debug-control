<?php
/**
 * GLOBUS Debug Control - HTTP API Log
 *
 * Captures outgoing WordPress HTTP API requests (wp_remote_*) via the
 * http_api_debug hook. Entries are stored in a transient (max 100, TTL 6h).
 * Capturing must be explicitly enabled and persists across page loads.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures and displays outgoing HTTP API requests made by WordPress.
 *
 * @package GlobusDebugControl
 */
final class GDC_Http_Log {

	/** Transient key for captured request entries. */
	private const TRANSIENT_LOG = 'gdc_http_log';

	/** Option key for the enabled/disabled flag. */
	private const OPT_CAPTURING = 'gdc_http_log_capturing';

	/** Maximum entries kept in the transient. */
	public const MAX_ENTRIES = 100;

	private const NONCE_CLEAR  = 'gdc_http_log_clear';
	private const NONCE_TOGGLE = 'gdc_http_log_toggle';

	/**
	 * Returns true when HTTP capturing is currently enabled.
	 */
	public static function is_capturing(): bool {
		return (bool) get_option( self::OPT_CAPTURING, false );
	}

	/**
	 * Enables or disables HTTP request capturing.
	 *
	 * @param bool $enabled Whether to enable capturing.
	 */
	public static function set_capturing( bool $enabled ): void {
		if ( $enabled ) {
			update_option( self::OPT_CAPTURING, 1, false );
		} else {
			delete_option( self::OPT_CAPTURING );
		}
	}

	/**
	 * Registers AJAX action hooks.
	 */
	public static function register_ajax_hooks(): void {
		add_action( 'wp_ajax_gdc_http_log_clear', array( self::class, 'ajax_clear' ) );
		add_action( 'wp_ajax_gdc_http_log_toggle', array( self::class, 'ajax_toggle' ) );
	}

	/**
	 * Callback for the http_api_debug action — captures one request entry.
	 *
	 * @param mixed  $response        WP HTTP response array or WP_Error.
	 * @param string $context         'response' or 'redirect'.
	 * @param string $transport_class  Transport class used.
	 * @param array  $args            Parsed request arguments.
	 * @param string $url             Request URL.
	 */
	public static function on_request( $response, string $context, string $transport_class, array $args, string $url ): void {
		if ( 'response' !== $context ) {
			return;
		}

		if ( is_wp_error( $response ) ) {
			$status    = 0;
			$error_msg = $response->get_error_message();
		} else {
			$status    = (int) wp_remote_retrieve_response_code( $response );
			$error_msg = '';
		}

		$entry = array(
			'ts'     => time(),
			'method' => strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' ),
			'url'    => $url,
			'status' => $status,
			'error'  => $error_msg,
		);

		$log = get_transient( self::TRANSIENT_LOG );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_ENTRIES );
		set_transient( self::TRANSIENT_LOG, $log, 6 * HOUR_IN_SECONDS );
	}

	/**
	 * Returns all captured log entries, newest first.
	 *
	 * @return array<int, array{ts: int, method: string, url: string, status: int, error: string}>
	 */
	public static function get_log(): array {
		$log = get_transient( self::TRANSIENT_LOG );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * AJAX handler: clears the captured HTTP log transient.
	 */
	public static function ajax_clear(): void {
		check_ajax_referer( self::NONCE_CLEAR );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		delete_transient( self::TRANSIENT_LOG );
		wp_send_json_success();
	}

	/**
	 * AJAX handler: enables or disables HTTP capturing.
	 */
	public static function ajax_toggle(): void {
		check_ajax_referer( self::NONCE_TOGGLE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$enable = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
		self::set_capturing( $enable );
		wp_send_json_success( array( 'capturing' => $enable ) );
	}

	/**
	 * Renders the HTTP log UI.
	 */
	public static function render(): void {
		$log       = self::get_log();
		$capturing = self::is_capturing();
		?>
		<script>var gdcHttp=
		<?php
		echo wp_json_encode(
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonceClear'  => wp_create_nonce( self::NONCE_CLEAR ),
				'nonceToggle' => wp_create_nonce( self::NONCE_TOGGLE ),
			)
		);
		?>
							;</script>

		<div class="gdc-tool-header">
			<div class="gdc-cron-summary">
				<div class="gdc-cron-summary-card <?php echo $capturing ? 'gdc-card-ok' : ''; ?>">
					<span class="dashicons dashicons-<?php echo $capturing ? 'controls-play' : 'controls-pause'; ?>"></span>
					<div>
						<strong><?php echo $capturing ? esc_html__( 'Capturing', 'globus-debug-control' ) : esc_html__( 'Paused', 'globus-debug-control' ); ?></strong>
						<span><?php esc_html_e( 'HTTP capture', 'globus-debug-control' ); ?></span>
					</div>
				</div>
				<div class="gdc-cron-summary-card">
					<span class="dashicons dashicons-cloud"></span>
					<div>
						<strong><?php echo esc_html( count( $log ) ); ?></strong>
						<span><?php esc_html_e( 'Requests logged', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			</div>
			<div class="gdc-log-actions">
				<button type="button" id="gdc-http-toggle" class="button button-secondary<?php echo $capturing ? ' gdc-http-capturing' : ''; ?>"
					data-capturing="<?php echo $capturing ? '1' : '0'; ?>">
					<span class="dashicons <?php echo $capturing ? 'dashicons-controls-pause' : 'dashicons-controls-play'; ?>"
						style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>
					<?php echo $capturing ? esc_html__( 'Stop capturing', 'globus-debug-control' ) : esc_html__( 'Start capturing', 'globus-debug-control' ); ?>
				</button>
				<?php if ( ! empty( $log ) ) : ?>
					<button type="button" id="gdc-http-clear" class="button button-secondary">
						<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>
						<?php esc_html_e( 'Clear log', 'globus-debug-control' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( empty( $log ) ) : ?>
			<div class="gdc-empty-state">
				<p>
					<?php
					if ( $capturing ) {
						esc_html_e( 'No requests captured yet. Browse other pages to record HTTP API calls.', 'globus-debug-control' );
					} else {
						esc_html_e( 'Capturing is disabled. Click "Start capturing" to begin recording outgoing HTTP requests.', 'globus-debug-control' );
					}
					?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat striped gdc-http-table">
				<thead>
					<tr>
						<th style="width:40px;">#</th>
						<th style="width:80px;"><?php esc_html_e( 'Time', 'globus-debug-control' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Method', 'globus-debug-control' ); ?></th>
						<th><?php esc_html_e( 'URL', 'globus-debug-control' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( 'Status', 'globus-debug-control' ); ?></th>
						<th style="width:200px;"><?php esc_html_e( 'Error', 'globus-debug-control' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $i => $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $i + 1 ); ?></td>
							<td><code style="font-size:11px;"><?php echo esc_html( gmdate( 'H:i:s', $entry['ts'] ) ); ?></code></td>
							<td><span class="gdc-http-method gdc-http-method-<?php echo esc_attr( strtolower( $entry['method'] ) ); ?>"><?php echo esc_html( $entry['method'] ); ?></span></td>
							<td class="gdc-http-url-cell"><code class="gdc-http-url" title="<?php echo esc_attr( $entry['url'] ); ?>"><?php echo esc_html( $entry['url'] ); ?></code></td>
							<td>
								<?php
								$s   = (int) $entry['status'];
								$cls = 0 === $s ? 'gdc-http-status-err'
									: ( $s >= 500 ? 'gdc-http-status-5xx'
									: ( $s >= 400 ? 'gdc-http-status-4xx'
									: ( $s >= 300 ? 'gdc-http-status-3xx' : 'gdc-http-status-2xx' ) ) );
								echo '<span class="gdc-http-status ' . esc_attr( $cls ) . '">' . esc_html( $s > 0 ? (string) $s : '—' ) . '</span>';
								?>
							</td>
							<td><?php echo esc_html( $entry['error'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}

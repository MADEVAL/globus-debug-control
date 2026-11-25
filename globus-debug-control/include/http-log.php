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

	/** Query parameter names whose values must be masked in logged URLs. */
	private const SENSITIVE_PARAMS = array(
		'key',
		'token',
		'secret',
		'password',
		'passwd',
		'auth',
		'api_key',
		'apikey',
		'access_token',
		'oauth_token',
		'client_secret',
		'sig',
		'signature',
		'code',
	);

	private const NONCE_CLEAR  = 'gdc_http_log_clear';
	private const NONCE_TOGGLE = 'gdc_http_log_toggle';

	/**
	 * Microtime start values keyed by request URL hash.
	 *
	 * @var array<string, float>
	 */
	private static array $start_times = array();

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
	 * Masks sensitive query parameter values in a URL before it is stored.
	 *
	 * @param string $url Raw request URL.
	 * @return string URL with sensitive parameter values replaced by '***'.
	 */
	public static function mask_url( string $url ): string {
		if ( false === strpos( $url, '?' ) ) {
			return $url;
		}

		list( $base, $query ) = array_pad( explode( '?', $url, 2 ), 2, '' );

		if ( '' === $query ) {
			return $url;
		}

		parse_str( $query, $params );

		foreach ( $params as $name => $value ) {
			$lower = strtolower( (string) $name );
			foreach ( self::SENSITIVE_PARAMS as $needle ) {
				if ( false !== strpos( $lower, $needle ) ) {
					$params[ $name ] = '***';
					break;
				}
			}
		}

		$result = $base . '?' . http_build_query( $params );

		// http_build_query() encodes '***' as %2A%2A%2A - restore the literal marker.
		return str_replace( '%2A%2A%2A', '***', $result );
	}

	/**
	 * Records the start time of an outgoing HTTP request.
	 *
	 * Registered on the pre_http_request filter while capturing is enabled so
	 * the request duration can be reported in the log.
	 *
	 * @param mixed  $pre  Short-circuit value (passed through unchanged).
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return mixed
	 */
	public static function record_start_time( $pre, array $args, string $url ) {
		self::$start_times[ md5( $url ) ] = microtime( true );
		return $pre;
	}

	/**
	 * Returns the elapsed time for a request URL since it started, or 0.0.
	 *
	 * @param string $url Request URL.
	 * @return float Seconds with microsecond precision.
	 */
	private static function duration_for( string $url ): float {
		$key = md5( $url );

		if ( ! isset( self::$start_times[ $key ] ) ) {
			return 0.0;
		}

		$duration = microtime( true ) - self::$start_times[ $key ];
		unset( self::$start_times[ $key ] );
		return $duration;
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
			'ts'        => time(),
			'method'    => strtoupper( isset( $args['method'] ) ? (string) $args['method'] : 'GET' ),
			'url'       => self::mask_url( $url ),
			'status'    => $status,
			'error'     => $error_msg,
			'transport' => $transport_class,
			'duration'  => self::duration_for( $url ),
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

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
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

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
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

		<div class="gdc-http-viewer">

			<div class="gdc-cron-summary gdc-grid gdc-grid--auto gdc-mb-3h">
				<div class="gdc-card gdc-kpi <?php echo $capturing ? 'gdc-card-ok' : ''; ?>">
					<div class="gdc-row gdc-row--between">
						<span class="gdc-kpi-label"><?php esc_html_e( 'HTTP capture', 'globus-debug-control' ); ?></span>
						<span class="<?php echo $capturing ? 'gdc-tx-green' : 'gdc-tx2'; ?>">
							<?php echo GDC_UI::icon( $capturing ? 'play' : 'stop', 'gdc-ico--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						</span>
					</div>
					<div class="gdc-kpi-n"><span class="gdc-stat gdc-stat--sm"><?php echo $capturing ? esc_html__( 'Capturing', 'globus-debug-control' ) : esc_html__( 'Paused', 'globus-debug-control' ); ?></span></div>
					<div class="gdc-kpi-sub"><?php esc_html_e( 'Outgoing API requests', 'globus-debug-control' ); ?></div>
				</div>

				<div class="gdc-card gdc-kpi">
					<div class="gdc-row gdc-row--between">
						<span class="gdc-kpi-label"><?php esc_html_e( 'Requests logged', 'globus-debug-control' ); ?></span>
						<span class="gdc-tx2"><?php echo GDC_UI::icon( 'globe', 'gdc-ico--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?></span>
					</div>
					<div class="gdc-kpi-n"><span class="gdc-stat gdc-stat--sm"><?php echo esc_html( number_format_i18n( count( $log ) ) ); ?></span></div>
					<div class="gdc-kpi-sub"><?php esc_html_e( 'Most recent first', 'globus-debug-control' ); ?></div>
				</div>
			</div>

			<div class="gdc-toolbar">
				<span class="gdc-fs-xs gdc-dim">
					<?php esc_html_e( 'Captured requests are stored in a transient and capped at 100 entries.', 'globus-debug-control' ); ?>
				</span>
				<div class="gdc-toolbar-group">
					<button type="button" id="gdc-http-toggle" class="gdc-btn <?php echo $capturing ? 'gdc-btn--surface' : 'gdc-btn--pri'; ?><?php echo $capturing ? ' gdc-http-capturing' : ''; ?>"
						data-capturing="<?php echo $capturing ? '1' : '0'; ?>">
						<?php echo GDC_UI::icon( $capturing ? 'stop' : 'play', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<?php echo $capturing ? esc_html__( 'Stop capturing', 'globus-debug-control' ) : esc_html__( 'Start capturing', 'globus-debug-control' ); ?>
					</button>
					<?php if ( ! empty( $log ) ) : ?>
						<button type="button" id="gdc-http-clear" class="gdc-btn gdc-btn--danger">
							<?php echo GDC_UI::icon( 'trash', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
							<?php esc_html_e( 'Clear log', 'globus-debug-control' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( empty( $log ) ) : ?>
				<div class="gdc-empty-state">
					<?php
					if ( $capturing ) {
						$gdc_empty = __( 'No requests captured yet. Browse other pages to record HTTP API calls.', 'globus-debug-control' );
					} else {
						$gdc_empty = __( 'Capturing is disabled. Click "Start capturing" to begin recording outgoing HTTP requests.', 'globus-debug-control' );
					}

					echo GDC_UI::empty_state( $gdc_empty, 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
					?>
				</div>
			<?php else : ?>
				<div class="gdc-card">
					<div class="gdc-table-wrap">
						<table class="gdc-table gdc-http-table">
							<thead>
								<tr>
									<th class="gdc-col-time"><?php esc_html_e( 'Time', 'globus-debug-control' ); ?></th>
									<th><?php esc_html_e( 'Request', 'globus-debug-control' ); ?></th>
									<th class="gdc-col-status"><?php esc_html_e( 'Status', 'globus-debug-control' ); ?></th>
									<th class="gdc-col-transport"><?php esc_html_e( 'Transport', 'globus-debug-control' ); ?></th>
									<th class="gdc-col-error"><?php esc_html_e( 'Error', 'globus-debug-control' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $log as $entry ) : ?>
									<?php
									$s   = (int) $entry['status'];
									$cls = 0 === $s ? 'gdc-http-status-err'
										: ( $s >= 500 ? 'gdc-http-status-5xx'
										: ( $s >= 400 ? 'gdc-http-status-4xx'
										: ( $s >= 300 ? 'gdc-http-status-3xx' : 'gdc-http-status-2xx' ) ) );
									?>
									<tr>
										<td class="gdc-mono gdc-fs-2xs gdc-nowrap"><?php echo esc_html( gmdate( 'H:i:s', (int) $entry['ts'] ) ); ?></td>
										<td class="gdc-http-url-cell">
											<div class="gdc-row gdc-g-1h gdc-row--top gdc-row--wrap">
												<span class="gdc-chip gdc-http-method gdc-http-method-<?php echo esc_attr( strtolower( (string) $entry['method'] ) ); ?>"><?php echo esc_html( (string) $entry['method'] ); ?></span>
												<code class="gdc-http-url" title="<?php echo esc_attr( (string) $entry['url'] ); ?>"><?php echo esc_html( (string) $entry['url'] ); ?></code>
											</div>
										</td>
										<td>
											<div class="gdc-row gdc-g-1h">
												<span class="gdc-chip gdc-http-status <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $s > 0 ? (string) $s : '—' ); ?></span>
												<span class="gdc-dim gdc-fs-2xs gdc-http-duration"><?php echo esc_html( number_format_i18n( (float) ( $entry['duration'] ?? 0.0 ), 3 ) . 's' ); ?></span>
											</div>
										</td>
										<td><code class="gdc-fs-2xs"><?php echo esc_html( isset( $entry['transport'] ) ? (string) $entry['transport'] : '-' ); ?></code></td>
										<td>
											<?php if ( ! empty( $entry['error'] ) ) : ?>
												<span class="gdc-chip gdc-chip--red gdc-http-error"><?php echo esc_html( (string) $entry['error'] ); ?></span>
											<?php else : ?>
												<span class="gdc-dim">&mdash;</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

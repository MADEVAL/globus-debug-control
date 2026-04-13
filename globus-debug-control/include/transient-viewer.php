<?php
/**
 * GLOBUS Debug Control - Transient Viewer
 *
 * Lists all WordPress transients with key, size, expiry, and per-row delete.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays and manages WordPress transients stored in wp_options.
 *
 * @package GlobusDebugControl
 */
final class GDC_Transient_Viewer {

	private const NONCE_DELETE = 'gdc_transient_delete';
	private const NONCE_PURGE  = 'gdc_transient_purge';

	/**
	 * Registers AJAX action hooks.
	 */
	public static function register_ajax_hooks(): void {
		add_action( 'wp_ajax_gdc_transient_delete', array( self::class, 'ajax_delete' ) );
		add_action( 'wp_ajax_gdc_transient_purge', array( self::class, 'ajax_purge_expired' ) );
	}

	/**
	 * Returns all transients from the options table with expiry and size info.
	 *
	 * Uses a LEFT JOIN to retrieve timeout in a single query.
	 *
	 * @return array<int, array{key: string, value_size: int, expires_in: int|null, is_expired: bool}>
	 */
	public static function get_transients(): array {
		global $wpdb;

		$like_key     = $wpdb->esc_like( '_transient_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin diagnostic query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.option_name,
				        LENGTH(t.option_value) AS value_size,
				        CAST(x.option_value AS UNSIGNED) AS timeout
				 FROM {$wpdb->options} t
				 LEFT JOIN {$wpdb->options} x
				        ON x.option_name = CONCAT('_transient_timeout_', SUBSTRING(t.option_name, 12))
				 WHERE t.option_name LIKE %s
				   AND t.option_name NOT LIKE %s
				 ORDER BY t.option_name",
				$like_key,
				$like_timeout
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$now    = time();
		$result = array();

		foreach ( $rows as $row ) {
			$key      = substr( (string) $row['option_name'], strlen( '_transient_' ) );
			$timeout  = (int) $row['timeout'];
			$result[] = array(
				'key'        => $key,
				'value_size' => (int) $row['value_size'],
				'expires_in' => $timeout > 0 ? $timeout - $now : null,
				'is_expired' => $timeout > 0 && $timeout < $now,
			);
		}

		return $result;
	}

	/**
	 * Returns aggregate summary stats for a list of transients.
	 *
	 * @param array $transients Output of get_transients().
	 * @return array{total: int, expired: int, total_size: int}
	 */
	public static function get_summary( array $transients ): array {
		$total      = count( $transients );
		$expired    = 0;
		$total_size = 0;
		foreach ( $transients as $t ) {
			$total_size += (int) $t['value_size'];
			if ( $t['is_expired'] ) {
				++$expired;
			}
		}
		return compact( 'total', 'expired', 'total_size' );
	}

	/**
	 * AJAX handler: deletes a single transient by key.
	 */
	public static function ajax_delete(): void {
		check_ajax_referer( self::NONCE_DELETE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( '' === $key ) {
			wp_send_json_error( 'Invalid key.' );
		}

		delete_transient( $key );
		wp_send_json_success( array( 'key' => $key ) );
	}

	/**
	 * AJAX handler: purges all expired transients via a single bulk DELETE JOIN.
	 */
	public static function ajax_purge_expired(): void {
		check_ajax_referer( self::NONCE_PURGE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bulk-delete expired transients; no safe prepared form for this JOIN DELETE.
		$wpdb->query(
			"DELETE a, b
			 FROM {$wpdb->options} a
			 INNER JOIN {$wpdb->options} b
			         ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
			 WHERE a.option_name LIKE '_transient_timeout_%'
			   AND CAST(a.option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		wp_send_json_success( array( 'purged' => true ) );
	}

	/**
	 * Renders the transient viewer UI.
	 */
	public static function render(): void {
		$transients = self::get_transients();
		$summary    = self::get_summary( $transients );
		?>
		<script>var gdcTransient=
		<?php
		echo wp_json_encode(
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonceDelete' => wp_create_nonce( self::NONCE_DELETE ),
				'noncePurge'  => wp_create_nonce( self::NONCE_PURGE ),
			)
		);
		?>
									;</script>

		<div class="gdc-tool-header">
			<div class="gdc-cron-summary">
				<div class="gdc-cron-summary-card">
					<span class="dashicons dashicons-database"></span>
					<div>
						<strong><?php echo esc_html( $summary['total'] ); ?></strong>
						<span><?php esc_html_e( 'Total', 'globus-debug-control' ); ?></span>
					</div>
				</div>
				<div class="gdc-cron-summary-card <?php echo $summary['expired'] > 0 ? 'gdc-card-warning' : 'gdc-card-ok'; ?>">
					<span class="dashicons dashicons-clock"></span>
					<div>
						<strong><?php echo esc_html( $summary['expired'] ); ?></strong>
						<span><?php esc_html_e( 'Expired', 'globus-debug-control' ); ?></span>
					</div>
				</div>
				<div class="gdc-cron-summary-card">
					<span class="dashicons dashicons-chart-area"></span>
					<div>
						<strong><?php echo esc_html( size_format( $summary['total_size'] ) ); ?></strong>
						<span><?php esc_html_e( 'Total size', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			</div>
			<?php if ( $summary['expired'] > 0 ) : ?>
				<button type="button" id="gdc-transient-purge" class="button button-secondary">
					<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>
					<?php esc_html_e( 'Purge expired', 'globus-debug-control' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( empty( $transients ) ) : ?>
			<div class="gdc-empty-state">
				<p><?php esc_html_e( 'No transients found.', 'globus-debug-control' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat striped gdc-transient-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key', 'globus-debug-control' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Size', 'globus-debug-control' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Expires', 'globus-debug-control' ); ?></th>
						<th style="width:80px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $transients as $t ) : ?>
						<tr class="gdc-transient-row<?php echo $t['is_expired'] ? ' gdc-transient-expired' : ''; ?>" data-key="<?php echo esc_attr( $t['key'] ); ?>">
							<td><code class="gdc-transient-key"><?php echo esc_html( $t['key'] ); ?></code></td>
							<td><?php echo esc_html( size_format( $t['value_size'] ) ); ?></td>
							<td>
								<?php
								if ( null === $t['expires_in'] ) {
									esc_html_e( 'No expiry', 'globus-debug-control' );
								} elseif ( $t['is_expired'] ) {
									echo '<span class="gdc-badge-expired">' . esc_html__( 'Expired', 'globus-debug-control' ) . '</span>';
								} else {
									echo esc_html(
										sprintf(
											/* translators: %s: human-readable time difference */
											__( 'in %s', 'globus-debug-control' ),
											human_time_diff( time(), time() + (int) $t['expires_in'] )
										)
									);
								}
								?>
							</td>
							<td>
								<button type="button" class="button button-small gdc-transient-delete">
									<?php esc_html_e( 'Delete', 'globus-debug-control' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}

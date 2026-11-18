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
				'site'       => false,
			);
		}

		// On multisite, network transients live in the sitemeta table.
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$result = array_merge( $result, self::get_site_transients() );
		}

		return $result;
	}

	/**
	 * Returns site transients (_site_transient_*) from the sitemeta table.
	 *
	 * Keys are returned with the full _site_transient_ prefix so the delete
	 * route can dispatch them to delete_site_transient().
	 *
	 * @return array<int, array{key: string, value_size: int, expires_in: int|null, is_expired: bool, site: bool}>
	 */
	private static function get_site_transients(): array {
		global $wpdb;

		if ( empty( $wpdb->sitemeta ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin diagnostic query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.meta_key AS option_name,
				        LENGTH(t.meta_value) AS value_size,
				        CAST(x.meta_value AS UNSIGNED) AS timeout
				 FROM {$wpdb->sitemeta} t
				 LEFT JOIN {$wpdb->sitemeta} x
				        ON x.meta_key = CONCAT('_site_transient_timeout_', SUBSTRING(t.meta_key, 17))
				 WHERE t.meta_key LIKE %s
				   AND t.meta_key NOT LIKE %s
				 ORDER BY t.meta_key",
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$now    = time();
		$result = array();

		foreach ( $rows as $row ) {
			$timeout  = (int) $row['timeout'];
			$result[] = array(
				'key'        => (string) $row['option_name'],
				'value_size' => (int) $row['value_size'],
				'expires_in' => $timeout > 0 ? $timeout - $now : null,
				'is_expired' => $timeout > 0 && $timeout < $now,
				'site'       => true,
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

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( '' === $key ) {
			wp_send_json_error( 'Invalid key.' );
		}

		if ( function_exists( 'delete_site_transient' ) && 0 === strpos( $key, '_site_transient_' ) ) {
			delete_site_transient( substr( $key, strlen( '_site_transient_' ) ) );
			wp_send_json_success( array( 'key' => $key ) );
		}

		delete_transient( $key );
		wp_send_json_success( array( 'key' => $key ) );
	}

	/**
	 * Deletes all expired transients from the options table.
	 *
	 * @return int Number of deleted rows, or -1 when the query failed.
	 */
	public static function purge_expired(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bulk-delete expired transients; no safe prepared form for this JOIN DELETE.
		$result = $wpdb->query(
			"DELETE a, b
			 FROM {$wpdb->options} a
			 INNER JOIN {$wpdb->options} b
			         ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
			 WHERE a.option_name LIKE '_transient_timeout_%'
			   AND CAST(a.option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		if ( false === $result ) {
			return -1;
		}

		$deleted = (int) $result;

		if ( function_exists( 'is_multisite' ) && is_multisite() && ! empty( $wpdb->sitemeta ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bulk-delete expired site transients; no safe prepared form for this JOIN DELETE.
			$site_result = $wpdb->query(
				"DELETE a, b
				 FROM {$wpdb->sitemeta} a
				 INNER JOIN {$wpdb->sitemeta} b
				         ON b.meta_key = REPLACE(a.meta_key, '_site_transient_timeout_', '_site_transient_')
				 WHERE a.meta_key LIKE '_site_transient_timeout_%'
				   AND CAST(a.meta_value AS UNSIGNED) < UNIX_TIMESTAMP()"
			);

			if ( false === $site_result ) {
				return -1;
			}

			$deleted += (int) $site_result;
		}

		return $deleted;
	}

	/**
	 * AJAX handler: purges all expired transients via a single bulk DELETE JOIN.
	 */
	public static function ajax_purge_expired(): void {
		check_ajax_referer( self::NONCE_PURGE );

		if ( ! current_user_can( \Globus_Debug_Control::manage_capability() ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$deleted = self::purge_expired();

		if ( $deleted < 0 ) {
			wp_send_json_error( 'The purge query failed.', 500 );
		}

		wp_send_json_success(
			array(
				'purged'       => true,
				'rows_deleted' => $deleted,
			)
		);
	}

	/**
	 * Returns storage diagnostics that explain an empty transient list.
	 *
	 * @return array{external_cache: bool, cache_class: string, db_error: string, transient_like_rows: int}
	 */
	public static function get_diagnostics(): array {
		global $wpdb;

		$external_cache = function_exists( 'wp_using_ext_object_cache' )
			? (bool) wp_using_ext_object_cache()
			: file_exists( WP_CONTENT_DIR . '/object-cache.php' );

		$cache_class = '';
		if ( isset( $GLOBALS['wp_object_cache'] ) && is_object( $GLOBALS['wp_object_cache'] ) ) {
			$cache_class = get_class( $GLOBALS['wp_object_cache'] );
		}

		$db_error = isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '';

		$transient_like_rows = 0;
		if ( ! empty( $wpdb->options ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin diagnostic count.
			$count               = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( '_transient' ) . '%'
				)
			);
			$transient_like_rows = null === $count ? 0 : (int) $count;
		}

		return compact( 'external_cache', 'cache_class', 'db_error', 'transient_like_rows' );
	}

	/**
	 * Renders the transient viewer UI.
	 */
	public static function render(): void {
		$transients  = self::get_transients();
		$summary     = self::get_summary( $transients );
		$diagnostics = self::get_diagnostics();
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

		<?php if ( $diagnostics['db_error'] ) : ?>
			<div class="notice notice-error inline">
				<p>
					<strong><?php esc_html_e( 'Transient query failed:', 'globus-debug-control' ); ?></strong>
					<code><?php echo esc_html( $diagnostics['db_error'] ); ?></code>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $diagnostics['external_cache'] ) : ?>
			<div class="notice notice-info inline gdc-transient-cache-notice">
				<p>
					<strong><?php esc_html_e( 'A persistent object cache is active.', 'globus-debug-control' ); ?></strong>
					<?php esc_html_e( 'Transients are stored in the object cache (for example Redis or Memcached), not in the wp_options table. This viewer lists database rows only, so an empty table is expected and is not an error.', 'globus-debug-control' ); ?>
					<?php if ( '' !== $diagnostics['cache_class'] ) : ?>
						<code><?php echo esc_html( $diagnostics['cache_class'] ); ?></code>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

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
				<div class="gdc-tool-actions">
					<button type="button" id="gdc-transient-purge" class="button button-secondary">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Purge expired', 'globus-debug-control' ); ?>
						<span class="gdc-badge gdc-badge-warning"><?php echo esc_html( (string) $summary['expired'] ); ?></span>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( empty( $transients ) ) : ?>
			<div class="gdc-empty-state">
				<span class="dashicons dashicons-database" aria-hidden="true"></span>
				<?php if ( $diagnostics['external_cache'] ) : ?>
					<p><?php esc_html_e( 'No database-stored transients. Transients are kept in the active object cache.', 'globus-debug-control' ); ?></p>
				<?php elseif ( $diagnostics['transient_like_rows'] > 0 ) : ?>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of database rows */
								_n( 'Found %d transient-like option but could not list it. Check the database error above.', 'Found %d transient-like options but could not list them. Check the database error above.', $diagnostics['transient_like_rows'], 'globus-debug-control' ),
								$diagnostics['transient_like_rows']
							)
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'No transients found in the database. Expired entries may already have been removed, or the site may not use database transients.', 'globus-debug-control' ); ?></p>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of database rows */
								_n( 'Database check: %d row with a transient prefix.', 'Database check: %d rows with a transient prefix.', $diagnostics['transient_like_rows'], 'globus-debug-control' ),
								$diagnostics['transient_like_rows']
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="gdc-table-scroll">
				<table class="wp-list-table widefat striped gdc-transient-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Key', 'globus-debug-control' ); ?></th>
							<th class="gdc-col-size"><?php esc_html_e( 'Size', 'globus-debug-control' ); ?></th>
							<th class="gdc-col-expires"><?php esc_html_e( 'Expires', 'globus-debug-control' ); ?></th>
							<th class="gdc-col-actions"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $transients as $t ) : ?>
							<tr class="gdc-transient-row<?php echo $t['is_expired'] ? ' gdc-transient-expired' : ''; ?>" data-key="<?php echo esc_attr( $t['key'] ); ?>">
								<td><code class="gdc-transient-key" title="<?php echo esc_attr( $t['key'] ); ?>"><?php echo esc_html( $t['key'] ); ?></code></td>
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
			</div>
		<?php endif; ?>
		<?php
	}
}

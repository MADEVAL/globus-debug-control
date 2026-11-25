<?php
/**
 * GLOBUS Debug Control - Autoloaded Options Viewer
 *
 * Lists wp_options rows where autoload is enabled, sorted by value size.
 * Helps identify options that inflate the autoloaded payload on every WP request.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only diagnostic view of autoloaded WordPress options sorted by size.
 *
 * @package GlobusDebugControl
 */
final class GDC_Options_Viewer {

	/** Maximum number of rows returned (top N by size). */
	private const LIMIT = 200;

	/** Warn when total autoloaded size exceeds this byte threshold (800 KB). */
	private const WARN_BYTES = 819200;

	/** Warn when more than this many rows are autoloaded. */
	private const WARN_COUNT = 100;

	/**
	 * Returns autoloaded options sorted by value size descending.
	 *
	 * @return array<int, array{name: string, value_size: int}>
	 */
	public static function get_autoloaded(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin diagnostic; intentionally bypasses object cache.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS value_size
				 FROM {$wpdb->options}
				 WHERE autoload IN ('yes', 'on', 'auto', 'auto-on')
				 ORDER BY value_size DESC
				 LIMIT %d",
				self::LIMIT
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			function ( $row ) {
				return array(
					'name'       => (string) $row['option_name'],
					'value_size' => (int) $row['value_size'],
				);
			},
			$rows
		);
	}

	/**
	 * Returns aggregate stats for a set of autoloaded options.
	 *
	 * @param array $options Output of get_autoloaded().
	 * @return array{count: int, total_size: int}
	 */
	public static function get_stats( array $options ): array {
		$count      = count( $options );
		$total_size = (int) array_sum( array_column( $options, 'value_size' ) );
		return compact( 'count', 'total_size' );
	}

	/**
	 * Renders the autoloaded options viewer UI.
	 */
	public static function render(): void {
		$options     = self::get_autoloaded();
		$stats       = self::get_stats( $options );
		$count_class = $stats['count'] > self::WARN_COUNT ? 'gdc-card-warning' : 'gdc-card-ok';
		$size_class  = $stats['total_size'] > self::WARN_BYTES ? 'gdc-card-warning' : 'gdc-card-ok';
		?>
		<div class="gdc-options-viewer">

			<div class="gdc-cron-summary gdc-grid gdc-grid--auto gdc-mb-3h">
				<div class="gdc-card gdc-kpi <?php echo esc_attr( $count_class ); ?>">
					<div class="gdc-row gdc-row--between">
						<span class="gdc-kpi-label"><?php esc_html_e( 'Autoloaded rows', 'globus-debug-control' ); ?></span>
						<span class="<?php echo $stats['count'] > self::WARN_COUNT ? 'gdc-tx-amber' : 'gdc-tx-green'; ?>"><?php echo GDC_UI::icon( 'list', 'gdc-ico--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?></span>
					</div>
					<div class="gdc-kpi-n"><span class="gdc-stat gdc-stat--sm"><?php echo esc_html( number_format_i18n( (int) $stats['count'] ) ); ?></span></div>
					<div class="gdc-kpi-sub"><?php esc_html_e( 'Rows loaded on every request', 'globus-debug-control' ); ?></div>
				</div>

				<div class="gdc-card gdc-kpi <?php echo esc_attr( $size_class ); ?>">
					<div class="gdc-row gdc-row--between">
						<span class="gdc-kpi-label"><?php esc_html_e( 'Total autoloaded', 'globus-debug-control' ); ?></span>
						<span class="<?php echo $stats['total_size'] > self::WARN_BYTES ? 'gdc-tx-amber' : 'gdc-tx-green'; ?>"><?php echo GDC_UI::icon( 'database', 'gdc-ico--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?></span>
					</div>
					<div class="gdc-kpi-n"><span class="gdc-stat gdc-stat--sm"><?php echo esc_html( size_format( (int) $stats['total_size'] ) ); ?></span></div>
					<div class="gdc-kpi-sub"><?php esc_html_e( 'Recommended under 800 KB', 'globus-debug-control' ); ?></div>
				</div>
			</div>

			<?php if ( $stats['count'] >= self::LIMIT ) : ?>
				<div class="gdc-alert gdc-alert--info gdc-mb-3h">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: row limit */
							__( 'Showing the top %d rows by size.', 'globus-debug-control' ),
							self::LIMIT
						)
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $options ) ) : ?>
				<div class="gdc-empty-state">
					<?php echo GDC_UI::empty_state( __( 'No autoloaded options found.', 'globus-debug-control' ), 'database' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
				</div>
			<?php else : ?>
				<div class="gdc-card">
					<div class="gdc-table-wrap">
						<table class="gdc-table gdc-options-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Option name', 'globus-debug-control' ); ?></th>
									<th class="gdc-col-size"><?php esc_html_e( 'Size', 'globus-debug-control' ); ?></th>
									<th class="gdc-col-bar"><?php esc_html_e( 'Relative size', 'globus-debug-control' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$max = ! empty( $options ) ? max( 1, $options[0]['value_size'] ) : 1;
								foreach ( $options as $opt ) :
									$pct       = (int) round( ( $opt['value_size'] / $max ) * 100 );
									$bar_class = $opt['value_size'] > 100000 ? 'gdc-bar-danger'
										: ( $opt['value_size'] > 10000 ? 'gdc-bar-warning' : 'gdc-bar-ok' );
									?>
									<tr>
										<td><code title="<?php echo esc_attr( $opt['name'] ); ?>"><?php echo esc_html( $opt['name'] ); ?></code></td>
										<td class="gdc-col-num"><?php echo esc_html( size_format( $opt['value_size'] ) ); ?></td>
										<td>
											<div class="gdc-size-bar-wrap" title="<?php echo esc_attr( $pct . '%' ); ?>">
												<div class="gdc-size-bar <?php echo esc_attr( $bar_class ); ?>" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div>
											</div>
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

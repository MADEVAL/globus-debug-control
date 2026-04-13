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
		<div class="gdc-tool-header">
			<div class="gdc-cron-summary">
				<div class="gdc-cron-summary-card <?php echo esc_attr( $count_class ); ?>">
					<span class="dashicons dashicons-list-view"></span>
					<div>
						<strong><?php echo esc_html( $stats['count'] ); ?></strong>
						<span><?php esc_html_e( 'Autoloaded rows', 'globus-debug-control' ); ?></span>
					</div>
				</div>
				<div class="gdc-cron-summary-card <?php echo esc_attr( $size_class ); ?>">
					<span class="dashicons dashicons-chart-area"></span>
					<div>
						<strong><?php echo esc_html( size_format( $stats['total_size'] ) ); ?></strong>
						<span><?php esc_html_e( 'Total autoloaded', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			</div>
			<?php if ( $stats['count'] >= self::LIMIT ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: row limit */
							__( 'Showing top %d rows by size.', 'globus-debug-control' ),
							self::LIMIT
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( empty( $options ) ) : ?>
			<div class="gdc-empty-state">
				<p><?php esc_html_e( 'No autoloaded options found.', 'globus-debug-control' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat striped gdc-options-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Option name', 'globus-debug-control' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Size', 'globus-debug-control' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Relative size', 'globus-debug-control' ); ?></th>
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
							<td><code><?php echo esc_html( $opt['name'] ); ?></code></td>
							<td><?php echo esc_html( size_format( $opt['value_size'] ) ); ?></td>
							<td>
								<div class="gdc-size-bar-wrap">
									<div class="gdc-size-bar <?php echo esc_attr( $bar_class ); ?>" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}

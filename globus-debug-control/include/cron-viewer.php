<?php
/**
 * GLOBUS Debug Control - Cron Viewer
 *
 * Renders a table of all scheduled WP-Cron events.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders WordPress cron job schedule information.
 *
 * @package GlobusDebugControl
 */
final class GDC_Cron_Viewer {

	/**
	 * Outputs the complete cron-viewer HTML table.
	 */
	public static function render(): void {
		$crons = self::get_cron_events();
		?>
		<div class="gdc-cron-viewer">
			<?php if ( empty( $crons ) ) : ?>
				<div class="gdc-empty-state">
					<p><?php esc_html_e( 'No scheduled cron events found.', 'globus-debug-control' ); ?></p>
				</div>
			<?php else : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of events */
							_n( '%d scheduled event', '%d scheduled events', count( $crons ), 'globus-debug-control' ),
							count( $crons )
						)
					);
					?>
				</p>
				<table class="wp-list-table widefat fixed striped gdc-cron-table">
					<thead>
						<tr>
							<th scope="col" style="width: 160px;"><?php esc_html_e( 'Next Run', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width: 120px;"><?php esc_html_e( 'In', 'globus-debug-control' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Hook', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width: 140px;"><?php esc_html_e( 'Schedule', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width: 80px;"><?php esc_html_e( 'Args', 'globus-debug-control' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $crons as $event ) : ?>
						<tr class="<?php echo $event['overdue'] ? 'gdc-cron-overdue' : ''; ?>">
							<td>
								<?php echo esc_html( gmdate( 'Y-m-d H:i:s', $event['timestamp'] ) ); ?>
								<?php if ( $event['overdue'] ) : ?>
									<span class="gdc-badge gdc-badge-warning"><?php esc_html_e( 'overdue', 'globus-debug-control' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="<?php echo $event['overdue'] ? 'gdc-text-warning' : 'gdc-text-muted'; ?>">
								<?php echo esc_html( self::human_time_diff( $event['timestamp'] ) ); ?>
							</td>
							<td><code><?php echo esc_html( $event['hook'] ); ?></code></td>
							<td>
								<?php if ( ! empty( $event['schedule'] ) ) : ?>
									<span class="gdc-badge gdc-badge-info"><?php echo esc_html( $event['schedule'] ); ?></span>
									<?php if ( ! empty( $event['interval'] ) ) : ?>
										<small class="gdc-text-muted"><?php echo esc_html( self::interval_label( $event['interval'] ) ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span class="gdc-text-muted"><?php esc_html_e( 'one-time', 'globus-debug-control' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $event['args'] ) ) : ?>
									<code title="<?php echo esc_attr( wp_json_encode( $event['args'] ) ); ?>"><?php esc_html_e( 'yes', 'globus-debug-control' ); ?></code>
								<?php else : ?>
									<span class="gdc-text-muted">-</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Returns a sorted, normalised list of all scheduled cron events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cron_events(): array {
		$crons = _get_cron_array();

		if ( empty( $crons ) || ! is_array( $crons ) ) {
			return array();
		}

		$events = array();
		$now    = time();

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $event ) {
					$events[] = array(
						'timestamp' => (int) $timestamp,
						'hook'      => (string) $hook,
						'schedule'  => isset( $event['schedule'] ) ? (string) $event['schedule'] : '',
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						'args'      => isset( $event['args'] ) && is_array( $event['args'] ) ? array_filter( $event['args'] ) : array(),
						'overdue'   => (int) $timestamp < $now - 60,
					);
				}
			}
		}

		usort(
			$events,
			static function ( array $a, array $b ): int {
				return $a['timestamp'] - $b['timestamp'];
			}
		);

		return $events;
	}

	/**
	 * Returns a human-readable relative time string for a cron timestamp.
	 *
	 * @param int $timestamp Unix timestamp of the scheduled event.
	 * @return string E.g. "in 5 minutes" or "3 hours ago".
	 */
	public static function human_time_diff( int $timestamp ): string {
		$now  = time();
		$diff = $timestamp - $now;

		if ( $diff < 0 ) {
			return human_time_diff( $timestamp, $now ) . ' ' . __( 'ago', 'globus-debug-control' );
		}

		return __( 'in', 'globus-debug-control' ) . ' ' . human_time_diff( $now, $timestamp );
	}

	/**
	 * Returns a compact human-readable label for a cron interval in seconds.
	 *
	 * @param int $seconds Interval in seconds.
	 * @return string E.g. "15m", "1h", "1d".
	 */
	public static function interval_label( int $seconds ): string {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		if ( $seconds < 3600 ) {
			return round( $seconds / 60 ) . 'm';
		}

		if ( $seconds < 86400 ) {
			return round( $seconds / 3600 ) . 'h';
		}

		return round( $seconds / 86400 ) . 'd';
	}

	/**
	 * Returns an empty string (styles are enqueued via the global admin stylesheet).
	 *
	 * @return string
	 */
	public static function get_inline_styles(): string {
		return '';
	}
}

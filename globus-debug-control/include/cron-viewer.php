<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDC_Cron_Viewer {

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
									<span class="gdc-text-muted">—</span>
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

	public static function get_cron_events(): array {
		$crons = _get_cron_array();

		if ( empty( $crons ) || ! is_array( $crons ) ) {
			return [];
		}

		$events = [];
		$now    = time();

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $event ) {
					$events[] = [
						'timestamp' => (int) $timestamp,
						'hook'      => (string) $hook,
						'schedule'  => isset( $event['schedule'] ) ? (string) $event['schedule'] : '',
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						'args'      => isset( $event['args'] ) && is_array( $event['args'] ) ? array_filter( $event['args'] ) : [],
						'overdue'   => (int) $timestamp < $now - 60,
					];
				}
			}
		}

		usort( $events, static function ( array $a, array $b ): int {
			return $a['timestamp'] - $b['timestamp'];
		} );

		return $events;
	}

	public static function human_time_diff( int $timestamp ): string {
		$now  = time();
		$diff = $timestamp - $now;

		if ( $diff < 0 ) {
			return human_time_diff( $timestamp, $now ) . ' ' . __( 'ago', 'globus-debug-control' );
		}

		return __( 'in', 'globus-debug-control' ) . ' ' . human_time_diff( $now, $timestamp );
	}

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

	public static function get_inline_styles(): string {
		return '
			.gdc-cron-viewer { margin-top: 16px; }
			.gdc-cron-table code { font-size: 12px; }
			.gdc-cron-overdue td { background-color: #fcf0f1 !important; }
			.gdc-badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; line-height: 1.4; }
			.gdc-badge-warning { background: #fcf0f1; color: #8a2424; border: 1px solid #f8c4c4; }
			.gdc-badge-info { background: #f0f6fc; color: #135e96; border: 1px solid #b8d3ee; }
			.gdc-text-muted { color: #646970; }
			.gdc-text-warning { color: #d63638; font-weight: 600; }
			.gdc-empty-state { padding: 24px; text-align: center; background: #fff; border: 1px solid #dcdcde; color: #646970; }
		';
	}
}

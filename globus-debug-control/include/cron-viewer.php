<?php
/**
 * GLOBUS Debug Control - Cron Viewer
 *
 * Renders a table of all scheduled WP-Cron events with summary, notices,
 * callback info, and per-task run/delete actions.
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

	private const NONCE_SOURCES = 'gdc_cron_sources';
	private const NONCE_RUN     = 'gdc_cron_run';
	private const NONCE_DELETE  = 'gdc_cron_delete';

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Outputs the complete cron-viewer HTML: summary, notices, and events table.
	 */
	public static function render(): void {
		$crons   = self::get_cron_events();
		$summary = self::get_cron_summary();
		$notices = self::get_cron_notices( $crons, $summary );
		?>
		<div class="gdc-cron-viewer">

			<?php self::render_summary( $summary, $crons ); ?>

			<?php if ( ! empty( $notices ) ) : ?>
				<div class="gdc-cron-notices">
					<?php foreach ( $notices as $notice ) : ?>
						<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> notice-alt gdc-cron-notice inline">
							<p>
								<span class="dashicons dashicons-<?php echo esc_attr( $notice['icon'] ); ?>" style="vertical-align:middle;margin-right:4px;"></span>
								<?php echo esc_html( $notice['message'] ); ?>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $crons ) ) : ?>
				<div class="gdc-empty-state">
					<p><?php esc_html_e( 'No scheduled cron events found.', 'globus-debug-control' ); ?></p>
				</div>
			<?php else : ?>
				<script>var gdcCron=
				<?php
				echo wp_json_encode(
					array(
						'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
						'nonce'       => wp_create_nonce( self::NONCE_SOURCES ),
						'nonceRun'    => wp_create_nonce( self::NONCE_RUN ),
						'nonceDelete' => wp_create_nonce( self::NONCE_DELETE ),
						'i18n'        => array(
							'running'       => __( 'Running…', 'globus-debug-control' ),
							'deleting'      => __( 'Deleting…', 'globus-debug-control' ),
							'deleted'       => __( 'Deleted', 'globus-debug-control' ),
							'runOk'         => __( 'Triggered', 'globus-debug-control' ),
							'error'         => __( 'Error', 'globus-debug-control' ),
							'confirmDelete' => __( 'Delete this scheduled event?', 'globus-debug-control' ),
						),
					)
				);
				?>
				;</script>

				<table class="wp-list-table widefat fixed striped gdc-cron-table">
					<thead>
						<tr>
							<th scope="col" style="width:155px;"><?php esc_html_e( 'Next Run', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width:100px;"><?php esc_html_e( 'In', 'globus-debug-control' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Hook / Callback', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width:150px;"><?php esc_html_e( 'Source', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width:130px;"><?php esc_html_e( 'Schedule', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width:80px;"><?php esc_html_e( 'Args', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width:90px;"><?php esc_html_e( 'Actions', 'globus-debug-control' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $crons as $event ) : ?>
							<?php
							$overdue_class   = $event['overdue'] ? 'gdc-cron-overdue' : '';
							$orphan_class    = $event['orphan'] ? 'gdc-cron-orphan' : '';
							$row_class       = trim( $overdue_class . ' ' . $orphan_class );
							$overdue_urgency = self::overdue_urgency( $event['timestamp'] );
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>"
								data-hook="<?php echo esc_attr( $event['hook'] ); ?>"
								data-ts="<?php echo esc_attr( (string) $event['timestamp'] ); ?>">
								<td>
									<?php echo esc_html( gmdate( 'Y-m-d H:i:s', $event['timestamp'] ) ); ?>
									<?php if ( $event['overdue'] ) : ?>
										<span class="gdc-badge gdc-badge-<?php echo esc_attr( $overdue_urgency ); ?>">
											<?php esc_html_e( 'overdue', 'globus-debug-control' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="<?php echo $event['overdue'] ? 'gdc-text-warning' : 'gdc-text-muted'; ?>">
									<?php echo esc_html( self::human_time_diff( $event['timestamp'] ) ); ?>
								</td>
								<td>
									<code class="gdc-cron-hook-name"><?php echo esc_html( $event['hook'] ); ?></code>
									<?php if ( $event['orphan'] ) : ?>
										<span class="gdc-badge gdc-badge-orphan" title="<?php esc_attr_e( 'No callback registered and hook not found in plugin files', 'globus-debug-control' ); ?>">
											<?php esc_html_e( 'orphan', 'globus-debug-control' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( ! empty( $event['callbacks'] ) ) : ?>
										<button type="button" class="button-link gdc-cron-cb-toggle" aria-expanded="false">
											<span class="dashicons dashicons-arrow-down-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span>
										</button>
										<ul class="gdc-cron-callbacks" hidden>
											<?php foreach ( $event['callbacks'] as $cb_label ) : ?>
												<li><code class="gdc-cron-cb"><?php echo esc_html( $cb_label ); ?></code></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
									<?php if ( ! empty( $event['instances'] ) && $event['instances'] > 1 ) : ?>
										<span class="gdc-badge gdc-badge-info" title="<?php esc_attr_e( 'Multiple scheduled instances of this hook', 'globus-debug-control' ); ?>">
											&times;<?php echo esc_html( (string) $event['instances'] ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="gdc-cron-source" data-hook="<?php echo esc_attr( $event['hook'] ); ?>">
									<span class="spinner is-active" style="float:none;margin:0;"></span>
								</td>
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
								<td class="gdc-cron-actions">
									<button type="button"
										class="button button-small gdc-cron-run"
										title="<?php esc_attr_e( 'Trigger now', 'globus-debug-control' ); ?>"
										data-hook="<?php echo esc_attr( $event['hook'] ); ?>"
										data-ts="<?php echo esc_attr( (string) $event['timestamp'] ); ?>">
										<span class="dashicons dashicons-controls-play" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
									</button>
									<button type="button"
										class="button button-small gdc-cron-delete"
										title="<?php esc_attr_e( 'Delete event', 'globus-debug-control' ); ?>"
										data-hook="<?php echo esc_attr( $event['hook'] ); ?>"
										data-ts="<?php echo esc_attr( (string) $event['timestamp'] ); ?>"
										data-args="<?php echo esc_attr( wp_json_encode( $event['args'] ) ); ?>">
										<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;color:#d63638;"></span>
									</button>
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
	 * Renders the summary cards strip above the table.
	 *
	 * @param array<string, mixed>             $summary Output of get_cron_summary().
	 * @param array<int, array<string, mixed>> $crons   Cron events list.
	 */
	private static function render_summary( array $summary, array $crons ): void {
		?>
		<div class="gdc-cron-summary">

			<div class="gdc-cron-summary-card <?php echo $summary['cron_disabled'] ? 'gdc-card-warning' : 'gdc-card-ok'; ?>">
				<span class="dashicons dashicons-<?php echo $summary['cron_disabled'] ? 'dismiss' : 'yes-alt'; ?>"></span>
				<div>
					<strong>WP-Cron</strong>
					<span>
						<?php if ( $summary['cron_disabled'] ) : ?>
							<?php esc_html_e( 'Disabled', 'globus-debug-control' ); ?>
						<?php elseif ( $summary['alternate_cron'] ) : ?>
							<?php esc_html_e( 'Alternate', 'globus-debug-control' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Enabled', 'globus-debug-control' ); ?>
						<?php endif; ?>
					</span>
				</div>
			</div>

			<div class="gdc-cron-summary-card">
				<span class="dashicons dashicons-calendar-alt"></span>
				<div>
					<strong><?php echo esc_html( (string) count( $crons ) ); ?></strong>
					<span><?php esc_html_e( 'Events', 'globus-debug-control' ); ?></span>
				</div>
			</div>

			<div class="gdc-cron-summary-card">
				<span class="dashicons dashicons-admin-links"></span>
				<div>
					<strong><?php echo esc_html( (string) $summary['unique_hooks'] ); ?></strong>
					<span><?php esc_html_e( 'Unique hooks', 'globus-debug-control' ); ?></span>
				</div>
			</div>

			<?php if ( $summary['overdue'] > 0 ) : ?>
				<div class="gdc-cron-summary-card gdc-card-warning">
					<span class="dashicons dashicons-warning"></span>
					<div>
						<strong><?php echo esc_html( (string) $summary['overdue'] ); ?></strong>
						<span><?php esc_html_e( 'Overdue', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $summary['orphans'] > 0 ) : ?>
				<div class="gdc-cron-summary-card gdc-card-error">
					<span class="dashicons dashicons-dismiss"></span>
					<div>
						<strong><?php echo esc_html( (string) $summary['orphans'] ); ?></strong>
						<span><?php esc_html_e( 'Orphaned', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $summary['stuck_cron'] ) : ?>
				<div class="gdc-cron-summary-card gdc-card-error">
					<span class="dashicons dashicons-lock"></span>
					<div>
						<strong><?php esc_html_e( 'Stuck', 'globus-debug-control' ); ?></strong>
						<span><?php esc_html_e( 'Cron lock active', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( null !== $summary['next_event'] ) : ?>
				<div class="gdc-cron-summary-card">
					<span class="dashicons dashicons-clock"></span>
					<div>
						<strong><?php echo esc_html( self::human_time_diff( $summary['next_event'] ) ); ?></strong>
						<span><?php esc_html_e( 'Next run', 'globus-debug-control' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Data methods
	// -------------------------------------------------------------------------

	/**
	 * Returns a sorted, normalised list of all scheduled cron events.
	 *
	 * Each event array includes:
	 *   - timestamp, hook, schedule, interval, args (standard)
	 *   - overdue    bool  - event is more than 60 s past its scheduled time
	 *   - orphan     bool  - no registered callback AND hook not found in files
	 *   - callbacks  string[] - human-readable callable labels from $wp_filter
	 *   - instances  int   - number of scheduled instances of this hook (deduped key = hook+args)
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cron_events(): array {
		global $wp_filter;

		$crons = _get_cron_array();

		if ( empty( $crons ) || ! is_array( $crons ) ) {
			return array();
		}

		// Count instances per hook so the table can show ×N badges.
		$hook_counts = array();
		foreach ( $crons as $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				$hook_counts[ $hook ] = ( $hook_counts[ $hook ] ?? 0 ) + count( $hook_events );
			}
		}

		$events = array();
		$now    = time();

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $event ) {
					$callbacks = self::collect_callbacks( $hook );
					$has_cb    = ! empty( $callbacks ) || ! empty( $wp_filter[ $hook ] );

					$events[] = array(
						'timestamp' => (int) $timestamp,
						'hook'      => (string) $hook,
						'schedule'  => isset( $event['schedule'] ) ? (string) $event['schedule'] : '',
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						'args'      => isset( $event['args'] ) && is_array( $event['args'] ) ? array_filter( $event['args'] ) : array(),
						'overdue'   => (int) $timestamp < $now - 60,
						'orphan'    => ! $has_cb,
						'callbacks' => $callbacks,
						'instances' => $hook_counts[ $hook ] ?? 1,
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
	 * Returns an aggregated summary of the WP-Cron system state.
	 *
	 * @return array{
	 *   cron_disabled: bool,
	 *   alternate_cron: bool,
	 *   unique_hooks: int,
	 *   overdue: int,
	 *   orphans: int,
	 *   duplicates: int,
	 *   stuck_cron: bool,
	 *   next_event: int|null
	 * }
	 */
	public static function get_cron_summary(): array {
		$crons = _get_cron_array();

		$unique_hooks = array();
		$overdue      = 0;
		$orphans      = 0;
		$duplicates   = 0;
		$hook_counts  = array();
		$now          = time();
		$next_ts      = null;

		if ( is_array( $crons ) ) {
			foreach ( $crons as $timestamp => $hooks ) {
				if ( null === $next_ts || (int) $timestamp < $next_ts ) {
					$next_ts = (int) $timestamp;
				}
				foreach ( $hooks as $hook => $hook_events ) {
					$unique_hooks[ $hook ] = true;
					$cnt                   = count( $hook_events );
					$hook_counts[ $hook ]  = ( $hook_counts[ $hook ] ?? 0 ) + $cnt;
					if ( (int) $timestamp < $now - 60 ) {
						$overdue += $cnt;
					}
				}
			}

			// Orphan + duplicate pass (needs full list).
			global $wp_filter;
			foreach ( array_keys( $unique_hooks ) as $hook ) {
				if ( empty( $wp_filter[ $hook ] ) ) {
					++$orphans;
				}
				if ( ( $hook_counts[ $hook ] ?? 1 ) > 1 ) {
					++$duplicates;
				}
			}
		}

		// Detect stuck cron lock: transient older than 10 minutes.
		$doing_cron = get_transient( 'doing_cron' );
		$stuck      = false !== $doing_cron && ( $now - (float) $doing_cron ) > 600;

		return array(
			'cron_disabled'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'alternate_cron' => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'unique_hooks'   => count( $unique_hooks ),
			'overdue'        => $overdue,
			'orphans'        => $orphans,
			'duplicates'     => $duplicates,
			'stuck_cron'     => $stuck,
			'next_event'     => $next_ts,
		);
	}

	/**
	 * Returns an array of actionable notice descriptors based on cron state.
	 *
	 * @param array<int, array<string, mixed>> $events  Output of get_cron_events().
	 * @param array<string, mixed>             $summary Output of get_cron_summary().
	 * @return array<int, array{type: string, icon: string, message: string}>
	 */
	public static function get_cron_notices( array $events, array $summary ): array {
		$notices = array();

		if ( $summary['cron_disabled'] ) {
			$notices[] = array(
				'type'    => 'warning',
				'icon'    => 'warning',
				'message' => __( 'DISABLE_WP_CRON is true - WP-Cron will not fire automatically on page load. Make sure a real system cron job is configured.', 'globus-debug-control' ),
			);
		}

		if ( $summary['alternate_cron'] ) {
			$notices[] = array(
				'type'    => 'info',
				'icon'    => 'info',
				'message' => __( 'ALTERNATE_WP_CRON is true - cron runs via a redirect instead of a non-blocking HTTP call.', 'globus-debug-control' ),
			);
		}

		if ( $summary['stuck_cron'] ) {
			$notices[] = array(
				'type'    => 'error',
				'icon'    => 'lock',
				'message' => __( 'The doing_cron transient is older than 10 minutes - WP-Cron appears to be stuck. Consider clearing it.', 'globus-debug-control' ),
			);
		}

		if ( $summary['orphans'] > 0 ) {
			$notices[] = array(
				'type'    => 'warning',
				'icon'    => 'dismiss',
				'message' => sprintf(
					/* translators: %d: number of orphaned hooks */
					_n(
						'%d orphaned cron hook found - its plugin may have been deactivated without cleaning up.',
						'%d orphaned cron hooks found - their plugins may have been deactivated without cleaning up.',
						$summary['orphans'],
						'globus-debug-control'
					),
					$summary['orphans']
				),
			);
		}

		if ( $summary['duplicates'] > 0 ) {
			$notices[] = array(
				'type'    => 'warning',
				'icon'    => 'warning',
				'message' => sprintf(
					/* translators: %d: number of duplicated hooks */
					_n(
						'%d hook has multiple scheduled instances.',
						'%d hooks have multiple scheduled instances.',
						$summary['duplicates'],
						'globus-debug-control'
					),
					$summary['duplicates']
				),
			);
		}

		// Detect hooks that run more often than every minute.
		foreach ( $events as $event ) {
			if ( ! empty( $event['interval'] ) && $event['interval'] < 60 ) {
				$notices[] = array(
					'type'    => 'info',
					'icon'    => 'performance',
					'message' => sprintf(
						/* translators: %s: hook name */
						__( '"%s" is scheduled with an interval shorter than 1 minute.', 'globus-debug-control' ),
						$event['hook']
					),
				);
				break; // one notice is enough - let the table show the rest.
			}
		}

		return $notices;
	}

	/**
	 * Returns human-readable callback labels for a given hook from $wp_filter.
	 *
	 * @param string $hook Hook name.
	 * @return string[]
	 */
	public static function collect_callbacks( string $hook ): array {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return array();
		}

		$filter_entry  = $wp_filter[ $hook ];
		$callbacks_map = is_object( $filter_entry ) && property_exists( $filter_entry, 'callbacks' )
			? $filter_entry->callbacks
			: (array) $filter_entry;

		$labels = array();
		foreach ( $callbacks_map as $priority_callbacks ) {
			if ( ! is_array( $priority_callbacks ) ) {
				continue;
			}
			foreach ( $priority_callbacks as $cb ) {
				if ( ! isset( $cb['function'] ) ) {
					continue;
				}
				$label = self::callable_label( $cb['function'] );
				if ( null !== $label && ! in_array( $label, $labels, true ) ) {
					$labels[] = $label;
				}
			}
		}

		return $labels;
	}

	/**
	 * Returns a human-readable string representation of a callable.
	 *
	 * @param mixed $cb Any callable.
	 * @return string|null
	 */
	public static function callable_label( $cb ): ?string {
		if ( is_string( $cb ) ) {
			return $cb . '()';
		}
		if ( $cb instanceof \Closure ) {
			return '{closure}';
		}
		if ( is_array( $cb ) && 2 === count( $cb ) ) {
			$class  = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
			$method = (string) $cb[1];
			return $class . '::' . $method . '()';
		}
		return null;
	}

	/**
	 * Returns an overdue urgency slug for a cron timestamp.
	 *
	 * - 'warning'  : overdue less than 1 hour
	 * - 'error'    : overdue 1 h – 24 h
	 * - 'critical' : overdue more than 1 day
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function overdue_urgency( int $timestamp ): string {
		$diff = time() - $timestamp;
		if ( $diff < 3600 ) {
			return 'warning';
		}
		if ( $diff < 86400 ) {
			return 'error';
		}
		return 'critical';
	}

	// -------------------------------------------------------------------------
	// AJAX hooks registration
	// -------------------------------------------------------------------------

	/**
	 * Registers AJAX action hooks for the cron viewer.
	 */
	public static function register_ajax_hooks(): void {
		add_action( 'wp_ajax_gdc_cron_sources', array( self::class, 'ajax_cron_sources' ) );
		add_action( 'wp_ajax_gdc_cron_sources_stream', array( self::class, 'ajax_cron_sources_stream' ) );
		add_action( 'wp_ajax_gdc_cron_run', array( self::class, 'ajax_cron_run' ) );
		add_action( 'wp_ajax_gdc_cron_delete', array( self::class, 'ajax_cron_delete' ) );
	}

	// -------------------------------------------------------------------------
	// AJAX: run / delete
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: triggers a scheduled cron hook immediately via do_action.
	 *
	 * Required POST params: hook (string), _ajax_nonce.
	 */
	public static function ajax_cron_run(): void {
		check_ajax_referer( self::NONCE_RUN );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$hook = isset( $_POST['hook'] )
			? preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', substr( (string) wp_unslash( $_POST['hook'] ), 0, 200 ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		if ( '' === $hook ) {
			wp_send_json_error( 'Invalid hook', 400 );
		}

		do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		wp_send_json_success( array( 'hook' => $hook ) );
	}

	/**
	 * AJAX handler: deletes a single scheduled cron event.
	 *
	 * Required POST params: hook (string), timestamp (int), args (JSON string), _ajax_nonce.
	 */
	public static function ajax_cron_delete(): void {
		check_ajax_referer( self::NONCE_DELETE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$hook = isset( $_POST['hook'] )
			? preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', substr( (string) wp_unslash( $_POST['hook'] ), 0, 200 ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		if ( '' === $hook ) {
			wp_send_json_error( 'Invalid hook', 400 );
		}

		$raw_args = isset( $_POST['args'] )
			? json_decode( wp_unslash( (string) $_POST['args'] ), true ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$args = is_array( $raw_args ) ? $raw_args : array();

		$result = wp_unschedule_event(
			isset( $_POST['timestamp'] ) ? (int) $_POST['timestamp'] : 0,
			$hook,
			$args
		);

		if ( false === $result ) {
			wp_send_json_error( 'Could not delete event', 500 );
		}

		wp_send_json_success( array( 'hook' => $hook ) );
	}

	// -------------------------------------------------------------------------
	// AJAX: sources (batch + stream)
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: resolves source info for a list of cron hook names.
	 *
	 * Accepts POST param `hooks[]` - an array of hook name strings.
	 * Returns a JSON object keyed by hook name, value is the source descriptor.
	 */
	public static function ajax_cron_sources(): void {
		check_ajax_referer( self::NONCE_SOURCES );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$raw = isset( $_POST['hooks'] ) && is_array( $_POST['hooks'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? wp_unslash( $_POST['hooks'] )
			: array();

		$result = array();
		foreach ( $raw as $hook ) {
			$hook = preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', substr( (string) $hook, 0, 200 ) );
			if ( '' !== $hook ) {
				$result[ $hook ] = self::resolve_hook_source( $hook );
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: streams source info for cron hook names as NDJSON.
	 *
	 * Each resolved hook is written to output immediately so the browser can
	 * update individual cells as results arrive, without waiting for the full batch.
	 */
	public static function ajax_cron_sources_stream(): void {
		check_ajax_referer( self::NONCE_SOURCES );

		if ( ! current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			wp_die( 'Forbidden' );
		}

		$raw = isset( $_POST['hooks'] ) && is_array( $_POST['hooks'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? wp_unslash( $_POST['hooks'] )
			: array();

		// End any output buffering so each line reaches the client immediately.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/x-ndjson; charset=utf-8' );
		header( 'X-Accel-Buffering: no' ); // Prevent nginx from buffering.
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );

		self::output_cron_sources_stream( $raw );

		wp_die();
	}

	/**
	 * Outputs resolved hook sources as NDJSON lines, flushing after each.
	 *
	 * Extracted as a public method so the streaming logic can be tested
	 * independently of the HTTP-header and output-buffer handling.
	 *
	 * @param array<mixed> $raw_hooks Unsanitised hook name values from the request.
	 */
	public static function output_cron_sources_stream( array $raw_hooks ): void {
		foreach ( $raw_hooks as $hook ) {
			$hook = preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', substr( (string) $hook, 0, 200 ) );
			if ( '' === $hook ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wp_json_encode(
				array(
					'hook'   => $hook,
					'source' => self::resolve_hook_source( $hook ),
				)
			) . "\n";
			flush();
		}
	}

	/**
	 * Resolves the origin (WordPress core, a plugin, or a theme) of a cron hook.
	 *
	 * Inspects registered callbacks via $wp_filter to determine the source file,
	 * then maps it to the appropriate source category.
	 *
	 * @param string $hook Hook name to inspect.
	 * @return array{type: string, name: string, slug: string}
	 */
	public static function resolve_hook_source( string $hook ): array {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return self::find_hook_in_files( $hook );
		}

		$filter_entry  = $wp_filter[ $hook ];
		$callbacks_map = is_object( $filter_entry ) && property_exists( $filter_entry, 'callbacks' )
			? $filter_entry->callbacks
			: (array) $filter_entry;

		$sources = array();

		foreach ( $callbacks_map as $priority_callbacks ) {
			if ( ! is_array( $priority_callbacks ) ) {
				continue;
			}
			foreach ( $priority_callbacks as $cb ) {
				if ( ! isset( $cb['function'] ) ) {
					continue;
				}
				$file = self::get_callback_file( $cb['function'] );
				if ( null === $file ) {
					continue;
				}
				$source = self::classify_file_source( $file );
				$key    = $source['type'] . ':' . $source['slug'];
				if ( ! isset( $sources[ $key ] ) ) {
					$sources[ $key ] = $source;
				}
			}
		}

		if ( empty( $sources ) ) {
			return self::find_hook_in_files( $hook );
		}

		uasort(
			$sources,
			static function ( array $a, array $b ): int {
				$order = array(
					'plugin'  => 0,
					'theme'   => 1,
					'core'    => 2,
					'unknown' => 3,
				);
				return ( $order[ $a['type'] ] ?? 3 ) - ( $order[ $b['type'] ] ?? 3 );
			}
		);

		return reset( $sources );
	}

	/**
	 * Returns the resolved file path for a callable, or null when not determinable.
	 *
	 * @param mixed $hook_cb Any callable (string function name, [object|string, method] array, Closure).
	 * @return string|null Forward-slash normalised absolute path, or null.
	 */
	private static function get_callback_file( $hook_cb ): ?string {
		try {
			if ( $hook_cb instanceof \Closure || ( is_string( $hook_cb ) && function_exists( $hook_cb ) ) ) {
				$ref  = new \ReflectionFunction( $hook_cb );
				$file = $ref->getFileName();
			} elseif ( is_array( $hook_cb ) && 2 === count( $hook_cb ) ) {
				$ref  = new \ReflectionMethod( $hook_cb[0], $hook_cb[1] );
				$file = $ref->getFileName();
			} else {
				return null;
			}
			return false !== $file ? str_replace( '\\', '/', $file ) : null;
		} catch ( \ReflectionException $e ) {
			return null;
		}
	}

	/**
	 * Maps an absolute file path to a source descriptor (core, plugin, theme, or unknown).
	 *
	 * Checks plugin directories before ABSPATH to handle installations where
	 * wp-content resides inside the WordPress root.
	 *
	 * @param string $file Forward-slash normalised absolute file path.
	 * @return array{type: string, name: string, slug: string}
	 */
	private static function classify_file_source( string $file ): array {
		$content_dir = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );
		$plugins_dir = defined( 'WP_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' )
			: $content_dir . '/plugins';
		$mu_dir      = defined( 'WPMU_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WPMU_PLUGIN_DIR ), '/' )
			: $content_dir . '/mu-plugins';
		$themes_dir  = $content_dir . '/themes';
		$abspath     = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );

		if ( 0 === strpos( $file, $plugins_dir . '/' ) ) {
			$slug = explode( '/', substr( $file, strlen( $plugins_dir ) + 1 ) )[0];
			return array(
				'type' => 'plugin',
				'name' => $slug,
				'slug' => $slug,
			);
		}

		if ( 0 === strpos( $file, $mu_dir . '/' ) ) {
			$slug = explode( '/', substr( $file, strlen( $mu_dir ) + 1 ) )[0];
			return array(
				'type' => 'plugin',
				'name' => $slug . ' (mu)',
				'slug' => $slug,
			);
		}

		if ( 0 === strpos( $file, $themes_dir . '/' ) ) {
			$slug = explode( '/', substr( $file, strlen( $themes_dir ) + 1 ) )[0];
			return array(
				'type' => 'theme',
				'name' => $slug,
				'slug' => $slug,
			);
		}

		if ( 0 === strpos( $file, $abspath . '/' ) || $file === $abspath ) {
			return array(
				'type' => 'core',
				'name' => 'WordPress',
				'slug' => 'core',
			);
		}

		return array(
			'type' => 'unknown',
			'name' => '-',
			'slug' => '',
		);
	}

	/**
	 * Fallback source detection when no callbacks are registered in $wp_filter.
	 *
	 * Scans PHP files in plugin, theme, and WordPress core directories for the
	 * hook name string. Results are cached per request via a static variable to
	 * avoid repeated disk scans for the same hook.
	 *
	 * @param string $hook Hook name to search for in file contents.
	 * @return array{type: string, name: string, slug: string}
	 */
	private static function find_hook_in_files( string $hook ): array {
		static $cache = array();

		if ( array_key_exists( $hook, $cache ) ) {
			return $cache[ $hook ];
		}

		$content_dir = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );
		$plugins_dir = defined( 'WP_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' )
			: $content_dir . '/plugins';
		$mu_dir      = defined( 'WPMU_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WPMU_PLUGIN_DIR ), '/' )
			: $content_dir . '/mu-plugins';
		$themes_dir  = $content_dir . '/themes';
		$abspath     = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );

		$found = self::scan_slug_dirs( $hook, $plugins_dir, 'plugin' )
			?? self::scan_root_php_files( $hook, $plugins_dir, 'plugin' )
			?? self::scan_slug_dirs( $hook, $mu_dir, 'plugin', true )
			?? self::scan_root_php_files( $hook, $mu_dir, 'plugin' )
			?? self::scan_slug_dirs( $hook, $themes_dir, 'theme' );

		if ( null === $found ) {
			foreach ( array( $abspath . '/wp-includes', $abspath . '/wp-admin' ) as $core_dir ) {
				if ( self::hook_string_in_tree( $hook, $core_dir ) ) {
					$found = array(
						'type' => 'core',
						'name' => 'WordPress',
						'slug' => 'core',
					);
					break;
				}
			}
		}

		$result         = $found ?? array(
			'type' => 'unknown',
			'name' => '-',
			'slug' => '',
		);
		$cache[ $hook ] = $result;
		return $result;
	}

	/**
	 * Iterates the immediate subdirectories of $base_dir (plugin or theme slugs),
	 * returning a source descriptor for the first slug directory whose PHP file
	 * tree contains the hook name as a literal string.
	 *
	 * @param string $hook     Hook name to search for.
	 * @param string $base_dir Absolute path to the plugins or themes base directory.
	 * @param string $type     'plugin' or 'theme'.
	 * @param bool   $mu       Whether the directory is the must-use-plugins directory.
	 * @return array{type: string, name: string, slug: string}|null
	 */
	private static function scan_slug_dirs( string $hook, string $base_dir, string $type, bool $mu = false ): ?array {
		if ( ! is_dir( $base_dir ) ) {
			return null;
		}

		$slug_dirs = glob( rtrim( $base_dir, '/' ) . '/*', GLOB_ONLYDIR );
		if ( ! $slug_dirs ) {
			return null;
		}

		foreach ( $slug_dirs as $slug_dir ) {
			if ( self::hook_string_in_tree( $hook, $slug_dir ) ) {
				$slug = basename( $slug_dir );
				return array(
					'type' => $type,
					'name' => $mu ? $slug . ' (mu)' : $slug,
					'slug' => $slug,
				);
			}
		}

		return null;
	}

	/**
	 * Scans single PHP files at the immediate root of $base_dir (e.g. plugins/*.php)
	 * for the hook name literal, returning a source descriptor on first match.
	 *
	 * Handles plugins distributed as a single file rather than inside a sub-directory.
	 *
	 * @param string $hook     Hook name to search for.
	 * @param string $base_dir Absolute path to the base directory.
	 * @param string $type     'plugin' or 'theme'.
	 * @return array{type: string, name: string, slug: string}|null
	 */
	private static function scan_root_php_files( string $hook, string $base_dir, string $type ): ?array {
		if ( ! is_dir( $base_dir ) ) {
			return null;
		}

		$php_files = glob( rtrim( $base_dir, '/' ) . '/*.php' );
		if ( ! is_array( $php_files ) ) {
			$php_files = array();
		}
		foreach ( $php_files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = file_get_contents( $file );
			if ( false !== $contents && false !== strpos( $contents, $hook ) ) {
				$slug = basename( $file, '.php' );
				return array(
					'type' => $type,
					'name' => $slug,
					'slug' => $slug,
				);
			}
		}

		return null;
	}

	/**
	 * Returns true when at least one PHP file in the given directory tree
	 * contains the hook name as a literal substring.
	 *
	 * @param string $hook Hook name string to search for.
	 * @param string $dir  Absolute path to the directory to scan.
	 * @return bool
	 */
	private static function hook_string_in_tree( string $hook, string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		foreach ( $it as $php_file ) {
			if ( ! $php_file->isFile() || 'php' !== $php_file->getExtension() ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = file_get_contents( $php_file->getPathname() );
			if ( false !== $contents && false !== strpos( $contents, $hook ) ) {
				return true;
			}
		}

		return false;
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

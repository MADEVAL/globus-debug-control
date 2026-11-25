<?php
/**
 * Overview module: health, KPIs, charts and environment.
 *
 * Expects:
 * - $gdc         Output of GDC_Overview::get_data().
 * - $gdc_version Plugin version.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic output comes from GDC_UI helpers, which escape internally.

$gdc_options = $gdc['options'];
$gdc_enabled = ! empty( $gdc_options['enabled'] );
$gdc_health  = $gdc['health'];
$gdc_log     = $gdc['log'];
$gdc_last    = is_array( $gdc['last_fatal'] ) && ! empty( $gdc['last_fatal']['message'] ) ? $gdc['last_fatal'] : null;
$gdc_wp_ver  = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
$gdc_charts  = $gdc['charts'];
?>
<div class="gdc-page" data-page="overview">
	<div class="gdc-pagehead">
		<div>
			<h1 class="gdc-pagehead-title"><?php esc_html_e( 'Overview', 'globus-debug-control' ); ?></h1>
			<div class="gdc-pagehead-sub"><?php esc_html_e( 'Debug health, error log and diagnostics in one place.', 'globus-debug-control' ); ?></div>
		</div>
		<div class="gdc-pagehead-actions">
			<a class="gdc-btn gdc-btn--surface" href="<?php echo esc_url( GDC_UI::module_url( 'settings' ) ); ?>">
				<?php echo GDC_UI::icon( 'gear' ); ?>
				<?php esc_html_e( 'Settings', 'globus-debug-control' ); ?>
			</a>
			<a class="gdc-btn gdc-btn--pri" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>">
				<?php echo GDC_UI::icon( 'code' ); ?>
				<?php esc_html_e( 'Open error log', 'globus-debug-control' ); ?>
			</a>
		</div>
	</div>

	<div class="gdc-settings-layout">
		<?php
		$gdc_nav          = GDC_UI::nav( 'overview' );
		$gdc_active_label = __( 'Overview', 'globus-debug-control' );
		require GDC_INCLUDES . '/ui/partials/snav.php';
		?>

		<div class="gdc-settings-content">

			<div class="gdc-card gdc-hero gdc-mb-3h">
				<div class="gdc-hero-main">
					<span class="<?php echo $gdc_enabled ? 'gdc-tx-green' : 'gdc-tx-amber'; ?>" data-gdc-state-icon>
						<?php echo GDC_UI::icon( $gdc_enabled ? 'shieldCheck' : 'shield', 'gdc-ico--lg' ); ?>
					</span>
					<div class="gdc-fill">
						<div class="gdc-hero-title" data-gdc-state-title>
							<?php echo esc_html( $gdc_enabled ? __( 'Debug control is active', 'globus-debug-control' ) : __( 'Debug control is paused', 'globus-debug-control' ) ); ?>
						</div>
						<div class="gdc-hero-sub">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of suppressed error types */
									_n( '%d error type suppressed.', '%d error types suppressed.', (int) $gdc['suppressed'], 'globus-debug-control' ),
									(int) $gdc['suppressed']
								)
							);
							?>
						</div>
					</div>
					<div class="gdc-hero-toggle">
						<span class="gdc-fs-xs gdc-fw-semibold"><?php esc_html_e( 'Master switch', 'globus-debug-control' ); ?></span>
						<button
							type="button"
							class="gdc-toggle <?php echo $gdc_enabled ? 'is-on' : ''; ?>"
							role="switch"
							aria-checked="<?php echo $gdc_enabled ? 'true' : 'false'; ?>"
							data-gdc-ajax-toggle
							data-key="enabled"
							data-value="<?php echo $gdc_enabled ? '1' : '0'; ?>"
						>
							<span class="gdc-toggle-knob"></span>
						</button>
					</div>
				</div>
				<div class="gdc-hero-actions">
					<a class="gdc-btn" href="<?php echo esc_url( GDC_UI::module_url( 'wpcfg' ) ); ?>">
						<?php echo GDC_UI::icon( 'debug' ); ?>
						<?php esc_html_e( 'WP_DEBUG', 'globus-debug-control' ); ?>
					</a>
					<a class="gdc-btn" href="<?php echo esc_url( GDC_UI::module_url( 'settings' ) . '#gdc-setting-system' ); ?>">
						<?php echo GDC_UI::icon( 'system' ); ?>
						<?php esc_html_e( 'Environment', 'globus-debug-control' ); ?>
					</a>
				</div>
			</div>

			<div class="gdc-grid gdc-grid--4 gdc-mb-3h">
				<a class="gdc-card gdc-kpi" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>">
					<div class="gdc-kpi-label"><?php esc_html_e( 'Log size', 'globus-debug-control' ); ?></div>
					<div class="gdc-kpi-n"><span class="gdc-stat"><?php echo esc_html( $gdc_log['exists'] ? size_format( (int) $gdc_log['size'] ) : '—' ); ?></span></div>
					<div class="gdc-kpi-sub">
						<?php
						if ( null !== $gdc_log['lines'] ) {
							echo esc_html(
								sprintf(
									/* translators: %s: number of log lines */
									_n( '%s line', '%s lines', (int) $gdc_log['lines'], 'globus-debug-control' ),
									number_format_i18n( (int) $gdc_log['lines'] )
								)
							);
						} else {
							esc_html_e( 'No log file', 'globus-debug-control' );
						}
						?>
					</div>
				</a>

				<a class="gdc-card gdc-kpi" href="<?php echo esc_url( GDC_UI::module_url( 'cron' ) ); ?>">
					<div class="gdc-kpi-label"><?php esc_html_e( 'Cron orphans', 'globus-debug-control' ); ?></div>
					<div class="gdc-kpi-n"><span class="gdc-stat <?php echo (int) $gdc['cron']['orphans'] > 0 ? 'gdc-tx-amber' : ''; ?>"><?php echo esc_html( number_format_i18n( (int) $gdc['cron']['orphans'] ) ); ?></span></div>
					<div class="gdc-kpi-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of scheduled hooks */
								_n( '%s scheduled hook', '%s scheduled hooks', (int) $gdc['cron']['unique_hooks'], 'globus-debug-control' ),
								number_format_i18n( (int) $gdc['cron']['unique_hooks'] )
							)
						);
						?>
					</div>
				</a>

				<a class="gdc-card gdc-kpi" href="<?php echo esc_url( GDC_UI::module_url( 'transients' ) ); ?>">
					<div class="gdc-kpi-label"><?php esc_html_e( 'Expired transients', 'globus-debug-control' ); ?></div>
					<div class="gdc-kpi-n"><span class="gdc-stat <?php echo (int) $gdc['transients']['expired'] > 0 ? 'gdc-tx-amber' : ''; ?>"><?php echo esc_html( number_format_i18n( (int) $gdc['transients']['expired'] ) ); ?></span></div>
					<div class="gdc-kpi-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of stored transients */
								_n( 'of %s stored transient', 'of %s stored transients', (int) $gdc['transients']['total'], 'globus-debug-control' ),
								number_format_i18n( (int) $gdc['transients']['total'] )
							)
						);
						?>
					</div>
				</a>

				<a class="gdc-card gdc-kpi" href="<?php echo esc_url( GDC_UI::module_url( 'autoloaded' ) ); ?>">
					<div class="gdc-kpi-label"><?php esc_html_e( 'Autoload payload', 'globus-debug-control' ); ?></div>
					<div class="gdc-kpi-n"><span class="gdc-stat"><?php echo esc_html( size_format( (int) $gdc['autoload']['total_size'] ) ); ?></span></div>
					<div class="gdc-kpi-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of autoloaded options */
								_n( '%s autoloaded option', '%s autoloaded options', (int) $gdc['autoload']['count'], 'globus-debug-control' ),
								number_format_i18n( (int) $gdc['autoload']['count'] )
							)
						);
						?>
					</div>
				</a>
			</div>

			<div class="gdc-grid gdc-grid--2 gdc-mb-3h">
				<div class="gdc-card gdc-card-pad">
					<div class="gdc-section-head">
						<div class="gdc-fill">
							<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'Cron events, next 24 hours', 'globus-debug-control' ); ?></h2>
							<div class="gdc-fs-xs gdc-dim gdc-mt-xs"><?php esc_html_e( 'Scheduled events grouped in 3-hour buckets.', 'globus-debug-control' ); ?></div>
						</div>
						<?php echo GDC_UI::chip( number_format_i18n( array_sum( $gdc_charts['cron']['values'] ) ) . ' ' . __( 'events', 'globus-debug-control' ), 'blue' ); ?>
					</div>
					<?php if ( array_sum( $gdc_charts['cron']['values'] ) > 0 ) : ?>
						<div class="gdc-chart-box">
							<canvas
								data-gdc-chart="bar"
								data-label="<?php esc_attr_e( 'Events', 'globus-debug-control' ); ?>"
								data-labels="<?php echo esc_attr( (string) wp_json_encode( $gdc_charts['cron']['labels'] ) ); ?>"
								data-values="<?php echo esc_attr( (string) wp_json_encode( $gdc_charts['cron']['values'] ) ); ?>"></canvas>
						</div>
					<?php else : ?>
						<?php echo GDC_UI::empty_state( __( 'Nothing scheduled in the next 24 hours.', 'globus-debug-control' ), 'clock' ); ?>
					<?php endif; ?>
				</div>

				<div class="gdc-card gdc-card-pad">
					<div class="gdc-section-head">
						<div class="gdc-fill">
							<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'Heaviest autoloaded options', 'globus-debug-control' ); ?></h2>
							<div class="gdc-fs-xs gdc-dim gdc-mt-xs"><?php esc_html_e( 'Top rows that load on every request, in KB.', 'globus-debug-control' ); ?></div>
						</div>
						<?php echo GDC_UI::chip( __( 'Top 8', 'globus-debug-control' ), 'blue' ); ?>
					</div>
					<?php if ( ! empty( $gdc_charts['autoload']['values'] ) ) : ?>
						<div class="gdc-chart-box gdc-chart-box--wide">
							<canvas
								data-gdc-chart="bar"
								data-horizontal="1"
								data-label="<?php esc_attr_e( 'KB', 'globus-debug-control' ); ?>"
								data-labels="<?php echo esc_attr( (string) wp_json_encode( $gdc_charts['autoload']['labels'] ) ); ?>"
								data-values="<?php echo esc_attr( (string) wp_json_encode( $gdc_charts['autoload']['values'] ) ); ?>"></canvas>
						</div>
					<?php else : ?>
						<?php echo GDC_UI::empty_state( __( 'No autoloaded options found.', 'globus-debug-control' ), 'database' ); ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="gdc-card gdc-card-pad gdc-mb-3h" id="gdc-health">
				<div class="gdc-status-head">
					<div class="gdc-status-main">
						<?php echo GDC_UI::gauge( (int) $gdc_health['score'], (string) $gdc_health['label'] ); ?>
						<div class="gdc-fill">
							<h2 class="gdc-section-title gdc-card-title gdc-fs-xl"><?php esc_html_e( 'System health', 'globus-debug-control' ); ?></h2>
							<div class="gdc-muted gdc-mt-2 gdc-fs-sm gdc-max-desc"><?php esc_html_e( 'Every check below opens the screen where it can be fixed.', 'globus-debug-control' ); ?></div>
						</div>
					</div>
					<div class="gdc-status-stats">
						<div class="status-stat">
							<div class="gdc-stat gdc-stat--sm gdc-tx-green"><?php echo esc_html( (string) $gdc_health['active'] ); ?></div>
							<div class="gdc-dim gdc-fs-xs gdc-mt-1"><?php esc_html_e( 'Active', 'globus-debug-control' ); ?></div>
						</div>
						<div class="status-stat">
							<div class="gdc-stat gdc-stat--sm"><?php echo esc_html( (string) $gdc_health['disabled'] ); ?></div>
							<div class="gdc-dim gdc-fs-xs gdc-mt-1"><?php esc_html_e( 'Disabled', 'globus-debug-control' ); ?></div>
						</div>
						<div class="status-stat">
							<div class="gdc-stat gdc-stat--sm gdc-tx-amber"><?php echo esc_html( (string) $gdc_health['attention'] ); ?></div>
							<div class="gdc-dim gdc-fs-xs gdc-mt-1"><?php esc_html_e( 'Attention', 'globus-debug-control' ); ?></div>
						</div>
					</div>
				</div>

				<div class="gdc-divider gdc-mt-4h"></div>

				<div class="gdc-statusgrid gdc-mt-4h">
					<?php foreach ( $gdc_health['groups'] as $gdc_group ) : ?>
						<div class="gdc-col gdc-g-1">
							<div class="gdc-fw-semibold gdc-fs-xs gdc-tx2 gdc-mb-2"><?php echo esc_html( (string) $gdc_group['title'] ); ?></div>
							<?php foreach ( $gdc_group['items'] as $gdc_item ) : ?>
								<?php echo GDC_UI::checklist_item( $gdc_item ); ?>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="gdc-grid gdc-grid--2">
				<div class="gdc-card gdc-card-pad">
					<h2 class="gdc-section-title gdc-card-title gdc-mb-3"><?php esc_html_e( 'Environment', 'globus-debug-control' ); ?></h2>
					<div class="gdc-col gdc-g-2">
						<?php
						echo GDC_UI::kv( __( 'Plugin version', 'globus-debug-control' ), esc_html( $gdc_version ), true );
						echo GDC_UI::kv( __( 'WordPress', 'globus-debug-control' ), esc_html( $gdc_wp_ver ), true );
						echo GDC_UI::kv( __( 'PHP', 'globus-debug-control' ), esc_html( PHP_VERSION ), true );
						echo GDC_UI::kv( 'WP_DEBUG', GDC_UI::bool_text( ! empty( $gdc['wp_config']['wp_debug'] ) ) );
						echo GDC_UI::kv( 'WP_DEBUG_LOG', GDC_UI::bool_text( ! empty( $gdc['wp_config']['wp_debug_log'] ) ) );
						echo GDC_UI::kv( __( 'MU dropin', 'globus-debug-control' ), GDC_UI::bool_text( ! empty( $gdc['mu']['installed'] ), ! empty( $gdc['mu']['installed'] ) ? __( 'Installed', 'globus-debug-control' ) : __( 'Not installed', 'globus-debug-control' ) ) );
						echo GDC_UI::kv( __( 'Log file', 'globus-debug-control' ), '' !== (string) $gdc_log['path'] ? '<code>' . esc_html( (string) $gdc_log['path'] ) . '</code>' : esc_html__( 'Not configured', 'globus-debug-control' ), true );
						?>
					</div>
				</div>

				<div class="gdc-card gdc-card-pad">
					<h2 class="gdc-section-title gdc-card-title gdc-mb-3"><?php esc_html_e( 'Recent activity', 'globus-debug-control' ); ?></h2>

					<?php if ( null !== $gdc_last ) : ?>
						<div class="gdc-alert gdc-alert--danger gdc-mb-3">
							<p class="gdc-fw-semibold"><?php esc_html_e( 'Last fatal error', 'globus-debug-control' ); ?></p>
							<p class="gdc-mono gdc-fs-2xs"><?php echo esc_html( (string) $gdc_last['message'] ); ?></p>
							<?php if ( ! empty( $gdc_last['file'] ) ) : ?>
								<p class="gdc-mono gdc-fs-2xs gdc-mt-2"><?php echo esc_html( (string) $gdc_last['file'] . ':' . (int) ( $gdc_last['line'] ?? 0 ) ); ?></p>
							<?php endif; ?>
							<a class="gdc-btn gdc-btn--sm gdc-mt-2" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>">
								<?php echo GDC_UI::icon( 'code', 'gdc-ico--xs' ); ?>
								<?php esc_html_e( 'Open log', 'globus-debug-control' ); ?>
							</a>
						</div>
					<?php else : ?>
						<?php echo GDC_UI::alert( '<strong>' . esc_html__( 'No fatal errors recorded.', 'globus-debug-control' ) . '</strong>', 'success' ); ?>
					<?php endif; ?>

					<div class="gdc-divider">
						<div class="gdc-fw-semibold gdc-fs-xs gdc-tx2 gdc-mb-2"><?php esc_html_e( 'Quick actions', 'globus-debug-control' ); ?></div>
						<div class="gdc-col gdc-g-1">
							<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'wpcfg' ) ); ?>"><?php echo GDC_UI::icon( 'debug', 'gdc-ico--xs' ); ?> <?php esc_html_e( 'Toggle wp-config constants', 'globus-debug-control' ); ?></a>
							<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'cron' ) ); ?>"><?php echo GDC_UI::icon( 'clock', 'gdc-ico--xs' ); ?> <?php esc_html_e( 'Review cron tasks', 'globus-debug-control' ); ?></a>
							<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'transients' ) ); ?>"><?php echo GDC_UI::icon( 'transient', 'gdc-ico--xs' ); ?> <?php esc_html_e( 'Purge expired transients', 'globus-debug-control' ); ?></a>
							<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'http' ) ); ?>"><?php echo GDC_UI::icon( 'globe', 'gdc-ico--xs' ); ?> <?php esc_html_e( 'Inspect HTTP requests', 'globus-debug-control' ); ?></a>
							<a class="gdc-link gdc-fs-sm" href="<?php echo esc_url( GDC_UI::module_url( 'constants' ) ); ?>"><?php echo GDC_UI::icon( 'list', 'gdc-ico--xs' ); ?> <?php esc_html_e( 'Browse constants', 'globus-debug-control' ); ?></a>
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>
<?php
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

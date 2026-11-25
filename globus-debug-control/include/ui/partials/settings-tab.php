<?php
/**
 * Settings module: plugin options, filtering, logging, early interception.
 *
 * Expects:
 * - $gdc_opts            Current plugin options.
 * - $gdc_opt             Option name used in field names.
 * - $gdc_log_path        Resolved log file path.
 * - $gdc_log_exists      Whether the log file exists.
 * - $gdc_log_size        Log file size in bytes.
 * - $gdc_mu_installed    Whether the mu-plugin dropin is installed.
 * - $gdc_is_api          Whether the current request runs in an API context.
 * - $gdc_settings_saved  Whether the settings were just saved.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic output below comes from GDC_UI helpers, which escape internally.

$gdc_is_php8 = version_compare( PHP_VERSION, '8.0.0', '>=' );
$gdc_field   = $gdc_opt . '[';
?>
<div class="gdc-page" data-page="settings">
	<?php if ( $gdc_settings_saved ) : ?>
		<div class="gdc-alert gdc-alert--success gdc-mb-3h" role="status">
			<?php esc_html_e( 'Settings saved.', 'globus-debug-control' ); ?>
		</div>
	<?php endif; ?>

	<div class="gdc-dependency">
		<?php GDC_WpConfig_Debug::render_dependency_notice( 'settings' ); ?>
	</div>

	<form method="post" action="options.php" id="gdc-settings-form">
		<?php settings_fields( 'globus_debug_control' ); ?>

		<div class="gdc-pagehead">
			<div>
				<h1 class="gdc-pagehead-title"><?php esc_html_e( 'Settings', 'globus-debug-control' ); ?></h1>
				<div class="gdc-pagehead-sub"><?php esc_html_e( 'Filter PHP errors, control display and logging, and load the filter before other plugins.', 'globus-debug-control' ); ?></div>
			</div>
			<div class="gdc-pagehead-actions">
				<a class="gdc-btn gdc-btn--surface" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>">
					<?php echo GDC_UI::icon( 'code' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
					<?php esc_html_e( 'Open error log', 'globus-debug-control' ); ?>
				</a>
				<button type="submit" class="gdc-btn gdc-btn--pri">
					<?php echo GDC_UI::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
					<?php esc_html_e( 'Save changes', 'globus-debug-control' ); ?>
				</button>
			</div>
		</div>

		<div class="gdc-settings-layout">
			<?php
			$gdc_nav          = GDC_UI::nav( 'settings' );
			$gdc_active_label = __( 'Settings', 'globus-debug-control' );
			require GDC_INCLUDES . '/ui/partials/snav.php';
			?>

			<div class="gdc-settings-content">
				<div class="gdc-grid gdc-grid--2 gdc-mb-3h">

					<div class="gdc-card gdc-card-pad" id="gdc-setting-enabled">
						<div class="gdc-section-head">
							<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'Plugin status', 'globus-debug-control' ); ?></h2>
							<?php echo GDC_UI::chip( ! empty( $gdc_opts['enabled'] ) ? __( 'Active', 'globus-debug-control' ) : __( 'Paused', 'globus-debug-control' ), ! empty( $gdc_opts['enabled'] ) ? 'green' : 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
						</div>

						<?php
						echo GDC_UI::switch_row(
							$gdc_field . 'enabled]',
							! empty( $gdc_opts['enabled'] ),
							__( 'Enable debug control', 'globus-debug-control' ),
							__( 'When disabled, the plugin does not change PHP or WordPress behaviour.', 'globus-debug-control' ),
							array( 'id' => 'gdc-opt-enabled' )
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
						?>

						<div class="gdc-divider">
							<div class="gdc-row gdc-g-2h gdc-row--wrap">
								<?php echo GDC_UI::status_chip( GDC_WpConfig_Debug::is_wp_debug_on(), 'WP_DEBUG ON', 'WP_DEBUG OFF' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
								<?php echo GDC_UI::status_chip( GDC_WpConfig_Debug::is_wp_debug_log_on(), 'WP_DEBUG_LOG ON', 'WP_DEBUG_LOG OFF' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
								<?php echo GDC_UI::status_chip( ! empty( $gdc_opts['log_enabled'] ), __( 'Plugin logging on', 'globus-debug-control' ), __( 'Plugin logging off', 'globus-debug-control' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
							</div>
							<?php if ( ! GDC_WpConfig_Debug::is_wp_debug_on() ) : ?>
								<p class="gdc-field-desc gdc-mt-2"><?php esc_html_e( 'Error filtering has no effect while WP_DEBUG is off.', 'globus-debug-control' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="gdc-card gdc-card-pad" id="gdc-setting-filtering">
						<h2 class="gdc-section-title gdc-card-title gdc-mb-3"><?php esc_html_e( 'Error filtering', 'globus-debug-control' ); ?></h2>
						<p class="gdc-field-desc gdc-mb-2h"><?php esc_html_e( 'Selected types are neither displayed nor logged. Fatal errors are always visible.', 'globus-debug-control' ); ?></p>

						<?php
						echo GDC_UI::switch_row(
							$gdc_field . 'suppress_notices]',
							! empty( $gdc_opts['suppress_notices'] ),
							'E_NOTICE',
							__( 'Also hides E_USER_NOTICE.', 'globus-debug-control' ),
							array( 'id' => 'gdc-opt-notices' )
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.

						echo GDC_UI::switch_row(
							$gdc_field . 'suppress_deprecated]',
							! empty( $gdc_opts['suppress_deprecated'] ),
							'E_DEPRECATED',
							__( 'Also hides E_USER_DEPRECATED.', 'globus-debug-control' ),
							array(
								'id'    => 'gdc-opt-deprecated',
								'color' => 'green',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.

						echo GDC_UI::switch_row(
							$gdc_field . 'suppress_warnings]',
							! empty( $gdc_opts['suppress_warnings'] ),
							'E_WARNING',
							__( 'Also hides E_USER_WARNING. Use with caution.', 'globus-debug-control' ),
							array(
								'id'    => 'gdc-opt-warnings',
								'color' => 'amber',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.

						echo GDC_UI::switch_row(
							$gdc_field . 'suppress_strict]',
							! empty( $gdc_opts['suppress_strict'] ),
							'E_STRICT',
							$gdc_is_php8
								? __( 'Not available in PHP 8.0 and newer.', 'globus-debug-control' )
								: __( 'Legacy strict standards messages.', 'globus-debug-control' ),
							array(
								'id'       => 'gdc-opt-strict',
								'disabled' => $gdc_is_php8,
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
						?>
					</div>

					<div class="gdc-card gdc-card-pad" id="gdc-setting-display">
						<h2 class="gdc-section-title gdc-card-title gdc-mb-3"><?php esc_html_e( 'Display', 'globus-debug-control' ); ?></h2>

						<label class="gdc-field" for="gdc-opt-display-mode">
							<?php echo GDC_UI::field_label( __( 'display_errors mode', 'globus-debug-control' ), __( 'Controls on-screen error output. API contexts always disable display.', 'globus-debug-control' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
							<select class="gdc-input" id="gdc-opt-display-mode" name="<?php echo esc_attr( $gdc_field . 'display_errors_mode]' ); ?>">
								<option value="inherit" <?php selected( $gdc_opts['display_errors_mode'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from WP_DEBUG_DISPLAY', 'globus-debug-control' ); ?></option>
								<option value="on" <?php selected( $gdc_opts['display_errors_mode'], 'on' ); ?>><?php esc_html_e( 'Always on', 'globus-debug-control' ); ?></option>
								<option value="off" <?php selected( $gdc_opts['display_errors_mode'], 'off' ); ?>><?php esc_html_e( 'Always off', 'globus-debug-control' ); ?></option>
							</select>
						</label>

						<div class="gdc-divider">
							<?php
							echo GDC_UI::switch_row(
								$gdc_field . 'remove_php_error_bodyclass]',
								! empty( $gdc_opts['remove_php_error_bodyclass'] ),
								__( 'Remove the php-error body class', 'globus-debug-control' ),
								__( 'WordPress adds this class when errors are detected. It can break theme styling.', 'globus-debug-control' ),
								array( 'id' => 'gdc-opt-bodyclass' )
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							?>
						</div>

						<div id="gdc-setting-api">
							<?php
							echo GDC_UI::switch_row(
								$gdc_field . 'force_in_api_contexts]',
								! empty( $gdc_opts['force_in_api_contexts'] ),
								__( 'Show errors in API contexts', 'globus-debug-control' ),
								__( 'By default, display is disabled for AJAX, REST, Cron and CLI requests.', 'globus-debug-control' ),
								array( 'id' => 'gdc-opt-api' )
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							?>
						</div>
					</div>

					<div class="gdc-card gdc-card-pad" id="gdc-setting-logging">
						<div class="gdc-section-head">
							<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'Logging', 'globus-debug-control' ); ?></h2>
							<?php echo GDC_UI::chip( $gdc_log_exists ? size_format( (int) $gdc_log_size ) : __( 'No file', 'globus-debug-control' ), $gdc_log_exists ? 'blue' : 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
						</div>

						<?php
						echo GDC_UI::switch_row(
							$gdc_field . 'log_enabled]',
							! empty( $gdc_opts['log_enabled'] ),
							__( 'Enable error logging', 'globus-debug-control' ),
							__( 'Writes PHP errors to the log file below.', 'globus-debug-control' ),
							array(
								'id'    => 'gdc-opt-log-enabled',
								'color' => 'green',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
						?>

						<label class="gdc-field gdc-mt-2h" for="gdc-opt-log-path" id="gdc-setting-logpath">
							<?php echo GDC_UI::field_label( __( 'Log file path', 'globus-debug-control' ), __( 'Absolute path, or a path relative to wp-content. Leave empty for auto-detection.', 'globus-debug-control' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
							<input class="gdc-input gdc-input--mono" type="text" id="gdc-opt-log-path" name="<?php echo esc_attr( $gdc_field . 'log_path]' ); ?>" value="<?php echo esc_attr( (string) $gdc_opts['log_path'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for auto-detection', 'globus-debug-control' ); ?>" />
							<span class="gdc-field-desc">
								<?php
								if ( '' !== $gdc_log_path ) {
									echo esc_html( $gdc_log_path );
									echo $gdc_log_exists ? '' : ' &middot; ' . esc_html__( 'file does not exist yet', 'globus-debug-control' );
								} else {
									esc_html_e( 'Not configured.', 'globus-debug-control' );
								}
								?>
							</span>
						</label>

						<label class="gdc-field" for="gdc-opt-log-size" id="gdc-setting-logsize">
							<?php echo GDC_UI::field_label( __( 'Maximum log size', 'globus-debug-control' ), __( 'When exceeded, the file is rotated with a date suffix and a new one is created.', 'globus-debug-control' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
							<span class="gdc-field-box">
								<input class="gdc-input" type="number" id="gdc-opt-log-size" name="<?php echo esc_attr( $gdc_field . 'log_max_size_mb]' ); ?>" value="<?php echo esc_attr( (string) $gdc_opts['log_max_size_mb'] ); ?>" min="1" max="100" step="1" />
								<span class="gdc-field-suffix"><?php esc_html_e( 'MB', 'globus-debug-control' ); ?></span>
							</span>
						</label>
					</div>

					<div class="gdc-card gdc-card-pad" id="gdc-setting-mu">
						<div class="gdc-section-head">
							<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'Early interception', 'globus-debug-control' ); ?></h2>
							<?php echo GDC_UI::chip( $gdc_mu_installed ? __( 'Installed', 'globus-debug-control' ) : __( 'Not installed', 'globus-debug-control' ), $gdc_mu_installed ? 'green' : 'amber' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
						</div>

						<?php
						echo GDC_UI::switch_row(
							$gdc_field . 'install_mu_dropin]',
							! empty( $gdc_opts['install_mu_dropin'] ),
							__( 'Install the mu-plugin dropin', 'globus-debug-control' ),
							__( 'Loads the error filter before regular plugins, so errors from plugins loaded earlier are intercepted too.', 'globus-debug-control' ),
							array(
								'id'    => 'gdc-opt-mu',
								'color' => 'green',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
						?>
						<div class="gdc-divider gdc-fs-xs">
							<?php echo GDC_UI::kv( __( 'Dropin file', 'globus-debug-control' ), '<code>' . esc_html( 'gdc-early-loader.php' ) . '</code> &middot; <code>wp-content/mu-plugins/</code>', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
						</div>
					</div>
				</div>

				<div class="gdc-card gdc-card-pad" id="gdc-setting-system">
					<div class="gdc-section-head">
						<h2 class="gdc-section-title gdc-card-title"><?php esc_html_e( 'System information', 'globus-debug-control' ); ?></h2>
						<?php echo GDC_UI::chip( $gdc_is_api ? __( 'API context', 'globus-debug-control' ) : __( 'Normal request', 'globus-debug-control' ), $gdc_is_api ? 'amber' : 'blue' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?>
					</div>

					<div class="gdc-grid gdc-grid--2">
						<div class="gdc-col gdc-g-2">
							<?php
							echo GDC_UI::kv( __( 'Plugin version', 'globus-debug-control' ), esc_html( (string) $gdc_version ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( __( 'PHP version', 'globus-debug-control' ), esc_html( PHP_VERSION ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( 'WP_DEBUG', GDC_UI::bool_text( defined( 'WP_DEBUG' ) && WP_DEBUG ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( 'WP_DEBUG_DISPLAY', GDC_UI::bool_text( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( 'WP_DEBUG_LOG', GDC_UI::bool_text( GDC_WpConfig_Debug::is_wp_debug_log_on() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							?>
						</div>
						<div class="gdc-col gdc-g-2">
							<?php
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting,PluginCheck.CodeAnalysis.PHPErrorReporting.DirectErrorReportingCall -- Read-only diagnostic display, not a runtime change.
							$gdc_error_level = error_reporting();
							echo GDC_UI::kv( __( 'error_reporting', 'globus-debug-control' ), esc_html( (string) $gdc_error_level ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( 'display_errors', '<code>' . esc_html( (string) ini_get( 'display_errors' ) ) . '</code>', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( 'log_errors', '<code>' . esc_html( (string) ini_get( 'log_errors' ) ) . '</code>', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( __( 'MU dropin', 'globus-debug-control' ), GDC_UI::bool_text( $gdc_mu_installed, $gdc_mu_installed ? __( 'Installed', 'globus-debug-control' ) : __( 'Not installed', 'globus-debug-control' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							echo GDC_UI::kv( __( 'Log file', 'globus-debug-control' ), '' !== $gdc_log_path ? '<code>' . esc_html( $gdc_log_path ) . '</code>' : esc_html__( 'Not configured', 'globus-debug-control' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
							?>
						</div>
					</div>
				</div>

				<div class="gdc-settings-actions">
					<span class="gdc-dim gdc-fs-xs"><?php esc_html_e( 'Changes are stored in a single option and sanitized on save.', 'globus-debug-control' ); ?></span>
					<button type="submit" class="gdc-btn gdc-btn--pri">
						<?php echo GDC_UI::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<?php esc_html_e( 'Save changes', 'globus-debug-control' ); ?>
					</button>
				</div>
			</div>
		</div>
	</form>
</div>
<?php
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped


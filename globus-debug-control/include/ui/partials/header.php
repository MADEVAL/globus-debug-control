<?php
/**
 * Shared admin header: logo, version, global search, quick actions.
 *
 * Expects:
 * - $gdc_version     Plugin version string.
 * - $gdc_wp_debug_on Whether WP_DEBUG is currently on.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gdc_version     = isset( $gdc_version ) ? (string) $gdc_version : '';
$gdc_wp_debug_on = ! empty( $gdc_wp_debug_on );
?>
<div class="gdc-plughead">
	<div class="gdc-plughead-left">
		<a class="gdc-logo" href="<?php echo esc_url( GDC_UI::page_url() ); ?>">
			<img class="gdc-logo-img" src="<?php echo esc_url( GDC_ASSETS_URL . 'img/logo.png' ); ?>" alt="<?php esc_attr_e( 'GLOBUS Debug Control', 'globus-debug-control' ); ?>" />
		</a>
		<span class="gdc-chip gdc-chip--kbd">v<?php echo esc_html( $gdc_version ); ?></span>
		<span class="gdc-chip <?php echo $gdc_wp_debug_on ? 'gdc-chip--red' : 'gdc-chip--green'; ?>" title="<?php esc_attr_e( 'Current WP_DEBUG state', 'globus-debug-control' ); ?>">
			<span class="gdc-dot <?php echo $gdc_wp_debug_on ? 'gdc-dot--red' : ''; ?>"></span>
			WP_DEBUG <?php echo $gdc_wp_debug_on ? 'ON' : 'OFF'; ?>
		</span>
	</div>

	<button type="button" class="gdc-search" id="gdc-search" aria-label="<?php esc_attr_e( 'Open settings search', 'globus-debug-control' ); ?>">
		<?php echo GDC_UI::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		<span class="gdc-search-label"><?php esc_html_e( 'Find a setting or screen... (or press Ctrl K)', 'globus-debug-control' ); ?></span>
		<span class="gdc-chip gdc-chip--kbd">Ctrl K</span>
	</button>

	<div class="gdc-plughead-right">
		<a class="gdc-btn gdc-btn--ghost gdc-btn--icon" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>" title="<?php esc_attr_e( 'Error Log', 'globus-debug-control' ); ?>" aria-label="<?php esc_attr_e( 'Error Log', 'globus-debug-control' ); ?>">
			<?php echo GDC_UI::icon( 'code', 'gdc-ico--md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		</a>
		<a class="gdc-btn gdc-btn--ghost gdc-btn--icon" href="<?php echo esc_url( GDC_UI::module_url( 'settings' ) ); ?>" title="<?php esc_attr_e( 'Settings', 'globus-debug-control' ); ?>" aria-label="<?php esc_attr_e( 'Settings', 'globus-debug-control' ); ?>">
			<?php echo GDC_UI::icon( 'gear', 'gdc-ico--md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		</a>
		<a class="gdc-btn gdc-btn--ghost gdc-btn--icon" href="https://wordpress.org/plugins/globus-debug-control/" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Plugin page and documentation', 'globus-debug-control' ); ?>" aria-label="<?php esc_attr_e( 'Plugin page and documentation', 'globus-debug-control' ); ?>">
			<?php echo GDC_UI::icon( 'support', 'gdc-ico--md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		</a>
		<button type="button" class="gdc-btn gdc-btn--ghost gdc-btn--icon gdc-search-mob" id="gdc-search-mob" aria-label="<?php esc_attr_e( 'Search', 'globus-debug-control' ); ?>">
			<?php echo GDC_UI::icon( 'search', 'gdc-ico--md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		</button>
	</div>
</div>

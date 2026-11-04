<?php
/**
 * GLOBUS Debug Control - Uninstall
 *
 * Removes plugin options and mu-plugin dropin when the plugin is deleted.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'globus_debug_control_options' );
delete_option( 'gdc_options' ); // Legacy key - remove if present.
delete_option( 'gdc_http_log_capturing' );
delete_option( 'gdc_last_fatal' );
delete_transient( 'gdc_http_log' );

// Remove mu-plugin dropin if it exists.
$globus_debug_control_mu_file = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' ) ) . '/gdc-early-loader.php';
if ( file_exists( $globus_debug_control_mu_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading mu-plugin file to verify ownership before deletion.
	$globus_debug_control_content = @file_get_contents( $globus_debug_control_mu_file );
	if ( false !== $globus_debug_control_content && false !== strpos( $globus_debug_control_content, 'GLOBUS Debug Control' ) ) {
		// wp_delete_file() lives in wp-admin/includes/file.php which is NOT loaded
		// during uninstall - use a plain unlink instead.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Uninstall context, wp_delete_file() is unavailable.
		@unlink( $globus_debug_control_mu_file );
	}
}

<?php
/**
 * GLOBUS Debug Control - Uninstall
 *
 * Removes plugin options and mu-plugin dropin when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'globus_debug_control_options' );
delete_option( 'gdc_options' ); // Legacy key - remove if present.

// Remove mu-plugin dropin if it exists.
$globus_debug_control_mu_file = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' ) ) . '/gdc-early-loader.php';
if ( file_exists( $globus_debug_control_mu_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading mu-plugin file to verify ownership before deletion.
	$globus_debug_control_content = @file_get_contents( $globus_debug_control_mu_file );
	if ( false !== $globus_debug_control_content && false !== strpos( $globus_debug_control_content, 'GLOBUS Debug Control' ) ) {
		wp_delete_file( $globus_debug_control_mu_file );
	}
}

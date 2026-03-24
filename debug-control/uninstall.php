<?php
/**
 * Debug Control — Uninstall
 *
 * Removes plugin options and mu-plugin dropin when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gdc_options' );

// Remove mu-plugin dropin if it exists.
$debug_control_mu_file = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' ) ) . '/gdc-early-loader.php';
if ( file_exists( $debug_control_mu_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading mu-plugin file to verify ownership before deletion.
	$debug_control_content = @file_get_contents( $debug_control_mu_file );
	if ( false !== $debug_control_content && false !== strpos( $debug_control_content, 'Debug Control' ) ) {
		wp_delete_file( $debug_control_mu_file );
	}
}

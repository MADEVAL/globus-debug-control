<?php
/**
 * GLOBUS Debug Control — Uninstall
 *
 * Removes plugin options and mu-plugin dropin when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gdc_options' );

// Remove mu-plugin dropin if it exists.
$gdc_mu_file = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' ) ) . '/gdc-early-loader.php';
if ( file_exists( $gdc_mu_file ) ) {
	$gdc_content = @file_get_contents( $gdc_mu_file );
	if ( false !== $gdc_content && false !== strpos( $gdc_content, 'GLOBUS Debug Control' ) ) {
		@unlink( $gdc_mu_file );
	}
}

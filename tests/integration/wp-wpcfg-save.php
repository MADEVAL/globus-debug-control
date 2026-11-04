<?php
/**
 * Exercises GDC_WpConfig_Debug::handle_action() against the real wp-config.php.
 *
 * handle_action() ends with exit(), so this must run as its own process.
 * The original config is snapshotted to the runtime dir; the caller restores it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$runtime   = sys_get_temp_dir() . '/gdc-itest';
$marker    = $runtime . '/wpcfg.marker';
$snapshot  = $runtime . '/original-wp-config.txt';
$config    = GDC_WpConfig_Debug::find_wp_config();

if ( '' === $config || ! is_file( $config ) ) {
	fwrite( STDERR, "wp-config.php not found\n" );
	exit( 1 );
}

if ( ! is_dir( $runtime ) ) {
	mkdir( $runtime, 0777, true );
}

wp_set_current_user( 1 );

file_put_contents( $snapshot, (string) file_get_contents( $config ) );
@unlink( $marker );

$nonce = wp_create_nonce( 'gdc_wp_config_debug' );
$_POST['gdc_wpcfg_save'] = '1';
$_POST['_wpnonce']       = $nonce;
$_REQUEST['_wpnonce']    = $nonce;
$_POST['gdc_wpcfg']      = array( 'WP_DEBUG_LOG' => 'true' );

register_shutdown_function(
	static function () use ( $marker ) {
		file_put_contents( $marker, "EXITED\n" );
	}
);

GDC_WpConfig_Debug::handle_action(); // writes the constant, then exit()

exit( 2 );

<?php
/**
 * Exercises GDC_Log_Viewer::handle_clear_action() against the real debug.log.
 *
 * The handler ends with exit(), so this must run as its own process. The caller
 * verifies the marker file and that the log was truncated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$runtime  = sys_get_temp_dir() . '/gdc-itest';
$marker   = $runtime . '/clear.marker';
$log_file = GDC_Log_Viewer::get_log_path();

if ( ! is_dir( $runtime ) ) {
	mkdir( $runtime, 0777, true );
}

wp_set_current_user( 1 );

@mkdir( dirname( $log_file ), 0777, true );
file_put_contents( $log_file, "GDC_ITEST_TO_BE_CLEARED\n" );
@unlink( $marker );

$nonce = wp_create_nonce( 'gdc_log_clear' );
$_POST['gdc_clear_log'] = '1';
$_POST['_wpnonce']      = $nonce;
$_REQUEST['_wpnonce']   = $nonce;

register_shutdown_function(
	static function () use ( $marker ) {
		file_put_contents( $marker, "EXITED\n" );
	}
);

GDC_Log_Viewer::handle_clear_action(); // truncates the log, then exit()

exit( 2 );

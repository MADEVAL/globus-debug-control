<?php
/**
 * Enables HTTP capturing and clears the previous log.
 * Must run in its own process so the plugin registers http_api_debug at load.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

GDC_Http_Log::set_capturing( true );
delete_transient( 'gdc_http_log' );

echo GDC_Http_Log::is_capturing() ? "CAPTURING ON\n" : "CAPTURING FAILED\n";
exit( GDC_Http_Log::is_capturing() ? 0 : 1 );

<?php
/**
 * Fires a real outgoing HTTP request so the HTTP log captures it.
 * Must run in its own process (the capture hook is registered at plugin load).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$response = wp_remote_get( 'https://example.com/?api_key=supersecret123', array( 'timeout' => 15 ) );

if ( is_wp_error( $response ) ) {
	fwrite( STDERR, 'wp_remote_get failed: ' . $response->get_error_message() . "\n" );
	exit( 1 );
}

echo 'REQUEST SENT: ' . wp_remote_retrieve_response_code( $response ) . "\n";
exit( 0 );

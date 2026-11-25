<?php
/**
 * Regenerates the static screenshot templates from the local WordPress sandbox.
 *
 * Dev-only tool. Requires `wordpress/wp-load.php` (the gitignored sandbox) with
 * the plugin active. Demo options, an HTTP log and a clean debug.log fixture are
 * injected for the duration of the render, then everything is restored.
 *
 * Usage:
 *   php tools/screenshots/generate-templates.php
 */

require dirname( __DIR__, 2 ) . '/wordpress/wp-load.php';

wp_set_current_user( 1 );

$pages = array(
	'overview'   => array( 'globus-debug-control', '01-overview.html' ),
	'settings'   => array( 'gdc-settings', '02-settings.html' ),
	'wp-debug'   => array( 'gdc-wp-debug', '03-wp-debug.html' ),
	'error-log'  => array( 'gdc-log', '04-error-log.html' ),
	'cron'       => array( 'gdc-cron', '05-cron.html' ),
	'transients' => array( 'gdc-transients', '06-transients.html' ),
	'autoloaded' => array( 'gdc-autoloaded', '07-autoloaded.html' ),
	'http-log'   => array( 'gdc-http', '08-http-log.html' ),
	'constants'  => array( 'gdc-constants', '09-constants.html' ),
);

$plugin = Globus_Debug_Control::instance();

$fixture_log = __DIR__ . '/fixtures/debug.log';

$original_options = get_option( 'globus_debug_control_options' );
$demo_options     = is_array( $original_options ) ? $original_options : array();

$demo_options['enabled']      = 1;
$demo_options['log_enabled']  = 1;
$demo_options['log_path']     = $fixture_log;
$demo_options['log_max_size_mb'] = 10;

update_option( 'globus_debug_control_options', $demo_options );

$original_http    = get_transient( 'gdc_http_log' );
$original_capture = get_option( 'gdc_http_log_capturing' );

update_option( 'gdc_http_log_capturing', 1 );
set_transient(
	'gdc_http_log',
	array(
		array(
			'ts'        => strtotime( '2026-11-24 14:31:07' ),
			'method'    => 'GET',
			'url'       => 'https://api.wordpress.org/core/version-check/1.7/',
			'status'    => 200,
			'duration'  => 0.412,
			'transport' => 'WP_Http_Curl',
			'error'     => '',
		),
		array(
			'ts'        => strtotime( '2026-11-24 14:28:55' ),
			'method'    => 'POST',
			'url'       => 'https://api.example.com/v1/orders/sync?token=***',
			'status'    => 201,
			'duration'  => 0.684,
			'transport' => 'WP_Http_Curl',
			'error'     => '',
		),
		array(
			'ts'        => strtotime( '2026-11-24 14:12:31' ),
			'method'    => 'GET',
			'url'       => 'https://updates.example.com/plugins/acme-forms.json',
			'status'    => 304,
			'duration'  => 0.231,
			'transport' => 'WP_Http_Curl',
			'error'     => '',
		),
		array(
			'ts'        => strtotime( '2026-11-24 13:58:02' ),
			'method'    => 'POST',
			'url'       => 'https://api.example.com/v1/mail/send',
			'status'    => 503,
			'duration'  => 1.204,
			'transport' => 'WP_Http_Curl',
			'error'     => 'Service Unavailable',
		),
		array(
			'ts'        => strtotime( '2026-11-24 13:44:19' ),
			'method'    => 'GET',
			'url'       => 'https://api.example.com/v1/health',
			'status'    => 0,
			'duration'  => 5.001,
			'transport' => 'WP_Http_Curl',
			'error'     => 'cURL error 28: Connection timed out after 5001 milliseconds',
		),
		array(
			'ts'        => strtotime( '2026-11-24 13:20:45' ),
			'method'    => 'GET',
			'url'       => 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins',
			'status'    => 200,
			'duration'  => 0.357,
			'transport' => 'WP_Http_Curl',
			'error'     => '',
		),
		array(
			'ts'        => strtotime( '2026-11-24 12:55:12' ),
			'method'    => 'DELETE',
			'url'       => 'https://api.example.com/v1/cache/purge',
			'status'    => 204,
			'duration'  => 0.118,
			'transport' => 'WP_Http_Curl',
			'error'     => '',
		),
		array(
			'ts'        => strtotime( '2026-11-24 12:31:40' ),
			'method'    => 'GET',
			'url'       => 'https://api.wordpress.org/events/1.0/',
			'status'    => 200,
			'duration'  => 0.298,
			'transport' => 'WP_Http_Curl',
			'error'     => '',
		),
	),
	HOUR_IN_SECONDS
);

$plugin->get_options( true );

$original_fatal = get_option( 'gdc_last_fatal', null );

update_option(
	'gdc_last_fatal',
	array(
		'ts'      => strtotime( '2026-11-24 14:02:10' ),
		'message' => 'Uncaught TypeError: Acme\\Forms\\Form::render(): Return value must be of type string, null returned',
		'file'    => '/var/www/example.com/public/wp-content/plugins/acme-forms/src/Form.php',
		'line'    => 231,
	),
	false
);

$fixture_public = '/var/www/example.com/public/wp-content/debug.log';

$replacements = array(
	$fixture_log                                          => $fixture_public,
	str_replace( '\\', '/', $fixture_log )                => $fixture_public,
	'D:\\\\_DEV\\\\Globus-debug-control\\\\wordpress'     => '/var/www/example.com/public',
	'D:\\_DEV\\Globus-debug-control\\wordpress'          => '/var/www/example.com/public',
	'D:/_DEV/Globus-debug-control/wordpress'              => '/var/www/example.com/public',
	'D:\\\\_DEV\\\\Globus-debug-control'                  => '/var/www/example.com/public',
	'D:/_DEV/Globus-debug-control'                        => '/var/www/example.com/public',
	home_url()                                            => 'https://example.com',
	site_url()                                            => 'https://example.com',
	str_replace( '://', ':\\/\\/', home_url() )           => 'https:\\/\\/example.com',
	'gdc_wp_test'                                         => 'example_db',
);

foreach ( $pages as $slug => $config ) {
	list( $page, $file ) = $config;

	$_GET['page'] = $page;

	ob_start();
	$plugin->render_settings_page();
	$html = (string) ob_get_clean();

	// The inline icon sprite is identical on every screen: keep it once.
	if ( preg_match( '#<svg xmlns="http://www.w3.org/2000/svg" style="display:none".*?</svg>#s', $html, $sprite_match ) ) {
		$sprite_file = __DIR__ . '/templates/sprite.html';

		if ( 'overview' === $slug ) {
			file_put_contents( $sprite_file, $sprite_match[0] . "\n" );
		}

		$html = str_replace( $sprite_match[0], '', $html );
	}

	$start = strpos( $html, '<div class="gdc-app">' );

	if ( false === $start ) {
		fwrite( STDERR, "Shell not found for {$slug}\n" );
		exit( 1 );
	}

	$html = substr( $html, $start );

	foreach ( $replacements as $from => $to ) {
		$html = str_replace( $from, $to, $html );
	}

	// Never ship real nonces in committed fixtures.
	$html = (string) preg_replace( '/"(nonce|statusNonce|nonceRun|nonceDelete|nonceBulk|nonceClear|nonceToggle)":"[^"]*"/', '"$1":"demo"', $html );
	$html = (string) preg_replace( '/name="(_wpnonce|_wp_http_referer)" value="[^"]*"/', 'name="$1" value="demo"', $html );

	// Static cron fixture: show the resolved source instead of a frozen spinner.
	if ( 'cron' === $slug ) {
		$html = str_replace(
			'<span class="spinner is-active"></span>',
			'<span class="gdc-source-badge gdc-source-core">WordPress</span>',
			$html
		);
	}

	// Static log console: colourise the demo lines for the screenshot only.
	if ( 'error-log' === $slug && preg_match( '#<textarea id="gdc-log-code" readonly>(.*?)</textarea>#s', $html, $match ) ) {
		$lines = explode( "\n", html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
		$mock  = '<div class="gdc-console-mock">';
		$no    = 0;

		foreach ( $lines as $line ) {
			++$no;
			$class = GDC_Log_Viewer::classify_line( $line );
			$mock .= '<div class="gdc-console-line ' . esc_attr( $class ) . '">';
			$mock .= '<span class="gdc-console-ln">' . $no . '</span>';
			$mock .= '<span class="gdc-console-tx">' . esc_html( $line ) . '</span>';
			$mock .= '</div>';
		}

		$mock .= '</div>';

		$html = (string) preg_replace(
			'#<div class="gdc-console" id="gdc-log-wrap">.*?</div>#s',
			'<div class="gdc-console" id="gdc-log-wrap">' . $mock . '</div>',
			$html,
			1
		);
	}

	$target = __DIR__ . '/templates/' . $file;
	file_put_contents( $target, rtrim( $html ) . "\n" );

	printf( "%-12s %6d bytes -> %s\n", $slug, strlen( $html ), $file );
}

unset( $_GET['page'] );

update_option( 'globus_debug_control_options', $original_options );
update_option( 'gdc_http_log_capturing', $original_capture );

if ( null === $original_fatal ) {
	delete_option( 'gdc_last_fatal' );
} else {
	update_option( 'gdc_last_fatal', $original_fatal, false );
}

if ( false === $original_http ) {
	delete_transient( 'gdc_http_log' );
} else {
	set_transient( 'gdc_http_log', $original_http, HOUR_IN_SECONDS );
}

$plugin->get_options( true );

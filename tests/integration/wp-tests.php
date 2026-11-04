<?php
/**
 * GLOBUS Debug Control - live WordPress integration tests.
 *
 * Run with:
 *   php wp-cli.phar eval-file tests/integration/wp-tests.php --path=wordpress --url=http://127.0.0.1:8088 --allow-root
 *
 * Requires: a working WordPress installation with MySQL, WP_DEBUG=true and the
 * globus-debug-control plugin active. Exits with code 1 on any failure.
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_action' ) ) {
	fwrite( STDERR, "Must run inside a bootstrapped WordPress (wp eval-file).\n" );
	exit( 1 );
}

$GLOBALS['gdc_itest'] = array(
	'passed' => 0,
	'failed' => 0,
);

// wp_send_json_*() terminates the process via wp_die(). Swap the handlers for
// no-ops so AJAX handlers can be exercised in-process.
add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $message = '', $title = '', $args = array() ) {};
	}
);
add_filter(
	'wp_die_ajax_handler',
	static function () {
		return static function ( $message = '', $title = '', $args = array() ) {};
	}
);
add_filter(
	'wp_die_json_handler',
	static function () {
		return static function ( $message = '', $title = '', $args = array() ) {};
	}
);

// Under wp-cli the redirect handler warns and returns; replace it so the
// redirect-based actions can be tested in-process without backtrace noise.
if ( has_filter( 'wp_redirect', 'WP_CLI\Utils\wp_redirect_handler' ) ) {
	remove_filter( 'wp_redirect', 'WP_CLI\Utils\wp_redirect_handler' );
}
add_filter(
	'wp_redirect',
	static function ( $location ) {
		return false;
	},
	10
);

/**
 * Records a single integration check result.
 */
function gdc_itest_check( string $name, bool $ok, string $detail = '' ): void {
	if ( $ok ) {
		$GLOBALS['gdc_itest']['passed']++;
		echo "[PASS] {$name}\n";
	} else {
		$GLOBALS['gdc_itest']['failed']++;
		echo "[FAIL] {$name}" . ( '' !== $detail ? " - {$detail}" : '' ) . "\n";
	}
}

$plugin_root = WP_PLUGIN_DIR . '/globus-debug-control';
$debug_log   = WP_CONTENT_DIR . '/debug.log';

// Act as the administrator for nonce/capability-based checks.
wp_set_current_user( 1 );

// ---------------------------------------------------------------------------
// A. Plugin bootstrap on a real WordPress
// ---------------------------------------------------------------------------

$gdc = Globus_Debug_Control::instance();
gdc_itest_check( 'plugin singleton instantiates', $gdc instanceof Globus_Debug_Control );

$header_content = (string) file_get_contents( $plugin_root . '/globus-debug-control.php' );
preg_match( '/Version:\s*([0-9.]+)/', $header_content, $header_version );
$class_version = (string) ( new ReflectionClass( Globus_Debug_Control::class ) )->getReflectionConstant( 'VERSION' )->getValue();
gdc_itest_check( 'plugin header version matches class constant', ( $header_version[1] ?? '' ) === $class_version, ( $header_version[1] ?? '' ) . ' vs ' . $class_version );

$opts = $gdc->get_options();
gdc_itest_check( 'default options applied', ! empty( $opts['enabled'] ) && 1 === $opts['suppress_notices'] );

// ---------------------------------------------------------------------------
// B. Real error-reporting / ini behaviour (WP_DEBUG is true on the site)
// ---------------------------------------------------------------------------

$er = error_reporting();
gdc_itest_check( 'E_NOTICE excluded from error_reporting (default suppress)', 0 === ( $er & E_NOTICE ) );
gdc_itest_check( 'E_DEPRECATED excluded from error_reporting', 0 === ( $er & E_DEPRECATED ) );
gdc_itest_check( 'E_WARNING still reported (not suppressed by default)', 0 !== ( $er & E_WARNING ) );

gdc_itest_check( 'display_errors disabled by default', '0' === (string) ini_get( 'display_errors' ), (string) ini_get( 'display_errors' ) );
gdc_itest_check( 'log_errors enabled by default', '1' === (string) ini_get( 'log_errors' ), (string) ini_get( 'log_errors' ) );

// Log path resolution.
$resolved = $gdc->resolve_log_path( array( 'log_path' => 'logs/itest-debug.log' ) );
gdc_itest_check( 'resolve_log_path prefixes wp-content', $resolved === str_replace( '\\', '/', WP_CONTENT_DIR . '/logs/itest-debug.log' ), (string) $resolved );

// Parent traversal is rejected (regression guard).
$traversal = $gdc->resolve_log_path( array( 'log_path' => '../wp-config.php' ) );
gdc_itest_check( 'log_path traversal rejected', '' === $traversal, (string) $traversal );

// Custom log path takes effect via ini.
update_option( 'globus_debug_control_options', array( 'log_path' => 'logs/itest-debug.log' ) );
$gdc->get_options( true );
$gdc->plugins_loaded();
$ini_log = (string) ini_get( 'error_log' );
gdc_itest_check( 'error_log ini points at custom path', false !== strpos( str_replace( '\\', '/', $ini_log ), 'logs/itest-debug.log' ), $ini_log );

// Real write to the custom log.
$custom_log = str_replace( '\\', '/', WP_CONTENT_DIR . '/logs/itest-debug.log' );
error_log( 'GDC_ITEST_MARKER_LOGGED' );
clearstatcache();
gdc_itest_check( 'custom log file created and written', is_file( $custom_log ) && false !== strpos( (string) file_get_contents( $custom_log ), 'GDC_ITEST_MARKER_LOGGED' ) );

// Suppressed error type is NOT written to the log.
update_option( 'globus_debug_control_options', array( 'log_path' => '' ) );
$gdc->get_options( true );
$gdc->plugins_loaded();
trigger_error( 'GDC_ITEST_MARKER_SUPPRESSED_NOTICE', E_USER_NOTICE );
clearstatcache();
$debug_contents = is_file( $debug_log ) ? (string) file_get_contents( $debug_log ) : '';
gdc_itest_check( 'suppressed E_USER_NOTICE not logged', false === strpos( $debug_contents, 'GDC_ITEST_MARKER_SUPPRESSED_NOTICE' ) );

// ---------------------------------------------------------------------------
// C. Options sanitize round-trip through the real settings flow
// ---------------------------------------------------------------------------

$sanitized = $gdc->sanitize_options(
	array(
		'enabled'             => '1',
		'suppress_notices'    => '1',
		'log_path'            => '../../evil.log',
		'log_max_size_mb'     => '500',
		'display_errors_mode' => 'off',
	)
);
gdc_itest_check( 'sanitize_options rejects traversal path', '' === $sanitized['log_path'], (string) $sanitized['log_path'] );
gdc_itest_check( 'sanitize_options clamps size to 100', 100 === $sanitized['log_max_size_mb'] );

// ---------------------------------------------------------------------------
// D. mu-plugin dropin on the real filesystem
// ---------------------------------------------------------------------------

$mu_path = str_replace( '\\', '/', WPMU_PLUGIN_DIR . '/gdc-early-loader.php' );
gdc_itest_check( 'mu dropin not installed before test', ! is_file( $mu_path ) );

gdc_itest_check( 'install_mu_dropin succeeds', true === $gdc->install_mu_dropin(), 'install_mu_dropin() returned false' );
gdc_itest_check( 'mu dropin file exists', is_file( $mu_path ) );
gdc_itest_check( 'is_mu_dropin_installed reports true', true === $gdc->is_mu_dropin_installed() );

gdc_itest_check( 'remove_mu_dropin succeeds', true === $gdc->remove_mu_dropin() );
gdc_itest_check( 'mu dropin file removed', ! is_file( $mu_path ) );

// ---------------------------------------------------------------------------
// E. wp-config.php inspection and safe write on the real file
// ---------------------------------------------------------------------------

$wpconfig = GDC_WpConfig_Debug::find_wp_config();
$config_ok = '' !== $wpconfig && is_file( $wpconfig );
gdc_itest_check( 'wp-config.php located', $config_ok, (string) $wpconfig );

$original_config = $config_ok ? (string) file_get_contents( $wpconfig ) : '';

$constants = GDC_WpConfig_Debug::read_constants( $wpconfig );
gdc_itest_check( 'WP_DEBUG parsed as true from wp-config', ! empty( $constants['WP_DEBUG']['defined'] ) && true === $constants['WP_DEBUG']['value'] );

// NOTE: The wp-config write path (handle_action + timestamped backup) and the
// log "Clear" action end with exit(), so they are exercised by dedicated
// subprocess steps (wp-wpcfg-save.php and wp-clear-log.php) that snapshot and
// restore wp-config.php - see the run script in the caller.

// ---------------------------------------------------------------------------
// F. Log viewer against the real debug.log
// ---------------------------------------------------------------------------

// Make sure the real debug.log has content to render.
if ( ! is_dir( dirname( $debug_log ) ) ) {
	mkdir( dirname( $debug_log ), 0777, true );
}
file_put_contents( $debug_log, "GDC_ITEST_VIEWER_LINE\n" );

$log_path = GDC_Log_Viewer::get_log_path();
gdc_itest_check( 'log viewer resolves default path', false !== strpos( str_replace( '\\', '/', $log_path ), 'debug.log' ), $log_path );

gdc_itest_check( 'classify_line detects fatal', 'gdc-log-fatal' === GDC_Log_Viewer::classify_line( 'PHP Fatal error: boom in /x.php:1' ) );
gdc_itest_check( 'classify_line detects warning', 'gdc-log-warning' === GDC_Log_Viewer::classify_line( 'PHP Warning: x in /y.php:2' ) );

ob_start();
GDC_Log_Viewer::render();
$log_html = ob_get_clean();
gdc_itest_check( 'log viewer renders toolbar', false !== strpos( $log_html, 'gdc-log-toolbar' ) );
gdc_itest_check( 'log viewer renders editor area', false !== strpos( $log_html, 'gdc-log-code' ) );

// The "Clear Log" action (handle_clear_action) ends with exit() and is
// verified by the wp-clear-log.php subprocess step.

// ---------------------------------------------------------------------------
// G. Cron viewer with a real scheduled event (incl. falsy args regression)
// ---------------------------------------------------------------------------

$itest_hook = 'gdc_itest_cron_hook';
$itest_ts   = time() + 300;
$itest_args = array( 0, 'keep' );

wp_clear_scheduled_hook( $itest_hook );
wp_schedule_event( $itest_ts, 'hourly', $itest_hook, $itest_args );
$events = GDC_Cron_Viewer::get_cron_events();

$found = false;
foreach ( $events as $event ) {
	if ( $itest_hook === $event['hook'] && (int) $event['timestamp'] === $itest_ts ) {
		$found = true;
		gdc_itest_check( 'falsy cron args preserved (0 kept)', array( 0, 'keep' ) === $event['args'], wp_json_encode( $event['args'] ) );
		break;
	}
}
gdc_itest_check( 'scheduled event listed by cron viewer', $found );

// Real delete via AJAX handler (nonce valid in-process).
$_POST['_ajax_nonce'] = wp_create_nonce( 'gdc_cron_delete' );
$_POST['hook']        = $itest_hook;
$_POST['timestamp']   = (string) $itest_ts;
$_POST['args']        = wp_json_encode( $itest_args );

try {
	GDC_Cron_Viewer::ajax_cron_delete();
	$deleted_ok = true;
} catch ( Exception $e ) {
	$deleted_ok = false;
}
gdc_itest_check( 'ajax_cron_delete succeeds with falsy args', $deleted_ok );
gdc_itest_check( 'event removed from cron array', false === wp_next_scheduled( $itest_hook, $itest_args ) );

// ---------------------------------------------------------------------------
// H. Transient viewer against the real database
// ---------------------------------------------------------------------------

$itest_transient = 'gdc_itest_transient_key';
delete_transient( $itest_transient );
set_transient( $itest_transient, 'integration-value', 3600 );

$listed = array();
foreach ( GDC_Transient_Viewer::get_transients() as $t ) {
	if ( $itest_transient === $t['key'] ) {
		$listed = $t;
		break;
	}
}
gdc_itest_check( 'transient listed by viewer', ! empty( $listed ) );
gdc_itest_check( 'transient value size reported', ! empty( $listed ) && $listed['value_size'] > 0 );

// Real delete via AJAX handler.
$_POST['_ajax_nonce'] = wp_create_nonce( 'gdc_transient_delete' );
$_POST['key']         = $itest_transient;
try {
	GDC_Transient_Viewer::ajax_delete();
	$transient_deleted = true;
} catch ( Exception $e ) {
	$transient_deleted = false;
}
gdc_itest_check( 'ajax delete removes transient', $transient_deleted && false === get_transient( $itest_transient ) );

// Expired purge: plant an expired transient directly in the DB.
$wpdb->insert(
	$wpdb->options,
	array(
		'option_name'  => '_transient_gdc_itest_expired',
		'option_value' => 'x',
		'autoload'     => 'no',
	)
);
$wpdb->insert(
	$wpdb->options,
	array(
		'option_name'  => '_transient_timeout_gdc_itest_expired',
		'option_value' => (string) ( time() - 60 ),
		'autoload'     => 'no',
	)
);
$_POST['_ajax_nonce'] = wp_create_nonce( 'gdc_transient_purge' );
try {
	GDC_Transient_Viewer::ajax_purge_expired();
	$purge_ran = true;
} catch ( Exception $e ) {
	$purge_ran = false;
}
$expired_row = $wpdb->get_var(
	$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", '_transient_gdc_itest_expired' )
);
gdc_itest_check( 'expired transient purged from DB', $purge_ran && null === $expired_row );

// ---------------------------------------------------------------------------
// I. Fatal-error monitor on a real install
// ---------------------------------------------------------------------------

$gdc->capture_fatal_error(
	array(
		'type'    => E_ERROR,
		'message' => 'GDC_ITEST_FAKE_FATAL',
		'file'    => __FILE__,
		'line'    => 1,
	)
);
$fatal = get_option( 'gdc_last_fatal', null );
gdc_itest_check( 'fatal error stored in option', is_array( $fatal ) && 'GDC_ITEST_FAKE_FATAL' === $fatal['message'] );

$gdc->capture_fatal_error(
	array(
		'type'    => E_NOTICE,
		'message' => 'GDC_ITEST_FAKE_NOTICE',
		'file'    => __FILE__,
		'line'    => 1,
	)
);
$fatal_after = get_option( 'gdc_last_fatal', array() );
gdc_itest_check( 'non-fatal type not stored', ( $fatal_after['message'] ?? '' ) === 'GDC_ITEST_FAKE_FATAL' );

ob_start();
GDC_Log_Viewer::render();
$fatal_html = ob_get_clean();
gdc_itest_check( 'log viewer renders fatal banner', false !== strpos( $fatal_html, 'gdc-last-fatal' ) );

// ---------------------------------------------------------------------------
// J. HTTP Log assertions (capturing was enabled and a request was fired by
//    separate eval steps before this run - see wp-http-enable/request.php)
// ---------------------------------------------------------------------------

gdc_itest_check( 'HTTP capturing enabled', GDC_Http_Log::is_capturing() );

$http_entries = GDC_Http_Log::get_log();
$http_entry   = $http_entries[0] ?? array();
gdc_itest_check( 'HTTP request captured', ! empty( $http_entry ) );
gdc_itest_check( 'sensitive query param masked in URL', false === strpos( (string) ( $http_entry['url'] ?? '' ), 'supersecret123' ) && false !== strpos( (string) ( $http_entry['url'] ?? '' ), 'api_key=***' ), (string) ( $http_entry['url'] ?? '' ) );
gdc_itest_check( 'transport class recorded', ! empty( $http_entry['transport'] ), (string) ( $http_entry['transport'] ?? '' ) );
gdc_itest_check( 'duration recorded', isset( $http_entry['duration'] ) && $http_entry['duration'] >= 0 );

// ---------------------------------------------------------------------------
// K. Capability filter + body class on a real install
// ---------------------------------------------------------------------------

gdc_itest_check( 'default manage capability', 'manage_options' === Globus_Debug_Control::manage_capability() );

add_filter(
	'gdc_manage_capability',
	static function () {
		return 'edit_posts';
	}
);
gdc_itest_check( 'capability filter applied', 'edit_posts' === Globus_Debug_Control::manage_capability() );
remove_all_filters( 'gdc_manage_capability' );

gdc_itest_check(
	'php-error body class removed',
	array( 'home', 'page' ) === $gdc->filter_body_class( array( 'home', 'php-error', 'page' ) )
);

// ---------------------------------------------------------------------------
// L. Dashboard widget on a real install
// ---------------------------------------------------------------------------

$gdc->add_dashboard_widgets();
ob_start();
$gdc->render_dashboard_widget();
$widget_html = ob_get_clean();
gdc_itest_check( 'dashboard widget renders', false !== strpos( $widget_html, 'gdc-dashboard' ) );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$stats = $GLOBALS['gdc_itest'];
echo "\n--------------------------------------------\n";
echo 'INTEGRATION RESULTS: ' . $stats['passed'] . ' passed, ' . $stats['failed'] . ' failed' . "\n";

if ( $stats['failed'] > 0 ) {
	exit( 1 );
}

echo "ALL INTEGRATION TESTS PASSED\n";
exit( 0 );

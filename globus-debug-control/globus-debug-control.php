<?php
/**
 * Plugin Name: GLOBUS Debug Control
 * Plugin URI: https://globus.studio
 * Description: Full WordPress debug mode control: error type filtering, display and logging management, php-error body class removal.
 * Version: 2.2.7
 * Author: Yevhen Leonidov
 * Author URI: https://leonidov.dev
 * Requires at least: 5.2
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: globus-debug-control
 * Domain Path: /languages
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GDC_FILE', __FILE__ );
define( 'GDC_DIR', __DIR__ );
define( 'GDC_INCLUDES', __DIR__ . '/include' );
define( 'GDC_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets/' );

require_once GDC_INCLUDES . '/log-viewer.php';
require_once GDC_INCLUDES . '/cron-viewer.php';
require_once GDC_INCLUDES . '/wp-config-debug.php';
require_once GDC_INCLUDES . '/transient-viewer.php';
require_once GDC_INCLUDES . '/options-viewer.php';
require_once GDC_INCLUDES . '/http-log.php';
require_once GDC_INCLUDES . '/constants-viewer.php';
require_once GDC_INCLUDES . '/trait-error-control.php';
require_once GDC_INCLUDES . '/trait-mu-dropin.php';
require_once GDC_INCLUDES . '/trait-admin-settings.php';
require_once GDC_INCLUDES . '/trait-admin-page.php';

/**
 * Main plugin class.
 *
 * Bootstraps all traits, registers WordPress hooks, and manages the singleton lifecycle.
 *
 * @package GlobusDebugControl
 */
final class Globus_Debug_Control {

	use GDC_Error_Control;
	use GDC_Mu_Dropin;
	use GDC_Admin_Settings;
	use GDC_Admin_Page;

	private const OPT     = 'globus_debug_control_options';
	private const VERSION = '2.2.7';
	private const MU_FILE = 'gdc-early-loader.php';

	/**
	 * Singleton class instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevents cloning of the singleton instance.
	 */
	private function __clone() {}
	/**
	 * Prevents unserializing of the singleton instance.
	 */
	public function __wakeup() {}

	/**
	 * Bootstraps the plugin.
	 *
	 * Runs early error-control initialisation and registers all WordPress hooks.
	 */
	private function __construct() {
		$this->early_init();

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 1 );
		add_action( 'wp_loaded', array( $this, 'on_wp_loaded' ), PHP_INT_MAX );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'after_setup_theme', array( $this, 'early_body_class_filter' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_php_error_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_settings_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_php_error_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_module_actions' ) );

		GDC_Log_Viewer::register_ajax_hooks();
		GDC_Cron_Viewer::register_ajax_hooks();
		GDC_Transient_Viewer::register_ajax_hooks();
		GDC_Http_Log::register_ajax_hooks();

		if ( GDC_Http_Log::is_capturing() ) {
			add_action( 'http_api_debug', array( 'GDC_Http_Log', 'on_request' ), 10, 5 );
			add_filter( 'pre_http_request', array( 'GDC_Http_Log', 'record_start_time' ), 10, 3 );
		}

		add_filter( 'body_class', array( $this, 'filter_body_class' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ), PHP_INT_MAX );
		// Note: enable_wp_debug_mode_checks is intentionally NOT registered here -
		// wp_debug_mode() runs in wp-settings.php before any plugin or mu-plugin
		// loads, so a regular-plugin registration can never take effect.
		// The early-loader mu-plugin keeps its own registration for robustness.

		register_deactivation_hook( GDC_FILE, array( $this, 'on_deactivation' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( GDC_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Returns the capability required to manage the plugin, filterable.
	 *
	 * @param string $context Optional context label for the filter.
	 * @return string
	 */
	public static function manage_capability( string $context = '' ): string {
		return (string) apply_filters( 'gdc_manage_capability', 'manage_options', $context );
	}

	/**
	 * Delegates admin action handling to submodule classes.
	 */
	public function handle_module_actions(): void {
		GDC_Log_Viewer::handle_clear_action();
		GDC_Log_Viewer::handle_download_action();
		GDC_WpConfig_Debug::handle_action();
	}

	/**
	 * Prepends a Settings link to the plugin row in the Plugins list screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[] Modified action links with Settings prepended.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=globus-debug-control' ) ) . '">' . esc_html__( 'Settings', 'globus-debug-control' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

Globus_Debug_Control::instance();

<?php
/**
 * Plugin Name: GLOBUS Debug Control
 * Plugin URI: https://globus.studio
 * Description: Full WordPress debug mode control: error type filtering, display and logging management, php-error body class removal.
 * Version: 2.2.5
 * Author: Yevhen Leonidov
 * Author URI: https://leonidov.dev
 * Requires at least: 5.2
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: globus-debug-control
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GDC_FILE',       __FILE__ );
define( 'GDC_DIR',        __DIR__ );
define( 'GDC_INCLUDES',   __DIR__ . '/include' );
define( 'GDC_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets/' );

require_once GDC_INCLUDES . '/log-viewer.php';
require_once GDC_INCLUDES . '/cron-viewer.php';
require_once GDC_INCLUDES . '/wp-config-debug.php';
require_once GDC_INCLUDES . '/trait-error-control.php';
require_once GDC_INCLUDES . '/trait-mu-dropin.php';
require_once GDC_INCLUDES . '/trait-admin-settings.php';
require_once GDC_INCLUDES . '/trait-admin-page.php';

final class Globus_Debug_Control {

	use GDC_Error_Control;
	use GDC_Mu_Dropin;
	use GDC_Admin_Settings;
	use GDC_Admin_Page;

	private const OPT     = 'globus_debug_control_options';
	private const VERSION = '2.2.5';
	private const MU_FILE = 'gdc-early-loader.php';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone() {}
	public function __wakeup() {}

	private function __construct() {
		$this->early_init();

		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 1 );
		add_action( 'wp_loaded', [ $this, 'on_wp_loaded' ], PHP_INT_MAX );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'after_setup_theme', [ $this, 'early_body_class_filter' ], 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_php_error_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_settings_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue_php_error_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_module_actions' ] );

		add_filter( 'body_class', [ $this, 'filter_body_class' ], PHP_INT_MAX );
		add_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ], PHP_INT_MAX );
		// Effective only as mu-plugin; as regular plugin wp_debug_mode() has already executed.
		add_filter( 'enable_wp_debug_mode_checks', '__return_false', 999 );

		register_deactivation_hook( GDC_FILE, [ $this, 'on_deactivation' ] );

		add_filter( 'plugin_action_links_' . plugin_basename( GDC_FILE ), [ $this, 'plugin_action_links' ] );
	}

	public function handle_module_actions(): void {
		GDC_Log_Viewer::handle_clear_action();
		GDC_Log_Viewer::handle_download_action();
		GDC_WpConfig_Debug::handle_action();
	}

	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=globus-debug-control' ) ) . '">' . esc_html__( 'Settings', 'globus-debug-control' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

Globus_Debug_Control::instance();

<?php
/**
 * GLOBUS Debug Control - Admin Page Trait
 *
 * Registers the top-level admin page, renders the shared shell with the
 * section navigation, enqueues assets, and manages the php-error body class.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait GDC_Admin_Page {

	/**
	 * Hook suffixes returned when the admin pages are registered.
	 *
	 * WordPress derives these from the parent slug and can vary between
	 * versions, so they are stored instead of being rebuilt by hand.
	 *
	 * @var string[]
	 */
	private static $page_hooks = array();

	// -------------------------------------------------------------------------
	// Menu & page rendering
	// -------------------------------------------------------------------------

	/**
	 * Registers the single top-level menu item.
	 *
	 * Module switching happens inside the plugin shell, so the WordPress menu
	 * does not grow with every viewer.
	 */
	public function add_settings_page() {
		self::$page_hooks   = array();
		self::$page_hooks[] = add_menu_page(
			'GLOBUS Debug Control',
			__( 'GLOBUS Debug', 'globus-debug-control' ),
			self::manage_capability(),
			'globus-debug-control',
			array( $this, 'render_settings_page' ),
			'none',
			81
		);
	}

	/**
	 * Removes the empty icon column from the plugin menu item.
	 *
	 * The plugin uses a text-only menu entry, so hiding the reserved image
	 * column keeps the label aligned with the rest of the admin menu.
	 */
	public function admin_enqueue_menu_styles(): void {
		$css = '#adminmenu .toplevel_page_globus-debug-control .wp-menu-image{display:none;}'
			. '#adminmenu .toplevel_page_globus-debug-control .wp-menu-name{padding-left:8px;}'
			. '.folded #adminmenu .toplevel_page_globus-debug-control .wp-menu-image{display:flex;align-items:center;justify-content:center;padding:0;font-size:14px;font-weight:700;line-height:1;}'
			. '.folded #adminmenu .toplevel_page_globus-debug-control .wp-menu-image::before{content:"G";}'
			. '.folded #adminmenu .toplevel_page_globus-debug-control .wp-menu-image br{display:none;}';

		wp_add_inline_style( 'common', $css );
	}

	/**
	 * Redirects legacy URLs to the shared module page.
	 *
	 * Old bookmarks used `?page=gdc-log` and the old Settings screen lived
	 * under `options-general.php`. Both now resolve to the single plugin page.
	 */
	public function maybe_redirect_legacy_url(): void {
		global $pagenow;

		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a legacy URL.
		if ( ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a legacy URL.
		$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) );
		$args = array();

		if ( is_user_logged_in() && ! current_user_can( self::manage_capability() ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a legacy URL.
		if ( isset( $_GET['logpage'] ) ) {
			$args['logpage'] = (int) $_GET['logpage']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a legacy URL.
		}

		$legacy_settings = ( 'options-general.php' === $pagenow && 'globus-debug-control' === $page );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a legacy URL.
		$legacy_module = ( 'admin.php' === $pagenow && 'globus-debug-control' !== $page )
			? GDC_UI::module_from_slug( $page )
			: '';

		if ( ! $legacy_settings && '' === $legacy_module ) {
			return;
		}

		$module = 'overview';

		if ( $legacy_settings ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a legacy URL.
			$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

			if ( '' !== $tab && array_key_exists( $tab, GDC_UI::modules() ) ) {
				$module = $tab;
			}
		} else {
			$module = $legacy_module;
		}

		wp_safe_redirect( GDC_UI::module_url( $module, $args ) );
		exit;
	}

	/**
	 * Registers the "Recent Errors" dashboard widget.
	 */
	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'gdc_recent_errors',
			__( 'GLOBUS: Recent Errors', 'globus-debug-control' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget: the last recorded fatal error, if any.
	 */
	public function render_dashboard_widget() {
		$last_fatal = get_option( 'gdc_last_fatal', null );
		$opts       = $this->get_options();
		$log_path   = GDC_Log_Viewer::get_log_path();
		$log_exists = '' !== $log_path && file_exists( $log_path );
		$log_size   = $log_exists ? (int) @filesize( $log_path ) : 0;
		?>
		<div class="gdc-dashboard gdc-widget">
			<?php if ( is_array( $last_fatal ) && ! empty( $last_fatal['message'] ) ) : ?>
				<div class="gdc-alert gdc-alert--danger gdc-last-fatal">
					<p class="gdc-fw-semibold"><?php esc_html_e( 'Last fatal error', 'globus-debug-control' ); ?></p>
					<p class="gdc-mono gdc-fs-2xs"><?php echo esc_html( (string) $last_fatal['message'] ); ?></p>
					<?php if ( ! empty( $last_fatal['file'] ) ) : ?>
						<p class="gdc-mono gdc-fs-2xs gdc-dim"><?php echo esc_html( (string) $last_fatal['file'] . ':' . (int) ( $last_fatal['line'] ?? 0 ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="gdc-alert gdc-alert--success">
					<p class="gdc-fw-semibold"><?php esc_html_e( 'No fatal errors recorded.', 'globus-debug-control' ); ?></p>
					<p class="gdc-fs-xs"><?php esc_html_e( 'The error log is clean.', 'globus-debug-control' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="gdc-row gdc-g-2h gdc-row--wrap gdc-mt-2">
				<?php
				echo GDC_UI::status_chip( GDC_WpConfig_Debug::is_wp_debug_on(), 'WP_DEBUG ON', 'WP_DEBUG OFF' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
				echo GDC_UI::status_chip( ! empty( $opts['log_enabled'] ), __( 'Logging on', 'globus-debug-control' ), __( 'Logging off', 'globus-debug-control' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.

				if ( $log_exists ) {
					echo GDC_UI::chip( size_format( (int) $log_size ), 'blue' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output.
				}
				?>
			</div>

			<div class="gdc-mt-2">
				<a class="gdc-btn gdc-btn--sm gdc-btn--surface" href="<?php echo esc_url( GDC_UI::module_url( 'log' ) ); ?>">
					<?php echo GDC_UI::icon( 'code', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
					<?php esc_html_e( 'Open Error Log', 'globus-debug-control' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the shared shell and the active module.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::manage_capability() ) ) {
			return;
		}

		$modules    = GDC_UI::modules();
		$active_tab = GDC_UI::current_module();

		$opts = $this->get_options();

		GDC_UI::sprite();

		$gdc_version      = self::VERSION;
		$gdc_wp_debug_on  = GDC_WpConfig_Debug::is_wp_debug_on();
		$gdc_nav          = GDC_UI::nav( $active_tab );
		$gdc_active_label = $modules[ $active_tab ]['label'];
		?>
		<div class="gdc-app">
			<?php include GDC_INCLUDES . '/ui/partials/header.php'; ?>

			<div class="gdc-content">
				<div class="gdc-wrap">
					<?php
					if ( 'overview' === $active_tab ) {
						$this->render_overview_module();
					} elseif ( 'settings' === $active_tab ) {
						$this->render_settings_module( $opts );
					} elseif ( 'wpcfg' === $active_tab ) {
						$this->render_wp_debug_module();
					} else {
						$this->render_module( $active_tab, $modules[ $active_tab ] );
					}
					?>
				</div>
			</div>

			<?php include GDC_INCLUDES . '/ui/partials/palette.php'; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Settings module with the design system.
	 *
	 * @param array<string, mixed> $opts Current plugin options.
	 */
	private function render_settings_module( array $opts ): void {
		$gdc_opts         = $opts;
		$gdc_opt          = self::OPT;
		$gdc_version      = self::VERSION;
		$gdc_log_path     = $this->resolve_log_path( $opts );
		$gdc_log_exists   = '' !== $gdc_log_path && file_exists( $gdc_log_path );
		$gdc_log_size     = $gdc_log_exists ? (int) @filesize( $gdc_log_path ) : 0;
		$gdc_mu_installed = $this->is_mu_dropin_installed();
		$gdc_is_api       = $this->is_api_context();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after a verified save.
		$gdc_settings_saved = isset( $_GET['settings-updated'] );

		include GDC_INCLUDES . '/ui/partials/settings-tab.php';
	}

	/**
	 * Renders the Overview module: health, KPIs, charts and environment.
	 */
	private function render_overview_module(): void {
		$gdc         = GDC_Overview::get_data();
		$gdc_version = self::VERSION;

		include GDC_INCLUDES . '/ui/partials/overview-tab.php';
	}

	/**
	 * Renders the WP_DEBUG module on the design system.
	 */
	private function render_wp_debug_module(): void {
		?>
		<div class="gdc-page" data-page="wpcfg">
			<div class="gdc-pagehead">
				<div>
					<h1 class="gdc-pagehead-title"><?php esc_html_e( 'WP_DEBUG', 'globus-debug-control' ); ?></h1>
					<div class="gdc-pagehead-sub"><?php esc_html_e( 'Toggle wp-config.php constants without editing files or using FTP.', 'globus-debug-control' ); ?></div>
				</div>
				<div class="gdc-pagehead-actions">
					<a class="gdc-btn gdc-btn--surface" href="<?php echo esc_url( GDC_UI::module_url( 'constants' ) ); ?>">
						<?php echo GDC_UI::icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<?php esc_html_e( 'All constants', 'globus-debug-control' ); ?>
					</a>
				</div>
			</div>

			<div class="gdc-settings-layout">
				<?php
				$gdc_nav          = GDC_UI::nav( 'wpcfg' );
				$gdc_active_label = __( 'WP_DEBUG', 'globus-debug-control' );
				include GDC_INCLUDES . '/ui/partials/snav.php';
				?>

				<div class="gdc-settings-content">
					<?php GDC_WpConfig_Debug::render(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a module on the design system.
	 *
	 * @param string               $tab    Module id.
	 * @param array<string, mixed> $module Module metadata.
	 */
	private function render_module( string $tab, array $module ): void {
		?>
		<div class="gdc-pagehead">
			<div>
				<h1 class="gdc-pagehead-title"><?php echo esc_html( (string) $module['label'] ); ?></h1>
				<?php if ( ! empty( $module['desc'] ) ) : ?>
					<div class="gdc-pagehead-sub"><?php echo esc_html( (string) $module['desc'] ); ?></div>
				<?php endif; ?>
			</div>
			<div class="gdc-pagehead-actions">
				<a class="gdc-btn gdc-btn--surface" href="<?php echo esc_url( GDC_UI::module_url( 'settings' ) ); ?>">
					<?php echo GDC_UI::icon( 'gear' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
					<?php esc_html_e( 'Settings', 'globus-debug-control' ); ?>
				</a>
			</div>
		</div>

		<div class="gdc-settings-layout">
			<?php
			$gdc_nav          = GDC_UI::nav( $tab );
			$gdc_active_label = (string) $module['label'];
			include GDC_INCLUDES . '/ui/partials/snav.php';
			?>

			<div class="gdc-settings-content">
				<?php
				switch ( $tab ) {
					case 'cron':
						GDC_Cron_Viewer::render();
						break;
					case 'transients':
						GDC_Transient_Viewer::render();
						break;
					case 'autoloaded':
						GDC_Options_Viewer::render();
						break;
					case 'http':
						GDC_Http_Log::render();
						break;
					case 'constants':
						GDC_Constants_Viewer::render();
						break;
					case 'log':
					default:
						GDC_Log_Viewer::render();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Body class filters
	// -------------------------------------------------------------------------

	/**
	 * Re-registers body class filters at higher priority after theme setup.
	 */
	public function early_body_class_filter() {
		remove_filter( 'body_class', array( $this, 'filter_body_class' ), PHP_INT_MAX );
		remove_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ), PHP_INT_MAX );
		add_filter( 'body_class', array( $this, 'filter_body_class' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ), PHP_INT_MAX );
	}

	/**
	 * Removes the php-error class from the front-end body classes array.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[] Filtered body classes.
	 */
	public function filter_body_class( array $classes ): array {
		$opts = $this->get_options();
		if ( ! empty( $opts['remove_php_error_bodyclass'] ) && ! empty( $opts['enabled'] ) ) {
			$classes = array_values(
				array_filter(
					$classes,
					function ( $css_class ) {
						return 'php-error' !== $css_class;
					}
				)
			);
		}
		return $classes;
	}

	/**
	 * Removes the php-error class from the admin body class string.
	 *
	 * @param string $classes Space-separated admin body classes.
	 * @return string Filtered body class string.
	 */
	public function filter_admin_body_class( string $classes ): string {
		$opts = $this->get_options();
		if ( ! empty( $opts['remove_php_error_bodyclass'] ) && ! empty( $opts['enabled'] ) ) {
			$list = array_filter(
				preg_split( '/\s+/', trim( $classes ), -1, PREG_SPLIT_NO_EMPTY ),
				function ( $css_class ) {
					return 'php-error' !== trim( $css_class );
				}
			);
			return implode( ' ', $list );
		}
		return $classes;
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing
	// -------------------------------------------------------------------------

	/**
	 * Returns a cache-busting version for a plugin asset.
	 *
	 * @param string $relative Path relative to the plugin directory.
	 * @return string
	 */
	private function asset_version( string $relative ): string {
		$file = GDC_DIR . '/' . ltrim( $relative, '/' );

		return file_exists( $file ) ? (string) filemtime( $file ) : self::VERSION;
	}

	/**
	 * Enqueues CSS and JS for the plugin admin screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function admin_enqueue_settings_assets( string $hook ): void {
		$hooks = self::$page_hooks;

		if ( empty( $hooks ) ) {
			$hooks = array( 'toplevel_page_globus-debug-control' );
		}

		if ( ! in_array( $hook, $hooks, true ) ) {
			return;
		}

		$css = GDC_ASSETS_URL . 'css/';
		$js  = GDC_ASSETS_URL . 'js/';

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style( 'gdc-tokens', $css . 'gdc-tokens.css', array(), $this->asset_version( 'assets/css/gdc-tokens.css' ) );
		wp_enqueue_style( 'gdc-ui', $css . 'gdc-ui.css', array( 'gdc-tokens' ), $this->asset_version( 'assets/css/gdc-ui.css' ) );
		wp_enqueue_style( 'gdc-components', $css . 'gdc-components.css', array( 'gdc-ui' ), $this->asset_version( 'assets/css/gdc-components.css' ) );
		wp_enqueue_style( 'gdc-toastify', GDC_ASSETS_URL . 'vendor/toastify/toastify.min.css', array(), $this->asset_version( 'assets/vendor/toastify/toastify.min.css' ) );

		wp_enqueue_script( 'gdc-toastify', GDC_ASSETS_URL . 'vendor/toastify/toastify.min.js', array(), $this->asset_version( 'assets/vendor/toastify/toastify.min.js' ), true );

		$tab = GDC_UI::current_module();

		if ( 'overview' === $tab ) {
			wp_enqueue_script( 'gdc-chartjs', GDC_ASSETS_URL . 'vendor/chartjs/chart.umd.js', array(), $this->asset_version( 'assets/vendor/chartjs/chart.umd.js' ), true );
		}

		wp_enqueue_script( 'gdc-ui', $js . 'gdc-ui.js', array( 'gdc-toastify' ), $this->asset_version( 'assets/js/gdc-ui.js' ), true );
		wp_enqueue_script( 'gdc-admin', $js . 'admin.js', array( 'gdc-ui' ), $this->asset_version( 'assets/js/admin.js' ), true );

		wp_localize_script(
			'gdc-ui',
			'gdcUiData',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'gdc_admin' ),
				'searchIndex' => GDC_UI::search_index(),
				'i18n'        => array(
					'no_results'    => esc_html__( 'Nothing found', 'globus-debug-control' ),
					'confirm_title' => esc_html__( 'Please confirm', 'globus-debug-control' ),
					'cancel'        => esc_html__( 'Cancel', 'globus-debug-control' ),
					'confirm'       => esc_html__( 'Confirm', 'globus-debug-control' ),
					'close'         => esc_html__( 'Close', 'globus-debug-control' ),
					'copied'        => esc_html__( 'Copied to clipboard.', 'globus-debug-control' ),
					'saved'         => esc_html__( 'Saved.', 'globus-debug-control' ),
					'active'        => esc_html__( 'Debug control is active', 'globus-debug-control' ),
					'paused'        => esc_html__( 'Debug control is paused', 'globus-debug-control' ),
					'error'         => esc_html__( 'Request failed. Please try again.', 'globus-debug-control' ),
				),
			)
		);

		wp_localize_script(
			'gdc-admin',
			'gdcAdmin',
			array(
				'i18n' => array(
					'enabled'  => esc_html__( 'Enabled', 'globus-debug-control' ),
					'disabled' => esc_html__( 'Disabled', 'globus-debug-control' ),
				),
			)
		);

		if ( 'log' === $tab ) {
			wp_enqueue_code_editor( array( 'type' => 'text/plain' ) );
		}
	}

	/**
	 * Enqueues the design tokens on the WordPress dashboard for the widget.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function admin_enqueue_dashboard_assets( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		$css = GDC_ASSETS_URL . 'css/';

		wp_enqueue_style( 'gdc-tokens', $css . 'gdc-tokens.css', array(), $this->asset_version( 'assets/css/gdc-tokens.css' ) );
		wp_enqueue_style( 'gdc-ui', $css . 'gdc-ui.css', array( 'gdc-tokens' ), $this->asset_version( 'assets/css/gdc-ui.css' ) );
		wp_enqueue_style( 'gdc-components', $css . 'gdc-components.css', array( 'gdc-ui' ), $this->asset_version( 'assets/css/gdc-components.css' ) );
	}

	/**
	 * Enqueues inline assets required to neutralise the php-error body class in wp-admin.
	 */
	public function admin_enqueue_php_error_assets() {
		$opts = $this->get_options();
		if ( empty( $opts['remove_php_error_bodyclass'] ) || empty( $opts['enabled'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline style, no version needed.
		wp_add_inline_style( 'common', 'body.php-error{}' );
		wp_add_inline_script( 'common', $this->get_php_error_class_js() );
	}

	/**
	 * Enqueues inline assets required to neutralise the php-error body class on the front end.
	 */
	public function frontend_enqueue_php_error_assets() {
		$opts = $this->get_options();
		if ( empty( $opts['remove_php_error_bodyclass'] ) || empty( $opts['enabled'] ) ) {
			return;
		}
		wp_register_style( 'gdc-php-error', false, array(), false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters -- Inline-only handle.
		wp_enqueue_style( 'gdc-php-error' );
		wp_add_inline_style( 'gdc-php-error', 'body.php-error{}' );
		wp_register_script( 'gdc-php-error', false, array(), false, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters -- Inline-only handle.
		wp_enqueue_script( 'gdc-php-error' );
		wp_add_inline_script( 'gdc-php-error', $this->get_php_error_class_js() );
	}

	/**
	 * Returns the JavaScript snippet that removes the php-error class from the body element.
	 *
	 * @return string Minified inline JavaScript.
	 */
	private function get_php_error_class_js(): string {
		return '(function(){function r(){var b=document.body||document.getElementsByTagName("body")[0];if(b&&b.classList.contains("php-error")){b.classList.remove("php-error");}}r();if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",r);}window.addEventListener("load",r);if(window.MutationObserver){var o=new MutationObserver(function(m){m.forEach(function(e){if(e.type==="attributes"&&e.attributeName==="class"){r();}});});var b=document.body||document.getElementsByTagName("body")[0];if(b){o.observe(b,{attributes:true,attributeFilter:["class"]});}}var c=0,i=setInterval(function(){r();c++;if(c>=30){clearInterval(i);}},100);})();';
	}
}

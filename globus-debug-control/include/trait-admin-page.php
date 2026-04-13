<?php
/**
 * GLOBUS Debug Control - Admin Page Trait
 *
 * Registers the admin menu page, renders tab-based settings UI,
 * handles asset enqueueing, and manages the php-error body class.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait GDC_Admin_Page {

	// -------------------------------------------------------------------------
	// Menu & page rendering
	// -------------------------------------------------------------------------

	/**
	 * Registers the plugin settings page under the WordPress Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			'GLOBUS Debug Control',
			'GLOBUS Debug Control',
			'manage_options',
			'globus-debug-control',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the full settings page with tabbed navigation.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'settings'   => __( 'Settings', 'globus-debug-control' ),
			'wpcfg'      => __( 'WP_DEBUG', 'globus-debug-control' ),
			'log'        => __( 'Error Log', 'globus-debug-control' ),
			'cron'       => __( 'Cron Tasks', 'globus-debug-control' ),
			'transients' => __( 'Transients', 'globus-debug-control' ),
			'autoloaded' => __( 'Autoloaded', 'globus-debug-control' ),
			'http'       => __( 'HTTP Log', 'globus-debug-control' ),
			'constants'  => __( 'Constants', 'globus-debug-control' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$active_tab = ( '' !== $raw_tab && array_key_exists( $raw_tab, $tabs ) ) ? $raw_tab : 'settings';

		$opts          = $this->get_options();
		$wp_debug_file = GDC_WpConfig_Debug::get_wp_debug_status();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( null !== $wp_debug_file ) : ?>
				<?php if ( $wp_debug_file ) : ?>
					<span class="gdc-debug-badge gdc-debug-on">
						<span class="dashicons dashicons-warning" style="font-size:14px;vertical-align:-2px;margin-right:2px;"></span>
						WP_DEBUG <?php esc_html_e( 'ON', 'globus-debug-control' ); ?>
					</span>
				<?php else : ?>
					<span class="gdc-debug-badge gdc-debug-off">
						WP_DEBUG <?php esc_html_e( 'OFF', 'globus-debug-control' ); ?>
					</span>
				<?php endif; ?>
			<?php endif; ?>

			<hr class="wp-header-end">

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a
						href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page' => 'globus-debug-control',
									'tab'  => $tab_key,
								),
								admin_url( 'options-general.php' )
							)
						);
						?>
								"
						class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
					><?php echo esc_html( $tab_label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="gdc-tab-content">

			<?php if ( 'settings' === $active_tab ) : ?>

				<form action="options.php" method="post">
					<?php
					settings_fields( 'globus_debug_control' );
					do_settings_sections( 'globus_debug_control' );
					submit_button( __( 'Save Settings', 'globus-debug-control' ) );
					?>
				</form>

				<hr>

				<h2><?php esc_html_e( 'System Information', 'globus-debug-control' ); ?></h2>
				<table class="widefat striped">
					<tr>
						<td style="width: 250px;"><strong><?php esc_html_e( 'Plugin version:', 'globus-debug-control' ); ?></strong></td>
						<td><?php echo esc_html( self::VERSION ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'PHP version:', 'globus-debug-control' ); ?></strong></td>
						<td><?php echo esc_html( PHP_VERSION ); ?></td>
					</tr>
					<tr>
						<td><strong>WP_DEBUG:</strong></td>
						<td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span style="color: #d63638;">&#9679;</span> ' . esc_html__( 'Enabled', 'globus-debug-control' ) : '<span style="color: #999;">&#9675;</span> ' . esc_html__( 'Disabled', 'globus-debug-control' ); ?></td>
					</tr>
					<tr>
						<td><strong>WP_DEBUG_DISPLAY:</strong></td>
						<td><?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? '<span style="color: #d63638;">&#9679;</span> ' . esc_html__( 'Enabled', 'globus-debug-control' ) : '<span style="color: #999;">&#9675;</span> ' . esc_html__( 'Disabled', 'globus-debug-control' ); ?></td>
					</tr>
					<tr>
						<td><strong>WP_DEBUG_LOG:</strong></td>
						<td><?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '<span style="color: #d63638;">&#9679;</span> ' . esc_html__( 'Enabled', 'globus-debug-control' ) : '<span style="color: #999;">&#9675;</span> ' . esc_html__( 'Disabled', 'globus-debug-control' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Current error_reporting:', 'globus-debug-control' ); ?></strong></td>
						<?php // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- Admin diagnostic display. ?>
						<td><code><?php echo esc_html( error_reporting() ); ?></code></td>
					</tr>
					<tr>
						<td><strong>display_errors:</strong></td>
						<td><code><?php echo esc_html( ini_get( 'display_errors' ) ); ?></code></td>
					</tr>
					<tr>
						<td><strong>log_errors:</strong></td>
						<td><code><?php echo esc_html( ini_get( 'log_errors' ) ); ?></code></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Log file path:', 'globus-debug-control' ); ?></strong></td>
						<td>
							<?php
							$log_path = $this->resolve_log_path( $opts );
							if ( $log_path ) {
								echo '<code>' . esc_html( $log_path ) . '</code>';
								if ( file_exists( $log_path ) ) {
									$size = filesize( $log_path );
									echo ' <span style="color: #2271b1;">(' . esc_html__( 'size:', 'globus-debug-control' ) . ' ' . esc_html( size_format( $size ) ) . ')</span>';
								} else {
									echo ' <span style="color: #999;">(' . esc_html__( 'file does not exist', 'globus-debug-control' ) . ')</span>';
								}
							} else {
								echo '<span style="color: #999;">' . esc_html__( 'Not configured', 'globus-debug-control' ) . '</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Execution context:', 'globus-debug-control' ); ?></strong></td>
						<td><?php echo $this->is_api_context() ? '<span style="color: #d63638;">' . esc_html__( 'API context', 'globus-debug-control' ) . '</span>' : '<span style="color: #2271b1;">' . esc_html__( 'Normal', 'globus-debug-control' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'MU-plugin dropin:', 'globus-debug-control' ); ?></strong></td>
						<td><?php echo $this->is_mu_dropin_installed() ? '<span style="color: #00a32a;">&#9679;</span> ' . esc_html__( 'Installed', 'globus-debug-control' ) . ' (<code>' . esc_html( self::MU_FILE ) . '</code>)' : '<span style="color: #d63638;">&#9679;</span> ' . esc_html__( 'Not installed - early interception disabled', 'globus-debug-control' ); ?></td>
					</tr>
				</table>

				<p style="margin-top: 20px;"><small>&copy; 2026 GLOBUS.studio | <?php esc_html_e( 'Version', 'globus-debug-control' ); ?> <?php echo esc_html( self::VERSION ); ?></small></p>

			<?php elseif ( 'wpcfg' === $active_tab ) : ?>

				<?php GDC_WpConfig_Debug::render(); ?>

				<p style="margin-top:16px;">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'globus-debug-control', 'tab' => 'constants' ), admin_url( 'options-general.php' ) ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View all WP constants →', 'globus-debug-control' ); ?>
					</a>
				</p>

			<?php elseif ( 'log' === $active_tab ) : ?>

				<?php GDC_Log_Viewer::render(); ?>

			<?php elseif ( 'cron' === $active_tab ) : ?>

				<?php GDC_Cron_Viewer::render(); ?>

			<?php elseif ( 'transients' === $active_tab ) : ?>

				<?php GDC_Transient_Viewer::render(); ?>

			<?php elseif ( 'autoloaded' === $active_tab ) : ?>

				<?php GDC_Options_Viewer::render(); ?>

			<?php elseif ( 'http' === $active_tab ) : ?>

				<?php GDC_Http_Log::render(); ?>

			<?php elseif ( 'constants' === $active_tab ) : ?>

				<?php GDC_Constants_Viewer::render(); ?>

			<?php endif; ?>

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
	 * Enqueues CSS and JS for the plugin settings page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function admin_enqueue_settings_assets( string $hook ): void {
		if ( 'settings_page_globus-debug-control' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'gdc-admin',
			GDC_ASSETS_URL . 'css/admin.css',
			array( 'dashicons' ),
			self::VERSION
		);

		wp_enqueue_script(
			'gdc-admin',
			GDC_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			self::VERSION,
			true
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		if ( 'log' === $tab ) {
			wp_enqueue_code_editor( array( 'type' => 'text/plain' ) );
		}
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
		wp_add_inline_script( 'jquery', $this->get_php_error_class_js() );
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

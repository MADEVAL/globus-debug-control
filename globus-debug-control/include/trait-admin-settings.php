<?php
/**
 * GLOBUS Debug Control - Admin Settings Trait
 *
 * Registers WordPress settings, sanitizes input, and renders
 * all settings field callbacks for the plugin options page.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait GDC_Admin_Settings {

	public function register_settings() {
		register_setting(
			'globus_debug_control',
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		add_settings_section( 'gdc_main', __( 'General Settings', 'globus-debug-control' ), '__return_false', 'globus_debug_control' );

		add_settings_field( 'enabled', __( 'Enable Plugin', 'globus-debug-control' ), array( $this, 'field_enabled' ), 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'suppress', __( 'Error Type Filtering', 'globus-debug-control' ), array( $this, 'field_suppress' ), 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'display_errors_mode', __( 'display_errors Mode', 'globus-debug-control' ), array( $this, 'field_display_mode' ), 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'logging', __( 'Logging Settings', 'globus-debug-control' ), array( $this, 'field_logging' ), 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'remove_php_error_bodyclass', __( 'php-error Class', 'globus-debug-control' ), array( $this, 'field_body_class' ), 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'force_in_api_contexts', __( 'API Contexts', 'globus-debug-control' ), array( $this, 'field_api_contexts' ), 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'install_mu_dropin', __( 'Early Error Interception', 'globus-debug-control' ), array( $this, 'field_mu_dropin' ), 'globus_debug_control', 'gdc_main' );
	}

	public function sanitize_options( $input ): array {
		$output = $this->defaults();

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$bool_fields = array(
			'enabled',
			'suppress_notices',
			'suppress_deprecated',
			'suppress_warnings',
			'suppress_strict',
			'log_enabled',
			'remove_php_error_bodyclass',
			'force_in_api_contexts',
			'install_mu_dropin',
		);

		foreach ( $bool_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? 1 : 0;
		}

		if ( isset( $input['display_errors_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $input['display_errors_mode'] ) );
			if ( in_array( $mode, array( 'inherit', 'on', 'off' ), true ) ) {
				$output['display_errors_mode'] = $mode;
			}
		}

		if ( isset( $input['log_path'] ) ) {
			$output['log_path'] = sanitize_text_field( wp_unslash( $input['log_path'] ) );
		}

		if ( isset( $input['log_max_size_mb'] ) ) {
			$output['log_max_size_mb'] = max( 1, min( 100, (int) $input['log_max_size_mb'] ) );
		}

		if ( $output['install_mu_dropin'] ) {
			$this->install_mu_dropin();
		} else {
			$this->remove_mu_dropin();
		}

		return $output;
	}

	// -------------------------------------------------------------------------
	// Settings field renderers
	// -------------------------------------------------------------------------

	public function field_enabled() {
		$opts = $this->get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[enabled]" value="1" <?php checked( $opts['enabled'], 1 ); ?>>
			<strong><?php esc_html_e( 'Enable debug control', 'globus-debug-control' ); ?></strong>
		</label>
		<p class="description"><?php esc_html_e( 'When disabled, the plugin does not affect PHP or WordPress settings.', 'globus-debug-control' ); ?></p>
		<?php
	}

	public function field_suppress() {
		$opts    = $this->get_options();
		$is_php8 = version_compare( PHP_VERSION, '8.0.0', '>=' );
		?>
		<fieldset>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_notices]" value="1" <?php checked( $opts['suppress_notices'], 1 ); ?>>
				<strong><?php esc_html_e( 'Suppress E_NOTICE', 'globus-debug-control' ); ?></strong> <?php esc_html_e( 'and E_USER_NOTICE', 'globus-debug-control' ); ?>
			</label><br>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_deprecated]" value="1" <?php checked( $opts['suppress_deprecated'], 1 ); ?>>
				<strong><?php esc_html_e( 'Suppress E_DEPRECATED', 'globus-debug-control' ); ?></strong> <?php esc_html_e( 'and E_USER_DEPRECATED', 'globus-debug-control' ); ?>
			</label><br>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_warnings]" value="1" <?php checked( $opts['suppress_warnings'], 1 ); ?>>
				<strong><?php esc_html_e( 'Suppress E_WARNING', 'globus-debug-control' ); ?></strong> <?php esc_html_e( 'and E_USER_WARNING (use with caution!)', 'globus-debug-control' ); ?>
			</label><br>
			<?php if ( ! $is_php8 ) : ?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_strict]" value="1" <?php checked( $opts['suppress_strict'], 1 ); ?>>
				<strong><?php esc_html_e( 'Suppress E_STRICT', 'globus-debug-control' ); ?></strong>
			</label>
			<?php else : ?>
			<label style="opacity: 0.5; cursor: not-allowed;">
				<input type="checkbox" disabled>
				<strong><?php esc_html_e( 'Suppress E_STRICT', 'globus-debug-control' ); ?></strong> <em><?php esc_html_e( '(not available in PHP 8.0+)', 'globus-debug-control' ); ?></em>
			</label>
			<?php endif; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Selected error types will not be displayed or logged. Fatal errors (E_ERROR) are always visible.', 'globus-debug-control' ); ?></p>
		<?php
	}

	public function field_display_mode() {
		$opts = $this->get_options();
		?>
		<select name="<?php echo esc_attr( self::OPT ); ?>[display_errors_mode]">
			<option value="inherit" <?php selected( $opts['display_errors_mode'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from WP_DEBUG_DISPLAY', 'globus-debug-control' ); ?></option>
			<option value="on" <?php selected( $opts['display_errors_mode'], 'on' ); ?>><?php esc_html_e( 'Always on', 'globus-debug-control' ); ?></option>
			<option value="off" <?php selected( $opts['display_errors_mode'], 'off' ); ?>><?php esc_html_e( 'Always off', 'globus-debug-control' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Controls error output on screen. In API contexts, display is automatically disabled.', 'globus-debug-control' ); ?></p>
		<?php
	}

	public function field_logging() {
		$opts = $this->get_options();
		?>
		<fieldset>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[log_enabled]" value="1" <?php checked( $opts['log_enabled'], 1 ); ?>>
				<strong><?php esc_html_e( 'Enable error logging', 'globus-debug-control' ); ?></strong>
			</label><br><br>

			<label for="gdc_log_path"><?php esc_html_e( 'Log file path:', 'globus-debug-control' ); ?></label><br>
			<input type="text" id="gdc_log_path" class="regular-text code" name="<?php echo esc_attr( self::OPT ); ?>[log_path]" value="<?php echo esc_attr( $opts['log_path'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for auto-detection', 'globus-debug-control' ); ?>">
			<p class="description"><?php esc_html_e( 'If empty: uses WP_DEBUG_LOG (usually wp-content/debug.log).', 'globus-debug-control' ); ?><br><?php esc_html_e( 'You can specify an absolute or relative (from wp-content) path.', 'globus-debug-control' ); ?></p>

			<label for="gdc_log_max_size"><?php esc_html_e( 'Maximum log size (MB):', 'globus-debug-control' ); ?></label><br>
			<input type="number" id="gdc_log_max_size" name="<?php echo esc_attr( self::OPT ); ?>[log_max_size_mb]" value="<?php echo esc_attr( $opts['log_max_size_mb'] ); ?>" min="1" max="100" style="width: 80px;">
			<p class="description"><?php esc_html_e( 'When exceeded, the file will be renamed with a date suffix and a new one created. Range: 1-100 MB.', 'globus-debug-control' ); ?></p>
		</fieldset>
		<?php
	}

	public function field_body_class() {
		$opts = $this->get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[remove_php_error_bodyclass]" value="1" <?php checked( $opts['remove_php_error_bodyclass'], 1 ); ?>>
			<strong>
			<?php
				$gdc_body_text = sprintf(
					/* translators: 1: CSS class name, 2: HTML tag name */
					__( 'Remove %1$s class from %2$s', 'globus-debug-control' ),
					'<code>php-error</code>',
					'<code>&lt;body&gt;</code>'
				);
					echo wp_kses( $gdc_body_text, array( 'code' => array() ) );
			?>
			</strong>
		</label>
		<p class="description"><?php esc_html_e( 'WordPress adds this class when errors are detected. It can interfere with styling.', 'globus-debug-control' ); ?></p>
		<?php
	}

	public function field_api_contexts() {
		$opts = $this->get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[force_in_api_contexts]" value="1" <?php checked( $opts['force_in_api_contexts'], 1 ); ?>>
			<strong><?php esc_html_e( 'Show errors in API contexts', 'globus-debug-control' ); ?></strong>
		</label>
		<p class="description"><?php esc_html_e( 'By default, error display is disabled in AJAX, REST API, Cron, and CLI for proper operation.', 'globus-debug-control' ); ?><br><?php esc_html_e( 'Enable only for API debugging.', 'globus-debug-control' ); ?></p>
		<?php
	}

	public function field_mu_dropin() {
		$opts      = $this->get_options();
		$installed = $this->is_mu_dropin_installed();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[install_mu_dropin]" value="1" <?php checked( $opts['install_mu_dropin'], 1 ); ?>>
			<strong><?php esc_html_e( 'Install mu-plugin for early interception', 'globus-debug-control' ); ?></strong>
		</label>
		<?php if ( $installed ) : ?>
			<span style="color: #00a32a; margin-left: 8px;">&#9679; <?php esc_html_e( 'Installed', 'globus-debug-control' ); ?></span>
		<?php else : ?>
			<span style="color: #999; margin-left: 8px;">&#9675; <?php esc_html_e( 'Not installed', 'globus-debug-control' ); ?></span>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'WordPress loads plugins alphabetically. Errors from plugins loaded earlier (e.g. Elementor) occur before GLOBUS Debug Control initializes.', 'globus-debug-control' ); ?><br>
			<?php
			$gdc_mu_text = sprintf(
				/* translators: 1: mu-plugin filename, 2: directory path */
				__( 'The mu-plugin loads first and intercepts such errors. File: %1$s in %2$s', 'globus-debug-control' ),
				'<code>' . esc_html( self::MU_FILE ) . '</code>',
				'<code>wp-content/mu-plugins/</code>'
			);
			echo wp_kses( $gdc_mu_text, array( 'code' => array() ) );
			?>
		</p>
		<?php
	}
}

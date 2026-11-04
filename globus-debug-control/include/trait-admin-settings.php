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

	/**
	 * Registers the plugin option group, setting, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'globus_debug_control',
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'autoload'          => false,
			)
		);

		add_settings_section( 'gdc_main', __( 'General Settings', 'globus-debug-control' ), '__return_false', 'globus_debug_control' );

		foreach ( $this->settings_fields_map() as $field_id => $field ) {
			add_settings_field( $field_id, $field['label'], array( $this, $field['callback'] ), 'globus_debug_control', 'gdc_main' );
		}
	}

	/**
	 * Returns the settings fields, their labels, callbacks, and visual groups.
	 *
	 * @return array<string, array{label: string, callback: string, group: string}>
	 */
	private function settings_fields_map(): array {
		return array(
			'enabled'                    => array(
				'label'    => __( 'Enable Plugin', 'globus-debug-control' ),
				'callback' => 'field_enabled',
				'group'    => 'general',
			),
			'suppress'                   => array(
				'label'    => __( 'Error Type Filtering', 'globus-debug-control' ),
				'callback' => 'field_suppress',
				'group'    => 'filtering',
			),
			'display_errors_mode'        => array(
				'label'    => __( 'display_errors Mode', 'globus-debug-control' ),
				'callback' => 'field_display_mode',
				'group'    => 'filtering',
			),
			'logging'                    => array(
				'label'    => __( 'Logging Settings', 'globus-debug-control' ),
				'callback' => 'field_logging',
				'group'    => 'logging',
			),
			'remove_php_error_bodyclass' => array(
				'label'    => __( 'php-error Class', 'globus-debug-control' ),
				'callback' => 'field_body_class',
				'group'    => 'display',
			),
			'force_in_api_contexts'      => array(
				'label'    => __( 'API Contexts', 'globus-debug-control' ),
				'callback' => 'field_api_contexts',
				'group'    => 'display',
			),
			'install_mu_dropin'          => array(
				'label'    => __( 'Early Error Interception', 'globus-debug-control' ),
				'callback' => 'field_mu_dropin',
				'group'    => 'early',
			),
		);
	}

	/**
	 * Returns the visual groups used on the settings screen.
	 *
	 * @return array<string, array{title: string, description: string}>
	 */
	private function settings_groups(): array {
		return array(
			'general'   => array(
				'title'       => __( 'Plugin status', 'globus-debug-control' ),
				'description' => __( 'Turn all debug control behaviour on or off.', 'globus-debug-control' ),
			),
			'filtering' => array(
				'title'       => __( 'Error filtering', 'globus-debug-control' ),
				'description' => __( 'Choose which PHP error types are hidden and how errors are displayed.', 'globus-debug-control' ),
			),
			'logging'   => array(
				'title'       => __( 'Logging', 'globus-debug-control' ),
				'description' => __( 'Write errors to a file and rotate it when it grows too large.', 'globus-debug-control' ),
			),
			'display'   => array(
				'title'       => __( 'Display and API contexts', 'globus-debug-control' ),
				'description' => __( 'Front-end cosmetics and non-HTML request handling.', 'globus-debug-control' ),
			),
			'early'     => array(
				'title'       => __( 'Early interception', 'globus-debug-control' ),
				'description' => __( 'Load the error filter before regular plugins.', 'globus-debug-control' ),
			),
		);
	}

	/**
	 * Renders the grouped settings form with one card per field group.
	 */
	public function render_settings_form(): void {
		$fields = $this->settings_fields_map();

		foreach ( $this->settings_groups() as $group_key => $group ) {
			$group_fields = array_filter(
				$fields,
				static function ( array $field ) use ( $group_key ): bool {
					return $group_key === $field['group'];
				}
			);

			if ( empty( $group_fields ) ) {
				continue;
			}
			?>
			<div class="gdc-card">
				<div class="gdc-card-header">
					<div>
						<h2><?php echo esc_html( $group['title'] ); ?></h2>
						<p><?php echo esc_html( $group['description'] ); ?></p>
					</div>
				</div>
				<div class="gdc-card-body">
					<table class="form-table" role="presentation">
						<tbody>
							<?php foreach ( $group_fields as $field ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $field['label'] ); ?></th>
									<td>
										<?php call_user_func( array( $this, $field['callback'] ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		submit_button( __( 'Save Settings', 'globus-debug-control' ) );
	}

	/**
	 * Sanitises and validates raw settings form input.
	 *
	 * Also triggers mu-plugin install or removal based on the checkbox value.
	 *
	 * @param mixed $input Raw value from the settings form.
	 * @return array<string, mixed> Clean, validated options array.
	 */
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
			$path = sanitize_text_field( wp_unslash( $input['log_path'] ) );

			// Reject parent-traversal paths: a "log" pointing at wp-config.php
			// could be truncated by the "Clear Log" action.
			$output['log_path'] = $this->sanitize_path( $path );
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

	/**
	 * Renders the "Enable Plugin" settings field.
	 */
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

	/**
	 * Renders the "Error Type Filtering" settings field.
	 */
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

	/**
	 * Renders the "display_errors Mode" settings field.
	 */
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

	/**
	 * Renders the "Logging Settings" settings field.
	 */
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

	/**
	 * Renders the "php-error Class" settings field.
	 */
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

	/**
	 * Renders the "API Contexts" settings field.
	 */
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

	/**
	 * Renders the "Early Error Interception" mu-plugin settings field.
	 */
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

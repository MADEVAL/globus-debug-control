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
	 * Registers the plugin option with the WordPress Settings API.
	 *
	 * The admin UI renders its own form, so no settings sections or fields are
	 * registered here; options.php still handles the nonce, capability and
	 * redirect for the custom form.
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
	}

	/**
	 * AJAX handler: toggles a single boolean option without touching the rest.
	 *
	 * Used by the Overview master switch. Only an allowlist of side-effect free
	 * keys is accepted; everything else keeps going through the settings form.
	 */
	public function ajax_toggle_setting(): void {
		check_ajax_referer( 'gdc_admin' );

		if ( ! current_user_can( self::manage_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'globus-debug-control' ) ), 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';

		if ( ! in_array( $key, array( 'enabled', 'log_enabled' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown setting.', 'globus-debug-control' ) ), 400 );
		}

		$value   = ! empty( $_POST['value'] ) ? 1 : 0;
		$options = $this->get_options( true );

		$options[ $key ] = $value;
		update_option( self::OPT, $options );

		wp_send_json_success(
			array(
				'key'   => $key,
				'value' => $value,
			)
		);
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
}

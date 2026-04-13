<?php
/**
 * GLOBUS Debug Control - Constants Viewer
 *
 * Displays all WordPress-related PHP constants defined at runtime, grouped by
 * category. Sensitive values (auth keys, salts, DB password, DB host) are masked.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only view of WP-related runtime PHP constants.
 *
 * @package GlobusDebugControl
 */
final class GDC_Constants_Viewer {

	/**
	 * Returns the list of constant names whose values must be masked.
	 *
	 * @return string[]
	 */
	public static function sensitive_keys(): array {
		return array(
			'DB_PASSWORD',
			'DB_HOST',
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);
	}

	/**
	 * Formats a single constant value for display, masking sensitive ones.
	 *
	 * @param string   $name      Constant name.
	 * @param mixed    $value     Raw value.
	 * @param string[] $sensitive Names to mask.
	 * @return string
	 */
	public static function format_value( string $name, $value, array $sensitive ): string {
		if ( in_array( $name, $sensitive, true ) ) {
			return '*** (masked)';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( null === $value ) {
			return 'null';
		}
		return (string) $value;
	}

	/**
	 * Returns WP-related constants grouped by category.
	 *
	 * @return array<string, array<string, string>> Group label => [constant_name => display_value]
	 */
	public static function get_wp_constants(): array {
		$all_constants = get_defined_constants( true );
		$user          = isset( $all_constants['user'] ) ? $all_constants['user'] : array();
		$sensitive     = self::sensitive_keys();

		$groups = array(
			'Debug'        => array(),
			'Performance'  => array(),
			'Security'     => array(),
			'Database'     => array(),
			'Auth & Salts' => array(),
			'Paths'        => array(),
			'Other WP_*'   => array(),
		);

		$debug_names = array(
			'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG', 'SAVEQUERIES', 'WP_DEVELOPMENT_MODE',
		);
		$perf_names = array(
			'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'WP_CACHE',
			'CONCATENATE_SCRIPTS', 'COMPRESS_SCRIPTS', 'COMPRESS_CSS',
			'WP_HTTP_BLOCK_EXTERNAL', 'WP_ACCESSIBLE_HOSTS',
		);
		$sec_names = array(
			'FORCE_SSL_ADMIN', 'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS',
			'DISALLOW_UNFILTERED_HTML', 'ALLOW_UNFILTERED_UPLOADS',
		);
		$db_names   = array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE' );
		$auth_names = array(
			'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
			'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
		);
		$path_names = array(
			'ABSPATH', 'WPINC', 'WP_CONTENT_DIR', 'WP_CONTENT_URL',
			'WP_PLUGIN_DIR', 'WP_PLUGIN_URL', 'WPMU_PLUGIN_DIR',
			'WP_LANG_DIR', 'WP_TEMP_DIR', 'WP_HOME', 'WP_SITEURL',
		);

		foreach ( $user as $name => $value ) {
			$display = self::format_value( $name, $value, $sensitive );

			if ( in_array( $name, $debug_names, true ) ) {
				$groups['Debug'][ $name ] = $display;
			} elseif ( in_array( $name, $perf_names, true ) ) {
				$groups['Performance'][ $name ] = $display;
			} elseif ( in_array( $name, $sec_names, true ) ) {
				$groups['Security'][ $name ] = $display;
			} elseif ( in_array( $name, $db_names, true ) ) {
				$groups['Database'][ $name ] = $display;
			} elseif ( in_array( $name, $auth_names, true ) ) {
				$groups['Auth & Salts'][ $name ] = $display;
			} elseif ( in_array( $name, $path_names, true ) ) {
				$groups['Paths'][ $name ] = $display;
			} elseif ( 0 === strpos( $name, 'WP_' ) ) {
				$groups['Other WP_*'][ $name ] = $display;
			}
		}

		return array_filter( $groups );
	}

	/**
	 * Renders the constants viewer UI.
	 */
	public static function render(): void {
		$groups    = self::get_wp_constants();
		$wpcfg_url = add_query_arg(
			array(
				'page' => 'globus-debug-control',
				'tab'  => 'wpcfg',
			),
			admin_url( 'options-general.php' )
		);
		?>
		<p>
			<a href="<?php echo esc_url( $wpcfg_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-arrow-left-alt" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>
				<?php esc_html_e( 'WP_DEBUG settings', 'globus-debug-control' ); ?>
			</a>
		</p>

		<?php if ( empty( $groups ) ) : ?>
			<div class="gdc-empty-state">
				<p><?php esc_html_e( 'No WP constants found.', 'globus-debug-control' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $groups as $group_label => $constants ) : ?>
				<h3 class="gdc-constant-group-heading"><?php echo esc_html( $group_label ); ?></h3>
				<table class="wp-list-table widefat striped gdc-constants-table" style="margin-bottom:20px;">
					<thead>
						<tr>
							<th style="width:50%;"><?php esc_html_e( 'Constant', 'globus-debug-control' ); ?></th>
							<th><?php esc_html_e( 'Value', 'globus-debug-control' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $constants as $const_name => $const_value ) : ?>
							<tr>
								<td><code><?php echo esc_html( $const_name ); ?></code></td>
								<td>
									<?php if ( '*** (masked)' === $const_value ) : ?>
										<span class="gdc-constant-masked"><?php esc_html_e( '*** (masked)', 'globus-debug-control' ); ?></span>
									<?php else : ?>
										<code><?php echo esc_html( $const_value ); ?></code>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php
	}
}

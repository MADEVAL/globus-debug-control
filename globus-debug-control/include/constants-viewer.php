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
	 * Returns true when a constant name looks like it holds a secret.
	 *
	 * Exact names from sensitive_keys() are always masked. Additional
	 * heuristics catch credentials defined by plugins and custom code.
	 *
	 * @param string   $name      Constant name.
	 * @param string[] $sensitive Exact names to mask.
	 * @return bool
	 */
	public static function is_sensitive_name( string $name, array $sensitive ): bool {
		if ( in_array( $name, $sensitive, true ) ) {
			return true;
		}

		$upper    = strtoupper( $name );
		$patterns = array( 'PASSWORD', 'SECRET', 'PRIVATE_KEY', 'ACCESS_KEY', 'API_KEY', 'AUTH_KEY', 'SALT', 'TOKEN' );

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $upper, $pattern ) ) {
				return true;
			}
		}

		return false;
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
		if ( self::is_sensitive_name( $name, $sensitive ) ) {
			return '*** (masked)';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( null === $value ) {
			return 'null';
		}
		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ')';
		}
		if ( is_object( $value ) ) {
			return 'object(' . get_class( $value ) . ')';
		}
		return (string) $value;
	}

	/**
	 * Returns all user-defined constants grouped by category.
	 *
	 * WordPress core constants get dedicated groups; constants defined by
	 * plugins, themes, and custom code land in "Plugin & Theme".
	 *
	 * @return array<string, array<string, string>> Group label => [constant_name => display_value]
	 */
	public static function get_wp_constants(): array {
		$all_constants = get_defined_constants( true );
		$user          = isset( $all_constants['user'] ) ? $all_constants['user'] : array();
		$sensitive     = self::sensitive_keys();

		$groups = array(
			'Debug'          => array(),
			'Performance'    => array(),
			'Security'       => array(),
			'Database'       => array(),
			'Auth & Salts'   => array(),
			'Paths'          => array(),
			'Other WP_*'     => array(),
			'Plugin & Theme' => array(),
		);

		$debug_names = array(
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG',
			'SAVEQUERIES',
			'WP_DEVELOPMENT_MODE',
		);
		$perf_names  = array(
			'WP_MEMORY_LIMIT',
			'WP_MAX_MEMORY_LIMIT',
			'WP_CACHE',
			'CONCATENATE_SCRIPTS',
			'COMPRESS_SCRIPTS',
			'COMPRESS_CSS',
			'WP_HTTP_BLOCK_EXTERNAL',
			'WP_ACCESSIBLE_HOSTS',
		);
		$sec_names   = array(
			'FORCE_SSL_ADMIN',
			'DISALLOW_FILE_EDIT',
			'DISALLOW_FILE_MODS',
			'DISALLOW_UNFILTERED_HTML',
		);
		$db_names    = array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE' );
		$auth_names  = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);
		$path_names  = array(
			'ABSPATH',
			'WPINC',
			'WP_CONTENT_DIR',
			'WP_CONTENT_URL',
			'WP_PLUGIN_DIR',
			'WP_PLUGIN_URL',
			'WPMU_PLUGIN_DIR',
			'WP_LANG_DIR',
			'WP_TEMP_DIR',
			'WP_HOME',
			'WP_SITEURL',
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
			} else {
				$groups['Plugin & Theme'][ $name ] = $display;
			}
		}

		foreach ( $groups as &$constants ) {
			ksort( $constants, SORT_NATURAL | SORT_FLAG_CASE );
		}
		unset( $constants );

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
			admin_url( 'admin.php' )
		);
		?>
		<p>
			<a href="<?php echo esc_url( $wpcfg_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'WP_DEBUG settings', 'globus-debug-control' ); ?>
			</a>
		</p>

		<?php if ( empty( $groups ) ) : ?>
			<div class="gdc-empty-state">
				<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No constants found.', 'globus-debug-control' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$total_constants = array_sum( array_map( 'count', $groups ) );
			?>
			<div class="gdc-constants-toolbar">
				<label class="screen-reader-text" for="gdc-constants-filter"><?php esc_html_e( 'Filter constants', 'globus-debug-control' ); ?></label>
				<input type="search" id="gdc-constants-filter" class="regular-text" placeholder="<?php esc_attr_e( 'Filter by constant name...', 'globus-debug-control' ); ?>">
				<span class="gdc-constants-total">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of constants */
							_n( '%s constant', '%s constants', $total_constants, 'globus-debug-control' ),
							number_format_i18n( $total_constants )
						)
					);
					?>
				</span>
			</div>

			<div class="gdc-constants-groups">
				<?php foreach ( $groups as $group_label => $constants ) : ?>
					<?php $is_open = 'Plugin & Theme' !== $group_label; ?>
					<details class="gdc-constants-group" data-default-open="<?php echo $is_open ? '1' : '0'; ?>" <?php echo $is_open ? 'open' : ''; ?>>
						<summary>
							<span class="gdc-constant-group-heading"><?php echo esc_html( $group_label ); ?></span>
							<span class="gdc-badge"><?php echo esc_html( number_format_i18n( count( $constants ) ) ); ?></span>
						</summary>
						<div class="gdc-table-scroll">
							<table class="wp-list-table widefat striped gdc-constants-table">
								<tbody>
									<?php foreach ( $constants as $const_name => $const_value ) : ?>
										<tr class="gdc-constant-row" data-name="<?php echo esc_attr( strtolower( $const_name ) ); ?>">
											<td class="gdc-constant-name"><code><?php echo esc_html( $const_name ); ?></code></td>
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
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}
}

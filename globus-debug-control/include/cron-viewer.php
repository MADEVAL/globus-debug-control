<?php
/**
 * GLOBUS Debug Control - Cron Viewer
 *
 * Renders a table of all scheduled WP-Cron events.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders WordPress cron job schedule information.
 *
 * @package GlobusDebugControl
 */
final class GDC_Cron_Viewer {

	private const NONCE_SOURCES = 'gdc_cron_sources';

	/**
	 * Outputs the complete cron-viewer HTML table.
	 */
	public static function render(): void {
		$crons = self::get_cron_events();
		?>
		<div class="gdc-cron-viewer">
			<?php if ( empty( $crons ) ) : ?>
				<div class="gdc-empty-state">
					<p><?php esc_html_e( 'No scheduled cron events found.', 'globus-debug-control' ); ?></p>
				</div>
			<?php else : ?>
				<script>var gdcCron=
				<?php
				echo wp_json_encode(
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( self::NONCE_SOURCES ),
					)
				);
				?>
									;</script>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of events */
							_n( '%d scheduled event', '%d scheduled events', count( $crons ), 'globus-debug-control' ),
							count( $crons )
						)
					);
					?>
				</p>
				<table class="wp-list-table widefat fixed striped gdc-cron-table">
					<thead>
						<tr>
							<th scope="col" style="width: 160px;"><?php esc_html_e( 'Next Run', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width: 120px;"><?php esc_html_e( 'In', 'globus-debug-control' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Hook', 'globus-debug-control' ); ?></th>
						<th scope="col" style="width: 150px;"><?php esc_html_e( 'Source', 'globus-debug-control' ); ?></th>
						<th scope="col" style="width: 140px;"><?php esc_html_e( 'Schedule', 'globus-debug-control' ); ?></th>
							<th scope="col" style="width: 80px;"><?php esc_html_e( 'Args', 'globus-debug-control' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $crons as $event ) : ?>
						<tr class="<?php echo $event['overdue'] ? 'gdc-cron-overdue' : ''; ?>">
							<td>
								<?php echo esc_html( gmdate( 'Y-m-d H:i:s', $event['timestamp'] ) ); ?>
								<?php if ( $event['overdue'] ) : ?>
									<span class="gdc-badge gdc-badge-warning"><?php esc_html_e( 'overdue', 'globus-debug-control' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="<?php echo $event['overdue'] ? 'gdc-text-warning' : 'gdc-text-muted'; ?>">
								<?php echo esc_html( self::human_time_diff( $event['timestamp'] ) ); ?>
							</td>
							<td><code><?php echo esc_html( $event['hook'] ); ?></code></td>
						<td class="gdc-cron-source" data-hook="<?php echo esc_attr( $event['hook'] ); ?>">
							<span class="spinner is-active" style="float:none;margin:0;"></span>
						</td>
							<td>
								<?php if ( ! empty( $event['schedule'] ) ) : ?>
									<span class="gdc-badge gdc-badge-info"><?php echo esc_html( $event['schedule'] ); ?></span>
									<?php if ( ! empty( $event['interval'] ) ) : ?>
										<small class="gdc-text-muted"><?php echo esc_html( self::interval_label( $event['interval'] ) ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span class="gdc-text-muted"><?php esc_html_e( 'one-time', 'globus-debug-control' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $event['args'] ) ) : ?>
									<code title="<?php echo esc_attr( wp_json_encode( $event['args'] ) ); ?>"><?php esc_html_e( 'yes', 'globus-debug-control' ); ?></code>
								<?php else : ?>
									<span class="gdc-text-muted">-</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Returns a sorted, normalised list of all scheduled cron events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cron_events(): array {
		$crons = _get_cron_array();

		if ( empty( $crons ) || ! is_array( $crons ) ) {
			return array();
		}

		$events = array();
		$now    = time();

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $event ) {
					$events[] = array(
						'timestamp' => (int) $timestamp,
						'hook'      => (string) $hook,
						'schedule'  => isset( $event['schedule'] ) ? (string) $event['schedule'] : '',
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
						'args'      => isset( $event['args'] ) && is_array( $event['args'] ) ? array_filter( $event['args'] ) : array(),
						'overdue'   => (int) $timestamp < $now - 60,
					);
				}
			}
		}

		usort(
			$events,
			static function ( array $a, array $b ): int {
				return $a['timestamp'] - $b['timestamp'];
			}
		);

		return $events;
	}

	/**
	 * Registers AJAX action hooks for the cron viewer.
	 */
	public static function register_ajax_hooks(): void {
		add_action( 'wp_ajax_gdc_cron_sources', array( self::class, 'ajax_cron_sources' ) );
	}

	/**
	 * AJAX handler: resolves source info for a list of cron hook names.
	 *
	 * Accepts POST param `hooks[]` — an array of hook name strings.
	 * Returns a JSON object keyed by hook name, value is the source descriptor.
	 */
	public static function ajax_cron_sources(): void {
		check_ajax_referer( self::NONCE_SOURCES );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$raw = isset( $_POST['hooks'] ) && is_array( $_POST['hooks'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? wp_unslash( $_POST['hooks'] )
			: array();

		$result = array();
		foreach ( $raw as $hook ) {
			$hook = preg_replace( '/[^a-zA-Z0-9_\-\/\.]/', '', substr( (string) $hook, 0, 200 ) );
			if ( '' !== $hook ) {
				$result[ $hook ] = self::resolve_hook_source( $hook );
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * Resolves the origin (WordPress core, a plugin, or a theme) of a cron hook.
	 *
	 * Inspects registered callbacks via $wp_filter to determine the source file,
	 * then maps it to the appropriate source category.
	 *
	 * @param string $hook Hook name to inspect.
	 * @return array{type: string, name: string, slug: string}
	 */
	public static function resolve_hook_source( string $hook ): array {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return self::find_hook_in_files( $hook );
		}

		$filter_entry  = $wp_filter[ $hook ];
		$callbacks_map = is_object( $filter_entry ) && property_exists( $filter_entry, 'callbacks' )
			? $filter_entry->callbacks
			: (array) $filter_entry;

		$sources = array();

		foreach ( $callbacks_map as $priority_callbacks ) {
			if ( ! is_array( $priority_callbacks ) ) {
				continue;
			}
			foreach ( $priority_callbacks as $cb ) {
				if ( ! isset( $cb['function'] ) ) {
					continue;
				}
				$file = self::get_callback_file( $cb['function'] );
				if ( null === $file ) {
					continue;
				}
				$source = self::classify_file_source( $file );
				$key    = $source['type'] . ':' . $source['slug'];
				if ( ! isset( $sources[ $key ] ) ) {
					$sources[ $key ] = $source;
				}
			}
		}

		if ( empty( $sources ) ) {
			return self::find_hook_in_files( $hook );
		}

		uasort(
			$sources,
			static function ( array $a, array $b ): int {
				$order = array(
					'plugin'  => 0,
					'theme'   => 1,
					'core'    => 2,
					'unknown' => 3,
				);
				return ( $order[ $a['type'] ] ?? 3 ) - ( $order[ $b['type'] ] ?? 3 );
			}
		);

		return reset( $sources );
	}

	/**
	 * Returns the resolved file path for a callable, or null when not determinable.
	 *
	 * @param mixed $hook_cb Any callable (string function name, [object|string, method] array, Closure).
	 * @return string|null Forward-slash normalised absolute path, or null.
	 */
	private static function get_callback_file( $hook_cb ): ?string {
		try {
			if ( $hook_cb instanceof \Closure || ( is_string( $hook_cb ) && function_exists( $hook_cb ) ) ) {
				$ref  = new \ReflectionFunction( $hook_cb );
				$file = $ref->getFileName();
			} elseif ( is_array( $hook_cb ) && 2 === count( $hook_cb ) ) {
				$ref  = new \ReflectionMethod( $hook_cb[0], $hook_cb[1] );
				$file = $ref->getFileName();
			} else {
				return null;
			}
			return false !== $file ? str_replace( '\\', '/', $file ) : null;
		} catch ( \ReflectionException $e ) {
			return null;
		}
	}

	/**
	 * Maps an absolute file path to a source descriptor (core, plugin, theme, or unknown).
	 *
	 * Checks plugin directories before ABSPATH to handle installations where
	 * wp-content resides inside the WordPress root.
	 *
	 * @param string $file Forward-slash normalised absolute file path.
	 * @return array{type: string, name: string, slug: string}
	 */
	private static function classify_file_source( string $file ): array {
		$content_dir = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );
		$plugins_dir = defined( 'WP_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' )
			: $content_dir . '/plugins';
		$mu_dir      = defined( 'WPMU_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WPMU_PLUGIN_DIR ), '/' )
			: $content_dir . '/mu-plugins';
		$themes_dir  = $content_dir . '/themes';
		$abspath     = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );

		if ( 0 === strpos( $file, $plugins_dir . '/' ) ) {
			$slug = explode( '/', substr( $file, strlen( $plugins_dir ) + 1 ) )[0];
			return array(
				'type' => 'plugin',
				'name' => $slug,
				'slug' => $slug,
			);
		}

		if ( 0 === strpos( $file, $mu_dir . '/' ) ) {
			$slug = explode( '/', substr( $file, strlen( $mu_dir ) + 1 ) )[0];
			return array(
				'type' => 'plugin',
				'name' => $slug . ' (mu)',
				'slug' => $slug,
			);
		}

		if ( 0 === strpos( $file, $themes_dir . '/' ) ) {
			$slug = explode( '/', substr( $file, strlen( $themes_dir ) + 1 ) )[0];
			return array(
				'type' => 'theme',
				'name' => $slug,
				'slug' => $slug,
			);
		}

		if ( 0 === strpos( $file, $abspath . '/' ) || $file === $abspath ) {
			return array(
				'type' => 'core',
				'name' => 'WordPress',
				'slug' => 'core',
			);
		}

		return array(
			'type' => 'unknown',
			'name' => '—',
			'slug' => '',
		);
	}

	/**
	 * Fallback source detection when no callbacks are registered in $wp_filter.
	 *
	 * Scans PHP files in plugin, theme, and WordPress core directories for the
	 * hook name string. Results are cached per request via a static variable to
	 * avoid repeated disk scans for the same hook.
	 *
	 * @param string $hook Hook name to search for in file contents.
	 * @return array{type: string, name: string, slug: string}
	 */
	private static function find_hook_in_files( string $hook ): array {
		static $cache = array();

		if ( array_key_exists( $hook, $cache ) ) {
			return $cache[ $hook ];
		}

		$content_dir = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );
		$plugins_dir = defined( 'WP_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' )
			: $content_dir . '/plugins';
		$mu_dir      = defined( 'WPMU_PLUGIN_DIR' )
			? rtrim( str_replace( '\\', '/', WPMU_PLUGIN_DIR ), '/' )
			: $content_dir . '/mu-plugins';
		$themes_dir  = $content_dir . '/themes';
		$abspath     = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );

		$found = self::scan_slug_dirs( $hook, $plugins_dir, 'plugin' )
			?? self::scan_slug_dirs( $hook, $mu_dir, 'plugin', true )
			?? self::scan_slug_dirs( $hook, $themes_dir, 'theme' );

		if ( null === $found ) {
			foreach ( array( $abspath . '/wp-includes', $abspath . '/wp-admin' ) as $core_dir ) {
				if ( self::hook_string_in_tree( $hook, $core_dir ) ) {
					$found = array(
						'type' => 'core',
						'name' => 'WordPress',
						'slug' => 'core',
					);
					break;
				}
			}
		}

		$result         = $found ?? array(
			'type' => 'unknown',
			'name' => '—',
			'slug' => '',
		);
		$cache[ $hook ] = $result;
		return $result;
	}

	/**
	 * Iterates the immediate subdirectories of $base_dir (plugin or theme slugs),
	 * returning a source descriptor for the first slug directory whose PHP file
	 * tree contains the hook name as a literal string.
	 *
	 * @param string $hook     Hook name to search for.
	 * @param string $base_dir Absolute path to the plugins or themes base directory.
	 * @param string $type     'plugin' or 'theme'.
	 * @param bool   $mu       Whether the directory is the must-use-plugins directory.
	 * @return array{type: string, name: string, slug: string}|null
	 */
	private static function scan_slug_dirs( string $hook, string $base_dir, string $type, bool $mu = false ): ?array {
		if ( ! is_dir( $base_dir ) ) {
			return null;
		}

		$slug_dirs = glob( rtrim( $base_dir, '/' ) . '/*', GLOB_ONLYDIR );
		if ( ! $slug_dirs ) {
			return null;
		}

		foreach ( $slug_dirs as $slug_dir ) {
			if ( self::hook_string_in_tree( $hook, $slug_dir ) ) {
				$slug = basename( $slug_dir );
				return array(
					'type' => $type,
					'name' => $mu ? $slug . ' (mu)' : $slug,
					'slug' => $slug,
				);
			}
		}

		return null;
	}

	/**
	 * Returns true when at least one PHP file in the given directory tree
	 * contains the hook name as a literal substring.
	 *
	 * @param string $hook Hook name string to search for.
	 * @param string $dir  Absolute path to the directory to scan.
	 * @return bool
	 */
	private static function hook_string_in_tree( string $hook, string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		foreach ( $it as $php_file ) {
			if ( ! $php_file->isFile() || 'php' !== $php_file->getExtension() ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = file_get_contents( $php_file->getPathname() );
			if ( false !== $contents && false !== strpos( $contents, $hook ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns a human-readable relative time string for a cron timestamp.
	 *
	 * @param int $timestamp Unix timestamp of the scheduled event.
	 * @return string E.g. "in 5 minutes" or "3 hours ago".
	 */
	public static function human_time_diff( int $timestamp ): string {
		$now  = time();
		$diff = $timestamp - $now;

		if ( $diff < 0 ) {
			return human_time_diff( $timestamp, $now ) . ' ' . __( 'ago', 'globus-debug-control' );
		}

		return __( 'in', 'globus-debug-control' ) . ' ' . human_time_diff( $now, $timestamp );
	}

	/**
	 * Returns a compact human-readable label for a cron interval in seconds.
	 *
	 * @param int $seconds Interval in seconds.
	 * @return string E.g. "15m", "1h", "1d".
	 */
	public static function interval_label( int $seconds ): string {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		if ( $seconds < 3600 ) {
			return round( $seconds / 60 ) . 'm';
		}

		if ( $seconds < 86400 ) {
			return round( $seconds / 3600 ) . 'h';
		}

		return round( $seconds / 86400 ) . 'd';
	}

	/**
	 * Returns an empty string (styles are enqueued via the global admin stylesheet).
	 *
	 * @return string
	 */
	public static function get_inline_styles(): string {
		return '';
	}
}

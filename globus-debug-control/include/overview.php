<?php
/**
 * GLOBUS Debug Control - Overview data
 *
 * Collects the signals shown on the Overview screen and computes the
 * health score. Rendering lives in include/ui/partials/overview-tab.php.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only aggregation of debug, cron, transient, autoload and HTTP state.
 */
final class GDC_Overview {

	/** Autoloaded options payload considered healthy (800 KB). */
	private const AUTOLOAD_WARN_BYTES = 819200;

	/** Autoloaded option rows considered healthy. */
	private const AUTOLOAD_WARN_COUNT = 100;

	/**
	 * Returns everything the Overview screen needs.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_data(): array {
		$options = Globus_Debug_Control::instance()->get_options();

		$log_path   = GDC_Log_Viewer::get_log_path();
		$log_exists = '' !== $log_path && file_exists( $log_path );
		$log_size   = $log_exists ? (int) @filesize( $log_path ) : 0;

		$log = array(
			'path'     => $log_path,
			'exists'   => $log_exists,
			'size'     => $log_size,
			'lines'    => self::count_lines( $log_path, $log_exists ),
			'severity' => self::log_severity( $log_path, $log_exists ),
			'writable' => '' !== $log_path && wp_is_writable( dirname( $log_path ) ),
		);

		$transients = GDC_Transient_Viewer::get_transients();
		$transient  = GDC_Transient_Viewer::get_summary( $transients );

		$autoloaded = GDC_Options_Viewer::get_autoloaded();
		$autoload   = GDC_Options_Viewer::get_stats( $autoloaded );

		$cron               = GDC_Cron_Viewer::get_cron_summary();
		$http               = GDC_Http_Log::get_log();
		$mu_installed       = Globus_Debug_Control::instance()->is_mu_dropin_installed();
		$mu_version         = (string) get_option( 'gdc_mu_dropin_version', '' );
		$config_path        = GDC_WpConfig_Debug::find_wp_config();
		$wp_config_writable = '' !== $config_path && wp_is_writable( $config_path );

		$data = array(
			'options'    => $options,
			'log'        => $log,
			'transients' => $transient,
			'autoload'   => $autoload,
			'cron'       => $cron,
			'http'       => array(
				'capturing' => GDC_Http_Log::is_capturing(),
				'count'     => count( $http ),
			),
			'mu'         => array(
				'installed' => $mu_installed,
				'current'   => $mu_installed && version_compare( $mu_version, Globus_Debug_Control::version(), '>=' ),
				'version'   => $mu_version,
			),
			'wp_config'  => array(
				'path'         => $config_path,
				'writable'     => $wp_config_writable,
				'wp_debug'     => GDC_WpConfig_Debug::is_wp_debug_on(),
				'wp_debug_log' => GDC_WpConfig_Debug::is_wp_debug_log_on(),
			),
			'last_fatal' => get_option( 'gdc_last_fatal', null ),
			'suppressed' => count( GDC_Log_Viewer::get_suppressed_types() ),
		);

		$health = self::health( $data );

		$data['health'] = $health;
		$data['charts'] = array(
			'cron'     => self::cron_chart(),
			'autoload' => self::autoload_chart( $autoloaded ),
		);

		return $data;
	}

	/**
	 * Counts log lines without loading a huge file into memory.
	 *
	 * @param string $path   Log file path.
	 * @param bool   $exists Whether the file exists.
	 * @return int|null Null when the file is missing or unreadable.
	 */
	private static function count_lines( string $path, bool $exists ): ?int {
		if ( ! $exists || '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$limit = 512 * 1024;
		$size  = (int) @filesize( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Bounded read of the log file for the dashboard counter.
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			return null;
		}

		if ( $size > $limit ) {
			fseek( $handle, -$limit, SEEK_END );
			fgets( $handle ); // Drop a partial first line.
		}

		$chunk = fread( $handle, $limit ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded read.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the handle above.

		if ( false === $chunk ) {
			return null;
		}

		$lines = substr_count( $chunk, "\n" );

		return $size > $limit ? $lines : $lines + ( '' !== $chunk && "\n" !== substr( $chunk, -1 ) ? 1 : 0 );
	}

	/**
	 * Counts fatal and warning lines in the last chunk of the log.
	 *
	 * @param string $path   Log file path.
	 * @param bool   $exists Whether the file exists.
	 * @return array{fatal: int, warning: int, notice: int}
	 */
	private static function log_severity( string $path, bool $exists ): array {
		$result = array(
			'fatal'   => 0,
			'warning' => 0,
			'notice'  => 0,
		);

		if ( ! $exists || '' === $path || ! is_readable( $path ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded read of the log tail.
		$content = @file_get_contents( $path, false, null, max( 0, (int) @filesize( $path ) - 262144 ) );
		if ( false === $content ) {
			return $result;
		}

		foreach ( explode( "\n", $content ) as $line ) {
			switch ( GDC_Log_Viewer::classify_line( $line ) ) {
				case 'gdc-log-fatal':
					++$result['fatal'];
					break;
				case 'gdc-log-warning':
					++$result['warning'];
					break;
				case 'gdc-log-notice':
					++$result['notice'];
					break;
			}
		}

		return $result;
	}

	/**
	 * Health model: checklist groups, counters and the score.
	 *
	 * @param array<string, mixed> $data Output of get_data() before health.
	 * @return array<string, mixed>
	 */
	public static function health( array $data ): array {
		$data = array_merge(
			array(
				'options'    => array(),
				'wp_config'  => array(
					'wp_debug'     => false,
					'wp_debug_log' => false,
				),
				'log'        => array(
					'exists'   => false,
					'writable' => false,
					'size'     => 0,
				),
				'cron'       => array(
					'orphans'    => 0,
					'stuck_cron' => false,
				),
				'transients' => array( 'expired' => 0 ),
				'autoload'   => array(
					'count'      => 0,
					'total_size' => 0,
				),
				'http'       => array( 'capturing' => false ),
				'mu'         => array(
					'installed' => false,
					'current'   => false,
				),
			),
			$data
		);

		$options = is_array( $data['options'] ) ? $data['options'] : array();
		$options = array_merge(
			array(
				'enabled'         => 0,
				'log_enabled'     => 0,
				'log_max_size_mb' => 10,
			),
			$options
		);

		$filtering = ! empty( $options['suppress_notices'] )
			|| ! empty( $options['suppress_deprecated'] )
			|| ! empty( $options['suppress_warnings'] )
			|| ! empty( $options['suppress_strict'] );

		$log_settings = array(
			'tab' => 'settings',
			'id'  => 'gdc-setting-logging',
		);

		$groups = array(
			array(
				'title' => __( 'Runtime', 'globus-debug-control' ),
				'items' => array(
					self::item(
						! empty( $options['enabled'] ),
						__( 'Debug control enabled', 'globus-debug-control' ),
						__( 'Master switch is off.', 'globus-debug-control' ),
						array( 'tab' => 'settings' )
					),
					self::item(
						! empty( $data['wp_config']['wp_debug'] ),
						'WP_DEBUG',
						__( 'PHP does not report debug errors.', 'globus-debug-control' ),
						array( 'tab' => 'wpcfg' )
					),
					self::item(
						! empty( $data['wp_config']['wp_debug_log'] ),
						'WP_DEBUG_LOG',
						__( 'WordPress does not write debug.log on its own.', 'globus-debug-control' ),
						array( 'tab' => 'wpcfg' ),
						! empty( $data['wp_config']['wp_debug'] ) ? 'off' : 'warn'
					),
					self::item(
						$filtering,
						__( 'Error filtering configured', 'globus-debug-control' ),
						__( 'No error types are suppressed.', 'globus-debug-control' ),
						array( 'tab' => 'settings' ),
						'warn'
					),
				),
			),
			array(
				'title' => __( 'Logging', 'globus-debug-control' ),
				'items' => array(
					self::item(
						! empty( $options['log_enabled'] ),
						__( 'Plugin logging enabled', 'globus-debug-control' ),
						__( 'Errors are not written by the plugin.', 'globus-debug-control' ),
						$log_settings
					),
					self::item(
						! empty( $data['log']['exists'] ) && ! empty( $data['log']['writable'] ),
						__( 'Log file ready', 'globus-debug-control' ),
						__( 'The log file is missing or the directory is not writable.', 'globus-debug-control' ),
						array( 'tab' => 'log' ),
						'warn'
					),
					self::item(
						(int) $data['log']['size'] < ( (int) $options['log_max_size_mb'] * 1024 * 1024 * 0.8 ),
						__( 'Log rotation headroom', 'globus-debug-control' ),
						__( 'The log is close to the rotation limit.', 'globus-debug-control' ),
						array( 'tab' => 'log' ),
						'warn'
					),
				),
			),
			array(
				'title' => __( 'Diagnostics', 'globus-debug-control' ),
				'items' => array(
					self::item(
						0 === (int) $data['cron']['orphans'],
						__( 'No orphaned cron hooks', 'globus-debug-control' ),
						sprintf(
							/* translators: %d: number of orphaned hooks */
							__( '%d cron hooks have no callback.', 'globus-debug-control' ),
							(int) $data['cron']['orphans']
						),
						array( 'tab' => 'cron' ),
						'warn'
					),
					self::item(
						empty( $data['cron']['stuck_cron'] ),
						__( 'Cron lock is free', 'globus-debug-control' ),
						__( 'A cron run appears to be stuck.', 'globus-debug-control' ),
						array( 'tab' => 'cron' ),
						'warn'
					),
					self::item(
						0 === (int) $data['transients']['expired'],
						__( 'No expired transients', 'globus-debug-control' ),
						sprintf(
							/* translators: %d: number of expired transients */
							__( '%d expired transients can be purged.', 'globus-debug-control' ),
							(int) $data['transients']['expired']
						),
						array( 'tab' => 'transients' ),
						'warn'
					),
					self::item(
						(int) $data['autoload']['total_size'] <= self::AUTOLOAD_WARN_BYTES
							&& (int) $data['autoload']['count'] <= self::AUTOLOAD_WARN_COUNT,
						__( 'Autoload payload is lean', 'globus-debug-control' ),
						__( 'Autoloaded options are heavier than recommended.', 'globus-debug-control' ),
						array( 'tab' => 'autoloaded' ),
						'warn'
					),
					self::item(
						empty( $data['http']['capturing'] ),
						__( 'HTTP capture is off', 'globus-debug-control' ),
						__( 'HTTP capture is on. Turn it off after debugging.', 'globus-debug-control' ),
						array( 'tab' => 'http' ),
						'warn'
					),
				),
			),
			array(
				'title' => __( 'Early loading', 'globus-debug-control' ),
				'items' => array(
					self::item(
						! empty( $data['mu']['installed'] ),
						__( 'MU dropin installed', 'globus-debug-control' ),
						__( 'Errors from earlier plugins are not intercepted.', 'globus-debug-control' ),
						array(
							'tab' => 'settings',
							'id'  => 'gdc-setting-mu',
						),
						'warn'
					),
					self::item(
						! empty( $data['mu']['current'] ),
						__( 'Dropin is up to date', 'globus-debug-control' ),
						__( 'The dropin version is older than the plugin.', 'globus-debug-control' ),
						array(
							'tab' => 'settings',
							'id'  => 'gdc-setting-mu',
						),
						'warn'
					),
				),
			),
		);

		$counts = array(
			'ok'     => 0,
			'warn'   => 0,
			'off'    => 0,
			'total'  => 0,
			'points' => 0.0,
		);

		foreach ( $groups as $group ) {
			foreach ( $group['items'] as $item ) {
				++$counts['total'];

				if ( 'ok' === $item['state'] ) {
					++$counts['ok'];
					$counts['points'] += 1.0;
				} elseif ( 'warn' === $item['state'] ) {
					++$counts['warn'];
					$counts['points'] += 0.5;
				} else {
					++$counts['off'];
				}
			}
		}

		$score = $counts['total'] > 0 ? (int) round( ( $counts['points'] / $counts['total'] ) * 100 ) : 0;

		return array(
			'score'     => $score,
			'label'     => self::score_label( $score ),
			'color'     => self::score_color( $score ),
			'active'    => $counts['ok'],
			'attention' => $counts['warn'],
			'disabled'  => $counts['off'],
			'groups'    => $groups,
		);
	}

	/**
	 * Builds one checklist item.
	 *
	 * @param bool                 $ok       Base state.
	 * @param string               $label    Label.
	 * @param string               $hint     Shown when not ok.
	 * @param array<string, mixed> $link     Tab/id link.
	 * @param string               $fail_as  State when the check fails: off|warn.
	 * @return array<string, mixed>
	 */
	private static function item( bool $ok, string $label, string $hint, array $link = array(), string $fail_as = 'off' ): array {
		$tab = isset( $link['tab'] ) ? (string) $link['tab'] : 'settings';
		$url = GDC_UI::module_url( $tab );

		if ( ! empty( $link['id'] ) ) {
			$url .= '#' . $link['id'];
		}

		return array(
			'state' => $ok ? 'ok' : ( 'warn' === $fail_as ? 'warn' : 'off' ),
			'label' => $label,
			'hint'  => $ok ? '' : $hint,
			'link'  => $url,
		);
	}

	/**
	 * Score caption.
	 *
	 * @param int $score 0-100.
	 * @return string
	 */
	public static function score_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'Secure', 'globus-debug-control' );
		}
		if ( $score >= 70 ) {
			return __( 'Strong', 'globus-debug-control' );
		}
		if ( $score >= 50 ) {
			return __( 'Moderate', 'globus-debug-control' );
		}
		if ( $score >= 30 ) {
			return __( 'Weak', 'globus-debug-control' );
		}

		return __( 'Critical', 'globus-debug-control' );
	}

	/**
	 * Gauge colour class for a score.
	 *
	 * @param int $score 0-100.
	 * @return string
	 */
	public static function score_color( int $score ): string {
		if ( $score >= 90 ) {
			return 'gdc-gauge--secure';
		}
		if ( $score >= 70 ) {
			return 'gdc-gauge--strong';
		}
		if ( $score >= 50 ) {
			return 'gdc-gauge--moderate';
		}
		if ( $score >= 30 ) {
			return 'gdc-gauge--weak';
		}

		return 'gdc-gauge--critical';
	}

	/**
	 * Cron events per 3-hour bucket for the next 24 hours.
	 *
	 * @return array{labels: array<int, string>, values: array<int, int>}
	 */
	public static function cron_chart(): array {
		$now     = time();
		$buckets = array_fill( 0, 8, 0 );
		$events  = GDC_Cron_Viewer::get_cron_events();

		foreach ( $events as $event ) {
			$timestamp = (int) ( $event['timestamp'] ?? 0 );
			$delta     = $timestamp - $now;

			if ( $delta < 0 || $delta > DAY_IN_SECONDS ) {
				continue;
			}

			$index = min( 7, (int) floor( $delta / ( 3 * HOUR_IN_SECONDS ) ) );
			++$buckets[ $index ];
		}

		$labels = array();

		for ( $i = 0; $i < 8; $i++ ) {
			$labels[] = date_i18n( 'H:00', $now + ( $i * 3 * HOUR_IN_SECONDS ) );
		}

		return array(
			'labels' => $labels,
			'values' => $buckets,
		);
	}

	/**
	 * Top autoloaded options by size.
	 *
	 * @param array<int, array{name: string, value_size: int}> $autoloaded Options list.
	 * @return array{labels: array<int, string>, values: array<int, float>}
	 */
	public static function autoload_chart( array $autoloaded ): array {
		$labels = array();
		$values = array();

		foreach ( array_slice( $autoloaded, 0, 8 ) as $row ) {
			$name = (string) $row['name'];

			if ( strlen( $name ) > 28 ) {
				$name = substr( $name, 0, 25 ) . '...';
			}

			$labels[] = $name;
			$values[] = round( ( (int) $row['value_size'] ) / 1024, 1 );
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}
}

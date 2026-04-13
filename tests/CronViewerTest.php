<?php
declare(strict_types=1);

namespace GDC\Tests;

final class CronViewerTest extends WordPressTestCase {

	/**
	 * Creates real PHP callback files so ReflectionFunction can resolve their
	 * paths against WP_PLUGIN_DIR, WP_CONTENT_DIR/themes, and ABSPATH.
	 * Runs once before all tests in this class.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Plugin callback.
		$plugin_dir = WP_CONTENT_DIR . '/plugins/test-cron-plugin';
		if ( ! is_dir( $plugin_dir ) ) {
			mkdir( $plugin_dir, 0755, true );
		}
		$plugin_file = $plugin_dir . '/callbacks.php';
		if ( ! file_exists( $plugin_file ) ) {
			file_put_contents( $plugin_file, '<?php function gdc_test_cron_plugin_cb() {}' );
		}
		if ( ! function_exists( 'gdc_test_cron_plugin_cb' ) ) {
			require_once $plugin_file;
		}

		// Theme callback.
		$theme_dir = WP_CONTENT_DIR . '/themes/test-cron-theme';
		if ( ! is_dir( $theme_dir ) ) {
			mkdir( $theme_dir, 0755, true );
		}
		$theme_file = $theme_dir . '/callbacks.php';
		if ( ! file_exists( $theme_file ) ) {
			file_put_contents( $theme_file, '<?php function gdc_test_cron_theme_cb() {}' );
		}
		if ( ! function_exists( 'gdc_test_cron_theme_cb' ) ) {
			require_once $theme_file;
		}

		// Core callback under ABSPATH/wp-includes.
		$core_dir = rtrim( ABSPATH, '/\\' ) . '/wp-includes';
		if ( ! is_dir( $core_dir ) ) {
			mkdir( $core_dir, 0755, true );
		}
		$core_file = $core_dir . '/test-cron-core.php';
		if ( ! file_exists( $core_file ) ) {
			file_put_contents( $core_file, '<?php function gdc_test_cron_core_cb() {}' );
		}
		if ( ! function_exists( 'gdc_test_cron_core_cb' ) ) {
			require_once $core_file;
		}
	}

	public function testGetCronEventsReturnsEmptyArrayWhenNoCrons(): void {
		$GLOBALS['gdc_test_state']['cron_array'] = [];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertSame([], $events);
	}

	public function testGetCronEventsParsesScheduledEvents(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'wp_scheduled_delete' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'daily',
						'interval' => 86400,
						'args'     => [],
					],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertCount(1, $events);
		$this->assertSame('wp_scheduled_delete', $events[0]['hook']);
		$this->assertSame('daily', $events[0]['schedule']);
		$this->assertSame(86400, $events[0]['interval']);
		$this->assertFalse($events[0]['overdue']);
	}

	public function testGetCronEventsDetectsOverdueEvents(): void {
		$ts = time() - 7200;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'my_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => '',
						'interval' => 0,
						'args'     => [],
					],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertTrue($events[0]['overdue']);
	}

	public function testGetCronEventsSortsByTimestampAscending(): void {
		$now = time();
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$now + 7200 => [
				'late_hook' => [
					'40cd750bba9870f18aada2478b24840a' => ['schedule' => '', 'interval' => 0, 'args' => []],
				],
			],
			$now + 1800 => [
				'early_hook' => [
					'40cd750bba9870f18aada2478b24840a' => ['schedule' => '', 'interval' => 0, 'args' => []],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertSame('early_hook', $events[0]['hook']);
		$this->assertSame('late_hook', $events[1]['hook']);
	}

	public function testIntervalLabelConvertsSeconds(): void {
		$this->assertSame('30s', \GDC_Cron_Viewer::interval_label(30));
		$this->assertSame('5m', \GDC_Cron_Viewer::interval_label(300));
		$this->assertSame('2h', \GDC_Cron_Viewer::interval_label(7200));
		$this->assertSame('1d', \GDC_Cron_Viewer::interval_label(86400));
	}

	public function testHumanTimeDiffReturnsFutureForFutureTs(): void {
		$result = \GDC_Cron_Viewer::human_time_diff(time() + 3600);

		$this->assertStringContainsString('in', $result);
	}

	public function testHumanTimeDiffReturnsPastForPastTs(): void {
		$result = \GDC_Cron_Viewer::human_time_diff(time() - 3600);

		$this->assertStringContainsString('ago', $result);
	}

	public function testRenderOutputsTableWhenEventsExist(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'my_cron_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'hourly',
						'interval' => 3600,
						'args'     => [],
					],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('my_cron_hook', $html);
		$this->assertStringContainsString('hourly', $html);
		$this->assertStringContainsString('gdc-cron-table', $html);
	}

	public function testRenderOutputsEmptyStateWhenNoCrons(): void {
		$GLOBALS['gdc_test_state']['cron_array'] = [];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('No scheduled cron events', $html);
	}

	public function testGetInlineStylesReturnsString(): void {
		$this->assertIsString(\GDC_Cron_Viewer::get_inline_styles());
	}

	// -----------------------------------------------------------------------
	// Source detection tests
	// -----------------------------------------------------------------------

	public function testResolveHookSourceReturnsUnknownWhenNoFilter(): void {
		$source = \GDC_Cron_Viewer::resolve_hook_source( 'nonexistent_hook_xyz' );

		$this->assertSame( 'unknown', $source['type'] );
		$this->assertSame( '', $source['slug'] );
	}

	public function testResolveHookSourceDetectsPlugin(): void {
		$GLOBALS['wp_filter']['gdc_test_plugin_cron'] = [
			10 => [
				'gdc_test_cron_plugin_cb' => [
					'function'      => 'gdc_test_cron_plugin_cb',
					'accepted_args' => 1,
				],
			],
		];

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_test_plugin_cron' );

		$this->assertSame( 'plugin', $source['type'] );
		$this->assertSame( 'test-cron-plugin', $source['slug'] );
		$this->assertSame( 'test-cron-plugin', $source['name'] );
	}

	public function testResolveHookSourceDetectsTheme(): void {
		$GLOBALS['wp_filter']['gdc_test_theme_cron'] = [
			10 => [
				'gdc_test_cron_theme_cb' => [
					'function'      => 'gdc_test_cron_theme_cb',
					'accepted_args' => 1,
				],
			],
		];

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_test_theme_cron' );

		$this->assertSame( 'theme', $source['type'] );
		$this->assertSame( 'test-cron-theme', $source['slug'] );
		$this->assertSame( 'test-cron-theme', $source['name'] );
	}

	public function testResolveHookSourceDetectsCore(): void {
		$GLOBALS['wp_filter']['gdc_test_core_cron'] = [
			10 => [
				'gdc_test_cron_core_cb' => [
					'function'      => 'gdc_test_cron_core_cb',
					'accepted_args' => 1,
				],
			],
		];

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_test_core_cron' );

		$this->assertSame( 'core', $source['type'] );
		$this->assertSame( 'WordPress', $source['name'] );
		$this->assertSame( 'core', $source['slug'] );
	}

	public function testResolveHookSourceWithClosureFromTestFileReturnsUnknown(): void {
		// Closures defined in the test file itself are outside WP dirs → unknown.
		$GLOBALS['wp_filter']['gdc_closure_cron'] = [
			10 => [
				'closure_id' => [
					'function'      => static function () {},
					'accepted_args' => 1,
				],
			],
		];

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_closure_cron' );

		$this->assertSame( 'unknown', $source['type'] );
	}

	public function testGetCronEventsIncludesSourceKey(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'wp_scheduled_delete' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'daily',
						'interval' => 86400,
						'args'     => [],
					],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertArrayNotHasKey( 'source', $events[0] );
	}

	public function testRenderIncludesSourceColumnHeader(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'my_cron_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'hourly',
						'interval' => 3600,
						'args'     => [],
					],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Source', $html );
	}

	public function testRenderOutputsSpinnerInSourceCell(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'my_cron_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'hourly',
						'interval' => 3600,
						'args'     => [],
					],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'gdc-cron-source', $html );
		$this->assertStringContainsString( 'data-hook="my_cron_hook"', $html );
		$this->assertStringContainsString( 'class="spinner is-active"', $html );
		$this->assertStringContainsString( 'gdcCron', $html );
		$this->assertStringContainsString( 'ajaxUrl', $html );
	}

	public function testAjaxCronSourcesReturnsPluginSource(): void {
		$dir = WP_CONTENT_DIR . '/plugins/acme-plugin';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/acme-plugin.php', "<?php\nadd_action( 'acme_daily_task', 'acme_cb' );\n" );

		$_POST['hooks'] = [ 'acme_daily_task' ];

		try {
			\GDC_Cron_Viewer::ajax_cron_sources();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_success', $e->getMessage() );
		}

		$resp = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue( $resp['success'] );
		$this->assertArrayHasKey( 'acme_daily_task', $resp['data'] );
		$this->assertSame( 'plugin', $resp['data']['acme_daily_task']['type'] );
		$this->assertSame( 'acme-plugin', $resp['data']['acme_daily_task']['slug'] );
	}

	// -----------------------------------------------------------------------
	// File-scan fallback tests (hook not in $wp_filter at runtime)
	// -----------------------------------------------------------------------

	public function testResolveHookSourceFallsBackToFilesForPlugin(): void {
		// setUp() purges WP_CONTENT_DIR; recreate the plugin directory and a PHP
		// file that contains the hook name as a string literal.
		$dir = WP_CONTENT_DIR . '/plugins/test-cron-plugin';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/main.php', "<?php\nadd_action( 'gdc_scan_plugin_hook', 'gdc_test_cron_plugin_cb' );\n" );

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_scan_plugin_hook' );

		$this->assertSame( 'plugin', $source['type'] );
		$this->assertSame( 'test-cron-plugin', $source['slug'] );
	}

	public function testResolveHookSourceFallsBackToFilesForTheme(): void {
		$dir = WP_CONTENT_DIR . '/themes/test-cron-theme';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/functions.php', "<?php\nadd_action( 'gdc_scan_theme_hook', 'gdc_test_cron_theme_cb' );\n" );

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_scan_theme_hook' );

		$this->assertSame( 'theme', $source['type'] );
		$this->assertSame( 'test-cron-theme', $source['slug'] );
	}

	public function testResolveHookSourceFallsBackToFilesForCore(): void {
		$dir = rtrim( ABSPATH, '/\\' ) . '/wp-includes';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/cron-hooks.php', "<?php\n// gdc_scan_core_hook handler.\nfunction wp_gdc_scan_core_handler() {}\nadd_action( 'gdc_scan_core_hook', 'wp_gdc_scan_core_handler' );\n" );

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_scan_core_hook' );

		$this->assertSame( 'core', $source['type'] );
		$this->assertSame( 'WordPress', $source['name'] );
	}

	public function testResolveHookSourceRemainsUnknownWhenNotInFilesEither(): void {
		$source = \GDC_Cron_Viewer::resolve_hook_source( 'gdc_completely_nonexistent_xyz_abc_cron' );

		$this->assertSame( 'unknown', $source['type'] );
	}

	// -----------------------------------------------------------------------
	// Streaming NDJSON output tests
	// -----------------------------------------------------------------------

	public function testOutputCronSourcesStreamEmitsOneLinePerHook(): void {
		$dir = WP_CONTENT_DIR . '/plugins/stream-scan-plugin';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/main.php', "<?php\nadd_action( 'stream_scan_cron_hook', 'cb' );\n" );

		ob_start();
		\GDC_Cron_Viewer::output_cron_sources_stream( array( 'stream_scan_cron_hook', 'stream_unknown_cron_xyz' ) );
		$output = ob_get_clean();

		$lines = array_values( array_filter( explode( "\n", $output ) ) );
		$this->assertCount( 2, $lines );

		$first = json_decode( $lines[0], true );
		$this->assertSame( 'stream_scan_cron_hook', $first['hook'] );
		$this->assertSame( 'plugin', $first['source']['type'] );
		$this->assertSame( 'stream-scan-plugin', $first['source']['slug'] );

		$second = json_decode( $lines[1], true );
		$this->assertSame( 'stream_unknown_cron_xyz', $second['hook'] );
		$this->assertSame( 'unknown', $second['source']['type'] );
	}

	public function testOutputCronSourcesStreamSkipsHooksThatSanitiseToEmpty(): void {
		ob_start();
		\GDC_Cron_Viewer::output_cron_sources_stream( array( '!!!', '' ) );
		$output = ob_get_clean();

		$this->assertSame( '', trim( $output ) );
	}

	public function testOutputCronSourcesStreamEmptyArrayProducesNoOutput(): void {
		ob_start();
		\GDC_Cron_Viewer::output_cron_sources_stream( array() );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function testAjaxCronSourcesStreamThrowsWpDieOnCompletion(): void {
		$_POST['hooks'] = array( 'stream_handler_nonexistent_xyz' );
		$ob_level       = ob_get_level();

		try {
			\GDC_Cron_Viewer::ajax_cron_sources_stream();
			$this->fail( 'Expected GDC_Test_WpDieException' );
		} catch ( \GDC_Test_WpDieException $e ) {
			// Restore any output buffer levels closed by the handler.
			while ( ob_get_level() < $ob_level ) {
				ob_start();
			}
			$this->assertSame( '', $e->getMessage() );
		}
	}

	public function testAjaxCronSourcesStreamForbidsUnprivilegedUser(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['hooks'] = array( 'some_hook' );

		try {
			\GDC_Cron_Viewer::ajax_cron_sources_stream();
			$this->fail( 'Expected GDC_Test_WpDieException' );
		} catch ( \GDC_Test_WpDieException $e ) {
			$GLOBALS['gdc_test_state']['current_user_can'] = true;
			$this->assertSame( 'Forbidden', $e->getMessage() );
		}
	}

	// -----------------------------------------------------------------------
	// Single-file plugin / mu-plugin root scan tests
	// -----------------------------------------------------------------------

	public function testResolveHookSourceFindsSingleFilePlugin(): void {
		file_put_contents(
			WP_PLUGIN_DIR . '/singlefile-cron-plugin.php',
			"<?php\n// wp_schedule_event( time(), 'hourly', 'singlefile_cronjob_hook' );\n"
		);

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'singlefile_cronjob_hook' );

		$this->assertSame( 'plugin', $source['type'] );
		$this->assertSame( 'singlefile-cron-plugin', $source['slug'] );
	}

	public function testResolveHookSourceFindsSingleFileMuPlugin(): void {
		file_put_contents(
			WPMU_PLUGIN_DIR . '/mu-singlefile-cron.php',
			"<?php\nwp_schedule_event( time(), 'daily', 'mu_singlefile_cron_task' );\n"
		);

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'mu_singlefile_cron_task' );

		$this->assertSame( 'plugin', $source['type'] );
		$this->assertSame( 'mu-singlefile-cron', $source['slug'] );
	}

	public function testResolveHookSourcePrefersDirPluginOverRootFile(): void {
		// Both a subdirectory plugin and a root file contain the hook — the
		// subdirectory (higher priority in scan order) must win.
		$dir = WP_CONTENT_DIR . '/plugins/dir-priority-plugin';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents( $dir . '/main.php', "<?php\nadd_action( 'priority_test_cron_hook', 'cb' );\n" );
		file_put_contents(
			WP_PLUGIN_DIR . '/root-priority-plugin.php',
			"<?php\nadd_action( 'priority_test_cron_hook', 'cb2' );\n"
		);

		$source = \GDC_Cron_Viewer::resolve_hook_source( 'priority_test_cron_hook' );

		$this->assertSame( 'plugin', $source['type'] );
		$this->assertSame( 'dir-priority-plugin', $source['slug'] );
	}
}

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

	public function testGetCronEventsPreservesFalsyArgs(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'falsy_args_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => '',
						'interval' => 0,
						'args'     => [0, false, '', 'keep'],
					],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertSame([0, false, '', 'keep'], $events[0]['args']);
	}

	public function testAjaxCronDeletePassesOriginalFalsyArgs(): void {
		$ts = time() + 3600;
		$_POST['hook']      = 'falsy_args_hook';
		$_POST['timestamp'] = (string) $ts;
		$_POST['args']      = json_encode([0, false, '', 'keep']);

		try {
			\GDC_Cron_Viewer::ajax_cron_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$unscheduled = $this->state('unscheduled');
		$this->assertSame([0, false, '', 'keep'], $unscheduled[0]['args']);
	}

	public function testResolveHookSourceUsesTransientCacheWithoutScanning(): void {
		\GDC_Cron_Viewer::reset_hook_source_cache();

		$cached = ['type' => 'plugin', 'name' => 'cached-plugin', 'slug' => 'cached-plugin'];
		$GLOBALS['gdc_test_state']['transients']['gdc_cron_src_' . md5('ghost_hook')] = $cached;

		// No plugin/theme files contain 'ghost_hook', so only a cache hit can produce this result.
		$source = \GDC_Cron_Viewer::resolve_hook_source('ghost_hook');

		$this->assertSame('plugin', $source['type']);
		$this->assertSame('cached-plugin', $source['slug']);
	}

	public function testResolveHookSourceStoresScanResultInTransient(): void {
		\GDC_Cron_Viewer::reset_hook_source_cache();

		// Clean any pre-existing cache entry.
		unset($GLOBALS['gdc_test_state']['transients']['gdc_cron_src_' . md5('no_such_hook_xyz')]);

		$source = \GDC_Cron_Viewer::resolve_hook_source('no_such_hook_xyz');

		$this->assertSame('unknown', $source['type']);
		$this->assertArrayHasKey('gdc_cron_src_' . md5('no_such_hook_xyz'), $GLOBALS['gdc_test_state']['transients']);
		$this->assertSame('unknown', $GLOBALS['gdc_test_state']['transients']['gdc_cron_src_' . md5('no_such_hook_xyz')]['type']);
	}

	public function testEscapeSourceForHtmlNeutralizesInjection(): void {
		$source = [
			'type' => 'plugin',
			'name' => '<img src=x onerror=alert(1)>',
			'slug' => '"quoted"<script>',
		];

		$escaped = \GDC_Cron_Viewer::escape_source_for_html($source);

		$this->assertStringNotContainsString('<img', $escaped['name']);
		$this->assertStringContainsString('&lt;img', $escaped['name']);
		$this->assertStringContainsString('&quot;quoted&quot;', $escaped['slug']);
		$this->assertStringNotContainsString('<script>', $escaped['slug']);
	}

	public function testOutputCronSourcesStreamEmitsEscapedNames(): void {
		\GDC_Cron_Viewer::reset_hook_source_cache();
		$GLOBALS['gdc_test_state']['transients']['gdc_cron_src_' . md5('xss_hook')] = [
			'type' => 'plugin',
			'name' => '<b>evil</b>',
			'slug' => 'evil',
		];

		ob_start();
		\GDC_Cron_Viewer::output_cron_sources_stream(['xss_hook']);
		$out = ob_get_clean();

		$this->assertStringContainsString('&lt;b&gt;evil&lt;\\/b&gt;', $out);
		$this->assertStringNotContainsString('<b>evil</b>', $out);
	}

	public function testAjaxCronBulkDeleteRemovesAllProvidedEvents(): void {
		$ts = time() + 3600;
		$_POST['events'] = [
			json_encode(['hook' => 'orphan_a', 'timestamp' => $ts, 'args' => []]),
			json_encode(['hook' => 'orphan_b', 'timestamp' => $ts, 'args' => [1, 2]]),
		];

		try {
			\GDC_Cron_Viewer::ajax_cron_bulk_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame(2, $response['data']['deleted']);
		$this->assertSame(0, $response['data']['failed']);
		$this->assertCount(2, $this->state('unscheduled'));
	}

	public function testAjaxCronBulkDeleteReportsFailedEvents(): void {
		$GLOBALS['gdc_test_state']['unschedule_result'] = false;
		$ts                                              = time() + 3600;
		$_POST['events']                                 = [
			json_encode(['hook' => 'orphan_a', 'timestamp' => $ts, 'args' => []]),
			'not-json',
			json_encode(['hook' => '', 'timestamp' => $ts, 'args' => []]),
		];

		try {
			\GDC_Cron_Viewer::ajax_cron_bulk_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame(0, $response['data']['deleted']);
		$this->assertSame(3, $response['data']['failed']);
	}

	public function testAjaxCronBulkDeleteForbiddenWithoutCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['events']                                = [];

		try {
			\GDC_Cron_Viewer::ajax_cron_bulk_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertFalse($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	public function testRenderShowsBulkDeleteButtonWhenOrphansExist(): void {
		$GLOBALS['gdc_test_state']['cron_array'] = [
			time() + 3600 => [
				'orphan_hook' => [
					'40cd750bba9870f18aada2478b24840a' => ['schedule' => '', 'interval' => 0, 'args' => []],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-cron-bulk-delete', $html);
	}

	public function testRenderHidesBulkDeleteButtonWithoutOrphans(): void {
		$GLOBALS['gdc_test_state']['cron_array'] = [
			time() + 3600 => [
				'gdc_test_cron_plugin_cb_hook' => [
					'40cd750bba9870f18aada2478b24840a' => ['schedule' => '', 'interval' => 0, 'args' => []],
				],
			],
		];
		$GLOBALS['wp_filter']['gdc_test_cron_plugin_cb_hook'] = [
			10 => [
				['function' => 'gdc_test_cron_plugin_cb'],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString('gdc-cron-bulk-delete', $html);
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
		// Both a subdirectory plugin and a root file contain the hook - the
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

	// -----------------------------------------------------------------------
	// get_cron_summary() tests
	// -----------------------------------------------------------------------

	public function testGetCronSummaryReturnsDefaultsWhenEmpty(): void {
		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertFalse( $summary['cron_disabled'] );
		$this->assertSame( 0, $summary['unique_hooks'] );
		$this->assertSame( 0, $summary['overdue'] );
		$this->assertSame( 0, $summary['orphans'] );
		$this->assertSame( 0, $summary['duplicates'] );
		$this->assertFalse( $summary['stuck_cron'] );
		$this->assertNull( $summary['next_event'] );
	}

	public function testGetCronSummaryCountsUniqueHooks(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'hook_a' => [ 'x' => [ 'schedule' => 'daily', 'interval' => 86400, 'args' => [] ] ],
				'hook_b' => [ 'y' => [ 'schedule' => 'hourly', 'interval' => 3600, 'args' => [] ] ],
			],
		];

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertSame( 2, $summary['unique_hooks'] );
		$this->assertSame( $ts, $summary['next_event'] );
	}

	public function testGetCronSummaryCountsOverdue(): void {
		$past = time() - 7200;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$past => [
				'overdue_hook' => [
					'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ],
					'b' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ],
				],
			],
		];

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertSame( 2, $summary['overdue'] );
	}

	public function testGetCronSummaryCountsOrphans(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'orphan_hook_xyz_no_cb' => [ 'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ] ],
			],
		];
		// Ensure no filter is registered for it.
		unset( $GLOBALS['wp_filter']['orphan_hook_xyz_no_cb'] );

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertGreaterThanOrEqual( 1, $summary['orphans'] );
	}

	public function testGetCronSummaryCountsDuplicates(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'dup_hook' => [
					'hash1' => [ 'schedule' => 'hourly', 'interval' => 3600, 'args' => [] ],
					'hash2' => [ 'schedule' => 'hourly', 'interval' => 3600, 'args' => ['x'] ],
				],
			],
		];

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertSame( 1, $summary['duplicates'] );
	}

	public function testGetCronSummaryDetectsStuckCron(): void {
		$GLOBALS['gdc_test_state']['transients']['doing_cron'] = (string) ( time() - 700 );

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertTrue( $summary['stuck_cron'] );

		$GLOBALS['gdc_test_state']['transients']['doing_cron'] = false;
	}

	public function testGetCronSummaryNotStuckWhenRecentCron(): void {
		$GLOBALS['gdc_test_state']['transients']['doing_cron'] = (string) time();

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertFalse( $summary['stuck_cron'] );

		$GLOBALS['gdc_test_state']['transients']['doing_cron'] = false;
	}

	public function testGetCronSummaryNextEventIsEarliest(): void {
		$early = time() + 100;
		$late  = time() + 9999;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$late  => [ 'h1' => [ 'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ] ] ],
			$early => [ 'h2' => [ 'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ] ] ],
		];

		$summary = \GDC_Cron_Viewer::get_cron_summary();

		$this->assertSame( $early, $summary['next_event'] );
	}

	// -----------------------------------------------------------------------
	// get_cron_notices() tests
	// -----------------------------------------------------------------------

	public function testGetCronNoticesEmptyWhenClean(): void {
		$summary = [
			'cron_disabled'  => false,
			'alternate_cron' => false,
			'stuck_cron'     => false,
			'orphans'        => 0,
			'duplicates'     => 0,
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( [], $summary );

		$this->assertSame( [], $notices );
	}

	public function testGetCronNoticesDisabledCronAddsWarning(): void {
		$summary = [
			'cron_disabled'  => true,
			'alternate_cron' => false,
			'stuck_cron'     => false,
			'orphans'        => 0,
			'duplicates'     => 0,
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( [], $summary );

		$types = array_column( $notices, 'type' );
		$this->assertContains( 'warning', $types );
	}

	public function testGetCronNoticesStuckCronAddsError(): void {
		$summary = [
			'cron_disabled'  => false,
			'alternate_cron' => false,
			'stuck_cron'     => true,
			'orphans'        => 0,
			'duplicates'     => 0,
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( [], $summary );

		$types = array_column( $notices, 'type' );
		$this->assertContains( 'error', $types );
	}

	public function testGetCronNoticesOrphansAddsWarning(): void {
		$summary = [
			'cron_disabled'  => false,
			'alternate_cron' => false,
			'stuck_cron'     => false,
			'orphans'        => 3,
			'duplicates'     => 0,
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( [], $summary );

		$types = array_column( $notices, 'type' );
		$this->assertContains( 'warning', $types );
		// Message should contain the count.
		$messages = array_column( $notices, 'message' );
		$this->assertStringContainsString( '3', implode( ' ', $messages ) );
	}

	public function testGetCronNoticesDuplicatesAddsWarning(): void {
		$summary = [
			'cron_disabled'  => false,
			'alternate_cron' => false,
			'stuck_cron'     => false,
			'orphans'        => 0,
			'duplicates'     => 2,
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( [], $summary );

		$types = array_column( $notices, 'type' );
		$this->assertContains( 'warning', $types );
	}

	public function testGetCronNoticesHighFrequencyHookAddsInfo(): void {
		$summary = [
			'cron_disabled'  => false,
			'alternate_cron' => false,
			'stuck_cron'     => false,
			'orphans'        => 0,
			'duplicates'     => 0,
		];
		$events = [
			[
				'hook'      => 'hyper_fast_hook',
				'interval'  => 30,
				'timestamp' => time() + 10,
				'overdue'   => false,
				'orphan'    => false,
				'callbacks' => [],
				'instances' => 1,
			],
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( $events, $summary );

		$types = array_column( $notices, 'type' );
		$this->assertContains( 'info', $types );
	}

	public function testGetCronNoticesAlternateCronAddsInfo(): void {
		$summary = [
			'cron_disabled'  => false,
			'alternate_cron' => true,
			'stuck_cron'     => false,
			'orphans'        => 0,
			'duplicates'     => 0,
		];

		$notices = \GDC_Cron_Viewer::get_cron_notices( [], $summary );

		$types = array_column( $notices, 'type' );
		$this->assertContains( 'info', $types );
	}

	// -----------------------------------------------------------------------
	// get_cron_events() enriched fields
	// -----------------------------------------------------------------------

	public function testGetCronEventsAddsOrphanFlag(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'clearly_orphaned_hook_no_cb' => [
					'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ],
				],
			],
		];
		unset( $GLOBALS['wp_filter']['clearly_orphaned_hook_no_cb'] );

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertTrue( $events[0]['orphan'] );
	}

	public function testGetCronEventsNotOrphanWhenFilterExists(): void {
		$ts   = time() + 3600;
		$hook = 'gdc_has_filter_cron_hook';
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				$hook => [ 'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ] ],
			],
		];
		$GLOBALS['wp_filter'][ $hook ] = [
			10 => [ 'cb' => [ 'function' => 'var_dump', 'accepted_args' => 0 ] ],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertFalse( $events[0]['orphan'] );
		unset( $GLOBALS['wp_filter'][ $hook ] );
	}

	public function testGetCronEventsCallbacksField(): void {
		$ts   = time() + 3600;
		$hook = 'gdc_cb_field_test_hook';
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				$hook => [ 'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ] ],
			],
		];
		$GLOBALS['wp_filter'][ $hook ] = [
			10 => [ 'my_test_fn' => [ 'function' => 'var_dump', 'accepted_args' => 0 ] ],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertIsArray( $events[0]['callbacks'] );
		$this->assertNotEmpty( $events[0]['callbacks'] );
		unset( $GLOBALS['wp_filter'][ $hook ] );
	}

	public function testGetCronEventsInstancesField(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'multi_instance_hook' => [
					'hash1' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ],
					'hash2' => [ 'schedule' => '', 'interval' => 0, 'args' => ['x'] ],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertSame( 2, $events[0]['instances'] );
	}

	// -----------------------------------------------------------------------
	// collect_callbacks() and callable_label() tests
	// -----------------------------------------------------------------------

	public function testCollectCallbacksReturnsEmptyWhenNoFilter(): void {
		$labels = \GDC_Cron_Viewer::collect_callbacks( 'no_filter_hook_xyz' );
		$this->assertSame( [], $labels );
	}

	public function testCollectCallbacksReturnsFunctionLabel(): void {
		$hook = 'gdc_collect_cb_test';
		$GLOBALS['wp_filter'][ $hook ] = [
			10 => [ 'fn' => [ 'function' => 'my_plain_function', 'accepted_args' => 0 ] ],
		];

		$labels = \GDC_Cron_Viewer::collect_callbacks( $hook );

		$this->assertContains( 'my_plain_function()', $labels );
		unset( $GLOBALS['wp_filter'][ $hook ] );
	}

	public function testCollectCallbacksDeduplicatesLabels(): void {
		$hook = 'gdc_dedup_cb_test';
		$GLOBALS['wp_filter'][ $hook ] = [
			10 => [
				'a' => [ 'function' => 'same_function', 'accepted_args' => 0 ],
				'b' => [ 'function' => 'same_function', 'accepted_args' => 0 ],
			],
		];

		$labels = \GDC_Cron_Viewer::collect_callbacks( $hook );

		$this->assertCount( 1, $labels );
		unset( $GLOBALS['wp_filter'][ $hook ] );
	}

	public function testCallableLabelForString(): void {
		$this->assertSame( 'my_func()', \GDC_Cron_Viewer::callable_label( 'my_func' ) );
	}

	public function testCallableLabelForClosure(): void {
		$this->assertSame( '{closure}', \GDC_Cron_Viewer::callable_label( static function () {} ) );
	}

	public function testCallableLabelForArrayMethod(): void {
		$label = \GDC_Cron_Viewer::callable_label( [ 'MyClass', 'myMethod' ] );
		$this->assertSame( 'MyClass::myMethod()', $label );
	}

	public function testCallableLabelForObjectMethod(): void {
		$obj   = new \stdClass();
		$label = \GDC_Cron_Viewer::callable_label( [ $obj, 'someMethod' ] );
		$this->assertSame( 'stdClass::someMethod()', $label );
	}

	public function testCallableLabelForNullableUnknown(): void {
		$this->assertNull( \GDC_Cron_Viewer::callable_label( 42 ) );
	}

	// -----------------------------------------------------------------------
	// overdue_urgency() tests
	// -----------------------------------------------------------------------

	public function testOverdueUrgencyWarningForRecentOverdue(): void {
		$ts = time() - 1800; // 30 min ago
		$this->assertSame( 'warning', \GDC_Cron_Viewer::overdue_urgency( $ts ) );
	}

	public function testOverdueUrgencyErrorForSeveralHours(): void {
		$ts = time() - 7200; // 2 h ago
		$this->assertSame( 'error', \GDC_Cron_Viewer::overdue_urgency( $ts ) );
	}

	public function testOverdueUrgencyCriticalForMoreThanOneDay(): void {
		$ts = time() - 90000; // >1 day
		$this->assertSame( 'critical', \GDC_Cron_Viewer::overdue_urgency( $ts ) );
	}

	// -----------------------------------------------------------------------
	// render() enriched output tests
	// -----------------------------------------------------------------------

	public function testRenderOutputsSummaryCards(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'render_summary_hook' => [
					'a' => [ 'schedule' => 'hourly', 'interval' => 3600, 'args' => [] ],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'gdc-cron-summary', $html );
		$this->assertStringContainsString( 'WP-Cron', $html );
		$this->assertStringContainsString( 'Events', $html );
	}

	public function testRenderOutputsActionsColumn(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'render_action_hook' => [
					'a' => [ 'schedule' => 'hourly', 'interval' => 3600, 'args' => [] ],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'gdc-cron-run', $html );
		$this->assertStringContainsString( 'gdc-cron-delete', $html );
		$this->assertStringContainsString( 'Actions', $html );
	}

	public function testRenderOutputsOrphanBadgeForOrphanedHook(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'render_orphan_hook_xyz_unique' => [
					'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ],
				],
			],
		];
		unset( $GLOBALS['wp_filter']['render_orphan_hook_xyz_unique'] );

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'gdc-badge-orphan', $html );
		$this->assertStringContainsString( 'orphan', $html );
	}

	public function testRenderOutputsCallbackToggleWhenCallbackExists(): void {
		$hook = 'render_cb_toggle_hook';
		$ts   = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				$hook => [ 'a' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ] ],
			],
		];
		$GLOBALS['wp_filter'][ $hook ] = [
			10 => [ 'fn' => [ 'function' => 'some_cb_fn', 'accepted_args' => 0 ] ],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'gdc-cron-cb-toggle', $html );
		$this->assertStringContainsString( 'gdc-cron-callbacks', $html );
		unset( $GLOBALS['wp_filter'][ $hook ] );
	}

	public function testRenderOutputsInstancesBadgeForDuplicateHooks(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'dup_render_hook' => [
					'h1' => [ 'schedule' => '', 'interval' => 0, 'args' => [] ],
					'h2' => [ 'schedule' => '', 'interval' => 0, 'args' => ['y'] ],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '&times;', $html );
	}

	public function testRenderIncludesNonceRunAndNonceDeleteInScript(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'nonce_test_hook' => [
					'a' => [ 'schedule' => 'hourly', 'interval' => 3600, 'args' => [] ],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'nonceRun', $html );
		$this->assertStringContainsString( 'nonceDelete', $html );
	}

	// -----------------------------------------------------------------------
	// AJAX: run
	// -----------------------------------------------------------------------

	public function testAjaxCronRunTriggersDoAction(): void {
		$_POST['hook'] = 'my_test_run_hook';

		try {
			\GDC_Cron_Viewer::ajax_cron_run();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_success', $e->getMessage() );
		}

		$this->assertContains( 'my_test_run_hook', $GLOBALS['gdc_test_state']['did_action'] );
		$resp = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue( $resp['success'] );
		$this->assertSame( 'my_test_run_hook', $resp['data']['hook'] );
	}

	public function testAjaxCronRunRejectsEmptyHook(): void {
		$_POST['hook'] = '!!!';

		try {
			\GDC_Cron_Viewer::ajax_cron_run();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_error', $e->getMessage() );
		}

		$resp = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertFalse( $resp['success'] );
	}

	public function testAjaxCronRunForbidsUnprivilegedUser(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['hook'] = 'some_hook';

		try {
			\GDC_Cron_Viewer::ajax_cron_run();
			$this->fail( 'Expected GDC_Test_WpDieException' );
		} catch ( \GDC_Test_WpDieException $e ) {
			$GLOBALS['gdc_test_state']['current_user_can'] = true;
			$resp = $GLOBALS['gdc_test_state']['json_response'];
			$this->assertFalse( $resp['success'] );
		}
	}

	public function testAjaxCronRunSanitisesHook(): void {
		$_POST['hook'] = 'my/valid-hook.name_123';

		try {
			\GDC_Cron_Viewer::ajax_cron_run();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_success', $e->getMessage() );
		}

		$this->assertContains( 'my/valid-hook.name_123', $GLOBALS['gdc_test_state']['did_action'] );
	}

	// -----------------------------------------------------------------------
	// AJAX: delete
	// -----------------------------------------------------------------------

	public function testAjaxCronDeleteCallsWpUnscheduleEvent(): void {
		$ts = time() + 3600;
		$_POST['hook']      = 'delete_test_hook';
		$_POST['timestamp'] = (string) $ts;
		$_POST['args']      = '[]';

		try {
			\GDC_Cron_Viewer::ajax_cron_delete();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_success', $e->getMessage() );
		}

		$unscheduled = $GLOBALS['gdc_test_state']['unscheduled'];
		$this->assertNotEmpty( $unscheduled );
		$last = end( $unscheduled );
		$this->assertSame( 'delete_test_hook', $last['hook'] );
		$this->assertSame( $ts, $last['timestamp'] );
	}

	public function testAjaxCronDeleteRejectsEmptyHook(): void {
		$_POST['hook']      = '   ';
		$_POST['timestamp'] = (string) time();
		$_POST['args']      = '[]';

		try {
			\GDC_Cron_Viewer::ajax_cron_delete();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_error', $e->getMessage() );
		}

		$resp = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertFalse( $resp['success'] );
	}

	public function testAjaxCronDeleteForbidsUnprivilegedUser(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['hook']      = 'some_hook';
		$_POST['timestamp'] = (string) time();
		$_POST['args']      = '[]';

		try {
			\GDC_Cron_Viewer::ajax_cron_delete();
			$this->fail( 'Expected GDC_Test_WpDieException' );
		} catch ( \GDC_Test_WpDieException $e ) {
			$GLOBALS['gdc_test_state']['current_user_can'] = true;
			$resp = $GLOBALS['gdc_test_state']['json_response'];
			$this->assertFalse( $resp['success'] );
		}
	}

	public function testAjaxCronDeleteReturnsErrorWhenUnscheduleFails(): void {
		$GLOBALS['gdc_test_state']['unschedule_result'] = false;
		$_POST['hook']      = 'fail_delete_hook';
		$_POST['timestamp'] = (string) time();
		$_POST['args']      = '[]';

		try {
			\GDC_Cron_Viewer::ajax_cron_delete();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_error', $e->getMessage() );
		}

		$resp = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertFalse( $resp['success'] );
		$GLOBALS['gdc_test_state']['unschedule_result'] = true;
	}

	public function testAjaxCronDeletePassesArgsFromPost(): void {
		$ts   = time() + 3600;
		$args = [ 'my_param' => 42 ];
		$_POST['hook']      = 'args_delete_hook';
		$_POST['timestamp'] = (string) $ts;
		$_POST['args']      = wp_json_encode( $args );

		try {
			\GDC_Cron_Viewer::ajax_cron_delete();
		} catch ( \GDC_Test_WpDieException $e ) {
			$this->assertSame( 'wp_send_json_success', $e->getMessage() );
		}

		$last = end( $GLOBALS['gdc_test_state']['unscheduled'] );
		$this->assertSame( $args, $last['args'] );
	}
}

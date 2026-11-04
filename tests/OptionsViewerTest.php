<?php
declare(strict_types=1);

namespace GDC\Tests;

final class OptionsViewerTest extends WordPressTestCase {

	// -------------------------------------------------------------------------
	// get_autoloaded()
	// -------------------------------------------------------------------------

	public function testGetAutoloadedReturnsEmptyWhenNoResults(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];

		$this->assertSame([], \GDC_Options_Viewer::get_autoloaded());
	}

	public function testGetAutoloadedReturnsCorrectShape(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => 'siteurl',   'value_size' => '30'],
			['option_name' => 'some_plugin_data', 'value_size' => '4096'],
		];

		$result = \GDC_Options_Viewer::get_autoloaded();

		$this->assertCount(2, $result);
		$this->assertSame('siteurl', $result[0]['name']);
		$this->assertSame(30, $result[0]['value_size']);
		$this->assertSame('some_plugin_data', $result[1]['name']);
		$this->assertSame(4096, $result[1]['value_size']);
	}

	public function testGetAutoloadedCastsValueSizeToInt(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => 'foo', 'value_size' => '12345'],
		];

		$result = \GDC_Options_Viewer::get_autoloaded();

		$this->assertIsInt($result[0]['value_size']);
		$this->assertSame(12345, $result[0]['value_size']);
	}

	// -------------------------------------------------------------------------
	// get_stats()
	// -------------------------------------------------------------------------

	public function testGetStatsEmptyArray(): void {
		$stats = \GDC_Options_Viewer::get_stats([]);

		$this->assertSame(0, $stats['count']);
		$this->assertSame(0, $stats['total_size']);
	}

	public function testGetStatsCorrectCountAndSize(): void {
		$options = [
			['name' => 'a', 'value_size' => 1000],
			['name' => 'b', 'value_size' => 2000],
			['name' => 'c', 'value_size' => 3000],
		];

		$stats = \GDC_Options_Viewer::get_stats($options);

		$this->assertSame(3, $stats['count']);
		$this->assertSame(6000, $stats['total_size']);
	}

	// -------------------------------------------------------------------------
	// render()
	// -------------------------------------------------------------------------

	public function testRenderOutputsEmptyStateWhenNoOptions(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];

		ob_start();
		\GDC_Options_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-empty-state', $html);
		$this->assertStringNotContainsString('gdc-options-table', $html);
	}

	public function testRenderOutputsTableWhenOptionsExist(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => 'siteurl',       'value_size' => '30'],
			['option_name' => 'active_plugins', 'value_size' => '2048'],
		];

		ob_start();
		\GDC_Options_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-options-table', $html);
		$this->assertStringContainsString('siteurl', $html);
		$this->assertStringContainsString('active_plugins', $html);
	}

	public function testRenderShowsWarningCardWhenCountExceedsThreshold(): void {
		// Build 101 rows to exceed the WARN_COUNT threshold of 100.
		$rows = [];
		for ($i = 0; $i < 101; $i++) {
			$rows[] = ['option_name' => 'opt_' . $i, 'value_size' => '100'];
		}
		$GLOBALS['gdc_test_state']['wpdb_results'] = $rows;

		ob_start();
		\GDC_Options_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-card-warning', $html);
	}

	public function testRenderShowsOkCardWhenCountBelowThreshold(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => 'siteurl', 'value_size' => '30'],
		];

		ob_start();
		\GDC_Options_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-card-ok', $html);
	}

	public function testRenderOutputsSizeBars(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => 'big_option', 'value_size' => '150000'],
		];

		ob_start();
		\GDC_Options_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-size-bar', $html);
		$this->assertStringContainsString('gdc-bar-danger', $html);
	}

	public function testRenderOutputsSummaryCards(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];

		ob_start();
		\GDC_Options_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-cron-summary', $html);
		$this->assertStringContainsString('Autoloaded rows', $html);
	}
}

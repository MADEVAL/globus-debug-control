<?php
declare(strict_types=1);

namespace GDC\Tests;

final class TransientViewerTest extends WordPressTestCase {

	// -------------------------------------------------------------------------
	// get_transients()
	// -------------------------------------------------------------------------

	public function testGetTransientsReturnsEmptyArrayWhenNoResults(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];

		$this->assertSame([], \GDC_Transient_Viewer::get_transients());
	}

	public function testGetTransientsReturnsCorrectFields(): void {
		$now = time();
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			[
				'option_name' => '_transient_my_key',
				'value_size'  => '512',
				'timeout'     => (string) ($now + 3600),
			],
		];

		$result = \GDC_Transient_Viewer::get_transients();

		$this->assertCount(1, $result);
		$this->assertSame('my_key', $result[0]['key']);
		$this->assertSame(512, $result[0]['value_size']);
		$this->assertFalse($result[0]['is_expired']);
		$this->assertIsInt($result[0]['expires_in']);
		$this->assertGreaterThan(0, $result[0]['expires_in']);
	}

	public function testGetTransientsDetectsExpiredTransient(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			[
				'option_name' => '_transient_old_key',
				'value_size'  => '128',
				'timeout'     => '1000000', // far in the past
			],
		];

		$result = \GDC_Transient_Viewer::get_transients();

		$this->assertTrue($result[0]['is_expired']);
		$this->assertLessThan(0, $result[0]['expires_in']);
	}

	public function testGetTransientsNullExpiresInWhenNoTimeout(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			[
				'option_name' => '_transient_permanent_key',
				'value_size'  => '64',
				'timeout'     => '0',
			],
		];

		$result = \GDC_Transient_Viewer::get_transients();

		$this->assertNull($result[0]['expires_in']);
		$this->assertFalse($result[0]['is_expired']);
	}

	public function testGetTransientsHandlesMultipleRows(): void {
		$now = time();
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => '_transient_a', 'value_size' => '100', 'timeout' => (string) ($now + 1000)],
			['option_name' => '_transient_b', 'value_size' => '200', 'timeout' => '0'],
			['option_name' => '_transient_c', 'value_size' => '300', 'timeout' => '1000'],
		];

		$result = \GDC_Transient_Viewer::get_transients();

		$this->assertCount(3, $result);
		$this->assertSame('a', $result[0]['key']);
		$this->assertSame('b', $result[1]['key']);
		$this->assertSame('c', $result[2]['key']);
		$this->assertFalse($result[0]['is_expired']);
		$this->assertNull($result[1]['expires_in']);
		$this->assertTrue($result[2]['is_expired']);
	}

	// -------------------------------------------------------------------------
	// get_summary()
	// -------------------------------------------------------------------------

	public function testGetTransientsIncludesSiteTransientsOnMultisite(): void {
		$GLOBALS['gdc_test_state']['is_multisite']       = true;
		$GLOBALS['gdc_test_state']['wpdb_results_queue'] = [
			// First query: options table (regular transients).
			[
				[
					'option_name' => '_transient_my_key',
					'value_size'  => '512',
					'timeout'     => (string) (time() + 3600),
				],
			],
			// Second query: sitemeta table (site transients).
			[
				[
					'option_name' => '_site_transient_update_plugins',
					'value_size'  => '2048',
					'timeout'     => (string) (time() - 60),
				],
			],
		];

		$result = \GDC_Transient_Viewer::get_transients();

		$this->assertCount(2, $result);
		$keys = array_column($result, 'key');
		$this->assertContains('my_key', $keys);
		$this->assertContains('_site_transient_update_plugins', $keys);
	}

	public function testGetTransientsSkipsSiteTransientsOnSingleSite(): void {
		$GLOBALS['gdc_test_state']['is_multisite'] = false;
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			[
				'option_name' => '_transient_my_key',
				'value_size'  => '512',
				'timeout'     => (string) (time() + 3600),
			],
		];

		$result = \GDC_Transient_Viewer::get_transients();

		$this->assertCount(1, $result);
		$this->assertSame('my_key', $result[0]['key']);
	}

	public function testAjaxDeleteUsesDeleteSiteTransientForSiteKeys(): void {
		$_POST['key']         = '_site_transient_update_plugins';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertTrue($GLOBALS['gdc_test_state']['json_response']['success']);
		$this->assertContains('update_plugins', $this->state('deleted_site_transients'));
	}

	public function testAjaxDeleteUsesDeleteTransientForRegularKeys(): void {
		$GLOBALS['gdc_test_state']['transients']['my_key'] = 'value';
		$_POST['key']         = 'my_key';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertArrayNotHasKey('my_key', $GLOBALS['gdc_test_state']['transients']);
		$this->assertEmpty($this->state('deleted_site_transients'));
	}

	public function testGetSummaryEmptyArray(): void {
		$summary = \GDC_Transient_Viewer::get_summary([]);

		$this->assertSame(0, $summary['total']);
		$this->assertSame(0, $summary['expired']);
		$this->assertSame(0, $summary['total_size']);
	}

	public function testGetSummaryCountsExpiredCorrectly(): void {
		$now = time();
		$transients = [
			['key' => 'a', 'value_size' => 100, 'expires_in' => 3600, 'is_expired' => false],
			['key' => 'b', 'value_size' => 200, 'expires_in' => -100, 'is_expired' => true],
			['key' => 'c', 'value_size' => 300, 'expires_in' => null, 'is_expired' => false],
		];

		$summary = \GDC_Transient_Viewer::get_summary($transients);

		$this->assertSame(3, $summary['total']);
		$this->assertSame(1, $summary['expired']);
		$this->assertSame(600, $summary['total_size']);
	}

	// -------------------------------------------------------------------------
	// ajax_delete()
	// -------------------------------------------------------------------------

	public function testAjaxDeleteSucceeds(): void {
		$GLOBALS['gdc_test_state']['transients']['my_key'] = 'some_value';
		$_POST['key']         = 'my_key';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame('my_key', $response['data']['key']);
		$this->assertArrayNotHasKey('my_key', $GLOBALS['gdc_test_state']['transients']);
	}

	public function testAjaxDeleteRejectsEmptyKey(): void {
		$_POST['key']         = '';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertFalse($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	public function testAjaxDeleteUsesFilteredCapability(): void {
		$GLOBALS['gdc_test_state']['filter_overrides']['gdc_manage_capability'] = 'edit_posts';
		$_POST['key']         = 'some_key';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertTrue($GLOBALS['gdc_test_state']['json_response']['success']);
		$this->assertContains('edit_posts', $this->state('capability_checks'));
	}

	public function testAjaxDeleteForbiddenWithoutCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['key']         = 'some_key';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_delete();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertFalse($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	// -------------------------------------------------------------------------
	// ajax_purge_expired()
	// -------------------------------------------------------------------------

	public function testAjaxPurgeExpiredSucceeds(): void {
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_purge_expired();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertTrue($response['data']['purged']);
		$this->assertNotEmpty($GLOBALS['gdc_test_state']['wpdb_queries']);
	}

	public function testAjaxPurgeExpiredForbiddenWithoutCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Transient_Viewer::ajax_purge_expired();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertFalse($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	public function testPurgeExpiredAlsoCleansSiteTransientsOnMultisite(): void {
		$GLOBALS['gdc_test_state']['is_multisite'] = true;

		$deleted = \GDC_Transient_Viewer::purge_expired();
		$queries = $GLOBALS['gdc_test_state']['wpdb_queries'];

		$this->assertSame(2, $deleted);
		$this->assertCount(2, $queries);
		$this->assertStringContainsString('wp_options', $queries[0]);
		$this->assertStringContainsString('wp_sitemeta', $queries[1]);
		$this->assertStringContainsString('_site_transient_timeout_', $queries[1]);
	}

	public function testPurgeExpiredStaysOptionsOnlyOnSingleSite(): void {
		$GLOBALS['gdc_test_state']['is_multisite'] = false;

		\GDC_Transient_Viewer::purge_expired();

		$this->assertCount(1, $GLOBALS['gdc_test_state']['wpdb_queries']);
	}

	// -------------------------------------------------------------------------
	// render()
	// -------------------------------------------------------------------------

	public function testRenderOutputsEmptyStateWhenNoTransients(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-empty-state', $html);
		$this->assertStringNotContainsString('gdc-transient-table', $html);
	}

	public function testRenderOutputsTableWhenTransientsExist(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => '_transient_foo', 'value_size' => '256', 'timeout' => (string) (time() + 3600)],
		];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-transient-table', $html);
		$this->assertStringContainsString('foo', $html);
		$this->assertStringContainsString('gdc-transient-delete', $html);
	}

	public function testRenderHighlightsExpiredRows(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => '_transient_old', 'value_size' => '64', 'timeout' => '1000'],
		];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-transient-expired', $html);
		$this->assertStringContainsString('gdc-badge-expired', $html);
	}

	public function testRenderShowsPurgeButtonWhenExpiredExist(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => '_transient_old', 'value_size' => '64', 'timeout' => '1000'],
		];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-transient-purge', $html);
	}

	public function testRenderDoesNotShowPurgeButtonWithoutExpired(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [
			['option_name' => '_transient_fresh', 'value_size' => '64', 'timeout' => (string) (time() + 9999)],
		];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString('gdc-transient-purge', $html);
	}

	public function testRenderEmbedsSummaryCards(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdcTransient', $html);
		$this->assertStringContainsString('gdc-cron-summary', $html);
	}

	public function testGetDiagnosticsReturnsExpectedShape(): void {
		$diagnostics = \GDC_Transient_Viewer::get_diagnostics();

		$this->assertArrayHasKey('external_cache', $diagnostics);
		$this->assertArrayHasKey('cache_class', $diagnostics);
		$this->assertArrayHasKey('db_error', $diagnostics);
		$this->assertArrayHasKey('transient_like_rows', $diagnostics);
		$this->assertFalse($diagnostics['external_cache']);
	}

	public function testRenderExplainsObjectCacheWhenExternalCacheIsActive(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];
		$GLOBALS['gdc_test_state']['external_object_cache'] = true;

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-transient-cache-notice', $html);
		$this->assertStringContainsString('persistent object cache', strtolower($html));
		$this->assertStringContainsString('not an error', strtolower($html));
	}

	public function testRenderShowsDatabaseError(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];
		$GLOBALS['wpdb']->last_error = 'Table is marked as crashed';

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('Transient query failed', $html);
		$this->assertStringContainsString('Table is marked as crashed', $html);
	}

	public function testRenderExplainsTransientLikeRowsWhenListIsEmpty(): void {
		$GLOBALS['gdc_test_state']['wpdb_results'] = [];
		$GLOBALS['gdc_test_state']['wpdb_var_queue'] = ['3'];

		ob_start();
		\GDC_Transient_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('transient-like', $html);
		$this->assertStringContainsString('3', $html);
	}
}

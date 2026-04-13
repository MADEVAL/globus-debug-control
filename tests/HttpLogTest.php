<?php
declare(strict_types=1);

namespace GDC\Tests;

final class HttpLogTest extends WordPressTestCase {

	// -------------------------------------------------------------------------
	// is_capturing() / set_capturing()
	// -------------------------------------------------------------------------

	public function testIsCapturingReturnsFalseByDefault(): void {
		$this->assertFalse(\GDC_Http_Log::is_capturing());
	}

	public function testSetCapturingEnablesOption(): void {
		\GDC_Http_Log::set_capturing(true);

		$this->assertTrue(\GDC_Http_Log::is_capturing());
		$this->assertArrayHasKey('gdc_http_log_capturing', $GLOBALS['gdc_test_state']['updated_options']);
	}

	public function testSetCapturingDisablesOption(): void {
		$GLOBALS['gdc_test_state']['options']['gdc_http_log_capturing'] = 1;

		\GDC_Http_Log::set_capturing(false);

		$this->assertFalse(\GDC_Http_Log::is_capturing());
		$this->assertContains('gdc_http_log_capturing', $GLOBALS['gdc_test_state']['deleted_options']);
	}

	// -------------------------------------------------------------------------
	// get_log()
	// -------------------------------------------------------------------------

	public function testGetLogReturnsEmptyArrayWhenNoTransient(): void {
		$this->assertSame([], \GDC_Http_Log::get_log());
	}

	public function testGetLogReturnsStoredEntries(): void {
		$entries = [
			['ts' => time(), 'method' => 'GET', 'url' => 'https://example.com', 'status' => 200, 'error' => ''],
		];
		$GLOBALS['gdc_test_state']['transients']['gdc_http_log'] = $entries;

		$log = \GDC_Http_Log::get_log();

		$this->assertCount(1, $log);
		$this->assertSame('GET', $log[0]['method']);
		$this->assertSame(200, $log[0]['status']);
	}

	// -------------------------------------------------------------------------
	// on_request()
	// -------------------------------------------------------------------------

	public function testOnRequestCapturesSuccessfulEntry(): void {
		$response = ['response' => ['code' => 200, 'message' => 'OK']];
		$args     = ['method' => 'POST'];

		\GDC_Http_Log::on_request($response, 'response', 'WP_Http', $args, 'https://api.example.com/data');

		$log = \GDC_Http_Log::get_log();

		$this->assertCount(1, $log);
		$this->assertSame('POST', $log[0]['method']);
		$this->assertSame('https://api.example.com/data', $log[0]['url']);
		$this->assertSame(200, $log[0]['status']);
		$this->assertSame('', $log[0]['error']);
	}

	public function testOnRequestCapturesWpError(): void {
		$response = new \WP_Error('http_request_failed', 'Connection timed out');
		$args     = ['method' => 'GET'];

		\GDC_Http_Log::on_request($response, 'response', 'WP_Http', $args, 'https://slow.example.com');

		$log = \GDC_Http_Log::get_log();

		$this->assertCount(1, $log);
		$this->assertSame(0, $log[0]['status']);
		$this->assertSame('Connection timed out', $log[0]['error']);
	}

	public function testOnRequestIgnoresNonResponseContext(): void {
		$response = ['response' => ['code' => 301]];
		$args     = ['method' => 'GET'];

		\GDC_Http_Log::on_request($response, 'redirect', 'WP_Http', $args, 'https://example.com');

		$this->assertSame([], \GDC_Http_Log::get_log());
	}

	public function testOnRequestDefaultsToGetMethod(): void {
		$response = ['response' => ['code' => 200]];

		\GDC_Http_Log::on_request($response, 'response', 'WP_Http', [], 'https://example.com');

		$log = \GDC_Http_Log::get_log();

		$this->assertSame('GET', $log[0]['method']);
	}

	public function testOnRequestLimitsToMaxEntries(): void {
		$max      = \GDC_Http_Log::MAX_ENTRIES;
		$response = ['response' => ['code' => 200]];
		$args     = ['method' => 'GET'];

		// Pre-fill with MAX_ENTRIES entries.
		$existing = [];
		for ($i = 0; $i < $max; $i++) {
			$existing[] = ['ts' => time(), 'method' => 'GET', 'url' => "https://example.com/$i", 'status' => 200, 'error' => ''];
		}
		$GLOBALS['gdc_test_state']['transients']['gdc_http_log'] = $existing;

		// Add one more.
		\GDC_Http_Log::on_request($response, 'response', 'WP_Http', $args, 'https://new.example.com');

		$log = \GDC_Http_Log::get_log();

		$this->assertCount($max, $log);
		// New entry is at the front.
		$this->assertSame('https://new.example.com', $log[0]['url']);
	}

	public function testOnRequestPrependsNewestFirst(): void {
		$response = ['response' => ['code' => 200]];
		$args     = ['method' => 'GET'];

		\GDC_Http_Log::on_request($response, 'response', 'WP_Http', $args, 'https://first.example.com');
		\GDC_Http_Log::on_request($response, 'response', 'WP_Http', $args, 'https://second.example.com');

		$log = \GDC_Http_Log::get_log();

		$this->assertSame('https://second.example.com', $log[0]['url']);
		$this->assertSame('https://first.example.com', $log[1]['url']);
	}

	// -------------------------------------------------------------------------
	// ajax_clear()
	// -------------------------------------------------------------------------

	public function testAjaxClearDeletesTransient(): void {
		$GLOBALS['gdc_test_state']['transients']['gdc_http_log'] = [
			['ts' => time(), 'method' => 'GET', 'url' => 'https://x.com', 'status' => 200, 'error' => ''],
		];
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Http_Log::ajax_clear();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertTrue($GLOBALS['gdc_test_state']['json_response']['success']);
		$this->assertArrayNotHasKey('gdc_http_log', $GLOBALS['gdc_test_state']['transients']);
	}

	public function testAjaxClearForbiddenWithoutCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Http_Log::ajax_clear();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertFalse($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	// -------------------------------------------------------------------------
	// ajax_toggle()
	// -------------------------------------------------------------------------

	public function testAjaxToggleEnablesCapturing(): void {
		$_POST['enabled']     = '1';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Http_Log::ajax_toggle();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertTrue($response['data']['capturing']);
		$this->assertTrue(\GDC_Http_Log::is_capturing());
	}

	public function testAjaxToggleDisablesCapturing(): void {
		$GLOBALS['gdc_test_state']['options']['gdc_http_log_capturing'] = 1;
		$_POST['enabled']     = '0';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Http_Log::ajax_toggle();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertFalse($response['data']['capturing']);
		$this->assertFalse(\GDC_Http_Log::is_capturing());
	}

	public function testAjaxToggleForbiddenWithoutCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['enabled']     = '1';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Http_Log::ajax_toggle();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$this->assertFalse($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	// -------------------------------------------------------------------------
	// render()
	// -------------------------------------------------------------------------

	public function testRenderShowsEmptyStateWhenNotCapturing(): void {
		ob_start();
		\GDC_Http_Log::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-empty-state', $html);
		$this->assertStringContainsString('Start capturing', $html);
	}

	public function testRenderShowsTableWhenLogHasEntries(): void {
		$GLOBALS['gdc_test_state']['transients']['gdc_http_log'] = [
			['ts' => time(), 'method' => 'GET', 'url' => 'https://api.example.com', 'status' => 200, 'error' => ''],
			['ts' => time(), 'method' => 'POST', 'url' => 'https://auth.example.com', 'status' => 401, 'error' => ''],
		];

		ob_start();
		\GDC_Http_Log::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-http-table', $html);
		$this->assertStringContainsString('https://api.example.com', $html);
		$this->assertStringContainsString('gdc-http-status-2xx', $html);
		$this->assertStringContainsString('gdc-http-status-4xx', $html);
	}

	public function testRenderShowsClearButtonWhenLogNotEmpty(): void {
		$GLOBALS['gdc_test_state']['transients']['gdc_http_log'] = [
			['ts' => time(), 'method' => 'GET', 'url' => 'https://example.com', 'status' => 200, 'error' => ''],
		];

		ob_start();
		\GDC_Http_Log::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-http-clear', $html);
	}

	public function testRenderDoesNotShowClearButtonWhenLogEmpty(): void {
		ob_start();
		\GDC_Http_Log::render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString('gdc-http-clear', $html);
	}

	public function testRenderShowsCapturingStatusInCard(): void {
		$GLOBALS['gdc_test_state']['options']['gdc_http_log_capturing'] = 1;

		ob_start();
		\GDC_Http_Log::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('Capturing', $html);
		$this->assertStringContainsString('Stop capturing', $html);
	}

	public function testRenderStatusCodeClassification(): void {
		$GLOBALS['gdc_test_state']['transients']['gdc_http_log'] = [
			['ts' => time(), 'method' => 'GET', 'url' => 'https://a.com', 'status' => 500, 'error' => 'Server Error'],
			['ts' => time(), 'method' => 'GET', 'url' => 'https://b.com', 'status' => 301, 'error' => ''],
			['ts' => time(), 'method' => 'GET', 'url' => 'https://c.com', 'status' => 0,   'error' => 'Timeout'],
		];

		ob_start();
		\GDC_Http_Log::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-http-status-5xx', $html);
		$this->assertStringContainsString('gdc-http-status-3xx', $html);
		$this->assertStringContainsString('gdc-http-status-err', $html);
	}
}

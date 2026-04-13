<?php
declare(strict_types=1);

namespace GDC\Tests;

final class LogViewerTest extends WordPressTestCase {
	public function testGetLogPathReturnsDefaultWhenNoneConfigured(): void {
		$this->refreshOptions([
			'enabled'    => 1,
			'log_enabled' => 1,
			'log_path'   => '',
		]);

		$path = \GDC_Log_Viewer::get_log_path();

		$this->assertStringEndsWith('/debug.log', $path);
	}

	public function testGetLogPathUsesConfiguredRelativePath(): void {
		$this->refreshOptions([
			'enabled'    => 1,
			'log_enabled' => 1,
			'log_path'   => 'logs/custom.log',
		]);

		$path = \GDC_Log_Viewer::get_log_path();

		$this->assertStringContainsString('logs/custom.log', $path);
	}

	public function testClassifyLineParseErrorReturnsCorrectClass(): void {
		$this->assertSame('gdc-log-fatal', \GDC_Log_Viewer::classify_line('[12-Apr-2026 10:00:00 UTC] PHP Parse error: syntax error'));
	}

	public function testClassifyLineWarningReturnsCorrectClass(): void {
		$this->assertSame('gdc-log-warning', \GDC_Log_Viewer::classify_line('[12-Apr-2026 10:00:00 UTC] PHP Warning: Division by zero'));
	}

	public function testClassifyLineNoticeReturnsCorrectClass(): void {
		$this->assertSame('gdc-log-notice', \GDC_Log_Viewer::classify_line('[12-Apr-2026 10:00:00 UTC] PHP Notice: Undefined variable'));
	}

	public function testClassifyLineDeprecatedReturnsNoticeClass(): void {
		$this->assertSame('gdc-log-notice', \GDC_Log_Viewer::classify_line('[12-Apr-2026 10:00:00 UTC] PHP Deprecated: Use of mysql_ functions is deprecated'));
	}

	public function testClassifyLineCronReturnsCorrectClass(): void {
		$this->assertSame('gdc-log-cron', \GDC_Log_Viewer::classify_line('wp-cron: running scheduled event'));
	}

	public function testClassifyLineUnknownReturnsInfoClass(): void {
		$this->assertSame('gdc-log-info', \GDC_Log_Viewer::classify_line('Some generic log message'));
	}

	public function testHandleClearActionSkipsWhenNoPOSTKey(): void {
		$_POST = [];

		$log_path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($log_path, "line1\nline2\n");

		\GDC_Log_Viewer::handle_clear_action();

		$this->assertSame("line1\nline2\n", file_get_contents($log_path));
	}

	public function testHandleClearActionClearsFile(): void {
		$log_path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($log_path, "line1\nline2\n");

		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$_POST['gdc_clear_log'] = '1';
		$_POST['_wpnonce']      = 'test_nonce';

		try {
			\GDC_Log_Viewer::handle_clear_action();
		} catch (\GDC_Test_RedirectException $e) {
			$this->assertStringContainsString('cleared=1', $e->location);
		}

		$this->assertSame('', file_get_contents($log_path));
	}

	public function testRenderOutputsEmptyStateWhenNoLogFile(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-log-viewer', $html);
	}

	public function testRenderOutputsLogLinesWhenFileExists(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($path, "[12-Apr-2026] PHP Warning: test warning\n[12-Apr-2026] PHP Notice: test notice\n");

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('test warning', $html);
		$this->assertStringContainsString('test notice', $html);
	}

	public function testHandleDownloadActionSkipsWhenGdcDlNotSet(): void {
		$_GET = [];

		\GDC_Log_Viewer::handle_download_action();

		$this->assertTrue(true);
	}

	public function testHandleDownloadActionDiesWhenFileNotExists(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		$_GET['gdc_dl']   = '1';
		$_GET['_wpnonce'] = 'test_nonce';

		$this->expectException(\GDC_Test_WpDieException::class);
		\GDC_Log_Viewer::handle_download_action();
	}

	public function testRenderOutputsCodeMirrorTextareaWhenFileExists(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($path, "[12-Apr-2026] PHP Warning: test warning\n[12-Apr-2026] PHP Notice: test notice\n");

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-log-viewer', $html);
		$this->assertStringContainsString('gdc-log-code', $html);
		$this->assertStringContainsString('gdc-log-line-data', $html);
	}

	public function testRenderAlwaysOutputsToolbarWithGenerateButton(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-log-toolbar', $html);
		$this->assertStringContainsString('gdc-generate-error', $html);
		$this->assertStringContainsString('gdc-live-toggle', $html);
		$this->assertStringContainsString('gdc-error-type', $html);
	}

	public function testRenderToolbarPresentWhenLogEmpty(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($path, '');

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-log-toolbar', $html);
		$this->assertStringContainsString('gdc-generate-error', $html);
		$this->assertStringContainsString('gdc-log-empty-state', $html);
	}

	public function testAjaxLogStatusReturnsMtimeAndSize(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($path, "test line\n");

		try {
			\GDC_Log_Viewer::ajax_log_status();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('mtime', $response['data']);
		$this->assertArrayHasKey('size', $response['data']);
		$this->assertGreaterThan(0, $response['data']['size']);
	}

	public function testAjaxLogStatusReturnsZerosWhenNoFile(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		try {
			\GDC_Log_Viewer::ajax_log_status();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame(0, $response['data']['mtime']);
		$this->assertSame(0, $response['data']['size']);
	}

	public function testAjaxLogStatusForbiddenWhenNoCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;

		try {
			\GDC_Log_Viewer::ajax_log_status();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertFalse($response['success']);
	}

	public function testAjaxGenerateErrorNoticeTriggersError(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$_POST['type']        = 'notice';
		$_POST['_ajax_nonce'] = 'test_nonce';

		$triggered = false;
		set_error_handler(function () use (&$triggered) {
			$triggered = true;
			return true;
		});

		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		} finally {
			restore_error_handler();
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame('notice', $response['data']['type']);
		$this->assertTrue($triggered);
	}

	public function testAjaxGenerateErrorWarningSucceeds(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'warning';
		$_POST['_ajax_nonce'] = 'test_nonce';

		set_error_handler(function () { return true; });
		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		} finally {
			restore_error_handler();
		}

		$this->assertTrue($GLOBALS['gdc_test_state']['json_response']['success']);
		$this->assertSame('warning', $GLOBALS['gdc_test_state']['json_response']['data']['type']);
	}

	public function testAjaxGenerateErrorDeprecatedSucceeds(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'deprecated';
		$_POST['_ajax_nonce'] = 'test_nonce';

		set_error_handler(function () { return true; });
		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		} finally {
			restore_error_handler();
		}

		$this->assertTrue($GLOBALS['gdc_test_state']['json_response']['success']);
	}

	public function testAjaxGenerateErrorRejectsInvalidType(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'invalid_type';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertFalse($response['success']);
	}

	public function testAjaxGenerateErrorForbiddenWhenNoCapability(): void {
		$GLOBALS['gdc_test_state']['current_user_can'] = false;
		$_POST['type']        = 'notice';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertFalse($response['success']);
	}

	public function testAjaxGenerateErrorTypeErrorSucceeds(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'error';
		$_POST['_ajax_nonce'] = 'test_nonce';

		set_error_handler(function () { return true; });
		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		} finally {
			restore_error_handler();
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame('error', $response['data']['type']);
	}

	public function testAjaxGenerateErrorExceptionSucceeds(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'exception';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame('exception', $response['data']['type']);
	}

	public function testAjaxGenerateErrorFatalSucceeds(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'fatal';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame('fatal', $response['data']['type']);
	}

	public function testAjaxGenerateErrorParseSucceeds(): void {
		$this->refreshOptions(['enabled' => 1]);
		$_POST['type']        = 'parse';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			\GDC_Log_Viewer::ajax_generate_error();
		} catch (\GDC_Test_WpDieException $e) {
		}

		$response = $GLOBALS['gdc_test_state']['json_response'];
		$this->assertTrue($response['success']);
		$this->assertSame('parse', $response['data']['type']);
	}

	public function testRenderOutputsWrapCheckboxWhenLogNotEmpty(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($path, "[12-Apr-2026] PHP Warning: test wrap toggle\n");

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-log-wrap-toggle', $html);
		$this->assertStringContainsString('gdc-log-wrap-label', $html);
	}

	public function testRenderDoesNotOutputWrapCheckboxWhenLogEmpty(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		file_put_contents($path, '');

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString('gdc-log-wrap-toggle', $html);
	}

	public function testRenderSelectContainsAllSevenErrorTypes(): void {
		$this->refreshOptions([
			'enabled'     => 1,
			'log_enabled' => 1,
			'log_path'    => '',
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		foreach (['notice', 'warning', 'deprecated', 'error', 'exception', 'fatal', 'parse'] as $type) {
			$this->assertStringContainsString('value="' . $type . '"', $html, "Missing option for type: $type");
		}
	}

	// -------------------------------------------------------------------------
	// get_suppressed_types()
	// -------------------------------------------------------------------------

	public function testGetSuppressedTypesDefaultsToNoticesAndDeprecated(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'suppress_notices'    => 1,
			'suppress_warnings'   => 0,
			'suppress_deprecated' => 1,
		]);

		$types = \GDC_Log_Viewer::get_suppressed_types();

		$this->assertContains('notice', $types);
		$this->assertContains('deprecated', $types);
		$this->assertNotContains('warning', $types);
	}

	public function testGetSuppressedTypesIncludesWarningWhenEnabled(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'suppress_notices'    => 0,
			'suppress_warnings'   => 1,
			'suppress_deprecated' => 0,
		]);

		$types = \GDC_Log_Viewer::get_suppressed_types();

		$this->assertSame(['warning'], $types);
	}

	public function testGetSuppressedTypesReturnsEmptyWhenNothingSuppressed(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'suppress_notices'    => 0,
			'suppress_warnings'   => 0,
			'suppress_deprecated' => 0,
		]);

		$this->assertSame([], \GDC_Log_Viewer::get_suppressed_types());
	}

	public function testGetSuppressedTypesAllThreeSuppressed(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'suppress_notices'    => 1,
			'suppress_warnings'   => 1,
			'suppress_deprecated' => 1,
		]);

		$types = \GDC_Log_Viewer::get_suppressed_types();

		$this->assertContains('notice', $types);
		$this->assertContains('warning', $types);
		$this->assertContains('deprecated', $types);
		$this->assertCount(3, $types);
	}

	public function testGetSuppressedTypesNeverIncludesNonTriggerTypes(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'suppress_notices'    => 1,
			'suppress_warnings'   => 1,
			'suppress_deprecated' => 1,
		]);

		$types = \GDC_Log_Viewer::get_suppressed_types();

		foreach (['error', 'exception', 'fatal', 'parse'] as $type) {
			$this->assertNotContains($type, $types, "Type '$type' must never appear in suppressed list");
		}
	}

	public function testRenderEmbedsSuppressedTypesInScript(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'log_enabled'         => 1,
			'log_path'            => '',
			'suppress_notices'    => 1,
			'suppress_warnings'   => 0,
			'suppress_deprecated' => 1,
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('"suppressedTypes"', $html);
		$this->assertMatchesRegularExpression('/"suppressedTypes"\s*:\s*\[/', $html);
	}

	public function testRenderOptionLabelContainsSuppressedWhenTypeSuppressed(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'log_enabled'         => 1,
			'log_path'            => '',
			'suppress_notices'    => 1,
			'suppress_warnings'   => 0,
			'suppress_deprecated' => 0,
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		// Notice option should mention suppressed.
		$this->assertMatchesRegularExpression('/value="notice"[^>]*>[^<]*suppressed/i', $html);
		// Warning option should NOT mention suppressed.
		$this->assertDoesNotMatchRegularExpression('/value="warning"[^>]*>[^<]*suppressed/i', $html);
	}

	public function testRenderOptionLabelNotSuppressedWhenDisabled(): void {
		$this->refreshOptions([
			'enabled'             => 1,
			'log_enabled'         => 1,
			'log_path'            => '',
			'suppress_notices'    => 0,
			'suppress_warnings'   => 0,
			'suppress_deprecated' => 0,
		]);

		$path = $this->runtimePath('wp-content/debug.log');
		if (file_exists($path)) {
			unlink($path);
		}

		ob_start();
		\GDC_Log_Viewer::render();
		$html = ob_get_clean();

		// No option label should mention "suppressed".
		$this->assertDoesNotMatchRegularExpression('/value="\w+"[^>]*>[^<]*suppressed/i', $html);
		$this->assertStringNotContainsString('gdc-suppress-warn', $html);
	}
}

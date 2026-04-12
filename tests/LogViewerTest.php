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

	public function testClassifyLineFatalReturnsCorrectClass(): void {
		$this->assertSame('gdc-log-fatal', \GDC_Log_Viewer::classify_line('[12-Apr-2026 10:00:00 UTC] PHP Fatal error: Uncaught Error'));
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

	public function testGetInlineStylesReturnsNonEmptyString(): void {
		$this->assertNotEmpty(\GDC_Log_Viewer::get_inline_styles());
	}
}

<?php
declare(strict_types=1);

namespace GDC\Tests;

final class WpConfigDebugTest extends WordPressTestCase {
	private string $config_dir;
	private string $config_path;

	protected function setUp(): void {
		parent::setUp();
		$this->config_dir  = $this->runtimePath('wordpress');
		$this->config_path = $this->config_dir . '/wp-config.php';

		if (! is_dir($this->config_dir)) {
			mkdir($this->config_dir, 0777, true);
		}
	}

	protected function tearDown(): void {
		if (file_exists($this->config_path)) {
			unlink($this->config_path);
		}

		parent::tearDown();
	}

	private function writeConfig(string $content): void {
		file_put_contents($this->config_path, $content);
	}

	public function testFindWpConfigReturnsPathWhenExists(): void {
		$this->writeConfig("<?php\n// config\n");

		$found = \GDC_WpConfig_Debug::find_wp_config();

		$this->assertSame(
			str_replace('\\', '/', $this->config_path),
			str_replace('\\', '/', $found)
		);
	}

	public function testFindWpConfigReturnsEmptyStringWhenMissing(): void {
		if (file_exists($this->config_path)) {
			unlink($this->config_path);
		}

		$found = \GDC_WpConfig_Debug::find_wp_config();

		$this->assertSame('', $found);
	}

	public function testReadConstantsDetectsDefinedTrueConstant(): void {
		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', true );\n");

		$constants = \GDC_WpConfig_Debug::read_constants($this->config_path);

		$this->assertTrue($constants['WP_DEBUG']['defined']);
		$this->assertTrue($constants['WP_DEBUG']['value']);
	}

	public function testReadConstantsDetectsDefinedFalseConstant(): void {
		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', false );\n");

		$constants = \GDC_WpConfig_Debug::read_constants($this->config_path);

		$this->assertTrue($constants['WP_DEBUG']['defined']);
		$this->assertFalse($constants['WP_DEBUG']['value']);
	}

	public function testReadConstantsReturnsNotDefinedForMissingConstant(): void {
		$this->writeConfig("<?php\n// no WP_DEBUG here\n");

		$constants = \GDC_WpConfig_Debug::read_constants($this->config_path);

		$this->assertFalse($constants['WP_DEBUG']['defined']);
	}

	public function testReadConstantsDetectsAllSupportedConstants(): void {
		$this->writeConfig(
			"<?php\n" .
			"define( 'WP_DEBUG', true );\n" .
			"define( 'WP_DEBUG_LOG', true );\n" .
			"define( 'WP_DEBUG_DISPLAY', false );\n" .
			"define( 'SCRIPT_DEBUG', true );\n" .
			"define( 'SAVEQUERIES', false );\n"
		);

		$constants = \GDC_WpConfig_Debug::read_constants($this->config_path);

		$this->assertTrue($constants['WP_DEBUG']['defined']);
		$this->assertTrue($constants['WP_DEBUG_LOG']['defined']);
		$this->assertTrue($constants['WP_DEBUG_DISPLAY']['defined']);
		$this->assertTrue($constants['SCRIPT_DEBUG']['defined']);
		$this->assertTrue($constants['SAVEQUERIES']['defined']);
	}

	public function testSetConstantUpdatesExistingConstant(): void {
		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', false );\n");

		$result = \GDC_WpConfig_Debug::set_constant($this->config_path, 'WP_DEBUG', 'true');

		$this->assertTrue($result);
		$content = file_get_contents($this->config_path);
		$this->assertStringContainsString("define( 'WP_DEBUG', true );", $content);
		$this->assertStringNotContainsString("define( 'WP_DEBUG', false );", $content);
	}

	public function testSetConstantInsertsBeforeStopEditingMarker(): void {
		$this->writeConfig(
			"<?php\n" .
			"/* That's all, stop editing! Happy publishing. */\n"
		);

		$result = \GDC_WpConfig_Debug::set_constant($this->config_path, 'WP_DEBUG', 'true');

		$this->assertTrue($result);
		$content = file_get_contents($this->config_path);
		$this->assertStringContainsString("define( 'WP_DEBUG', true );", $content);
	}

	public function testSetConstantReturnsFalseForUnknownConstant(): void {
		$this->writeConfig("<?php\n");

		$result = \GDC_WpConfig_Debug::set_constant($this->config_path, 'UNKNOWN_CONSTANT', 'true');

		$this->assertFalse($result);
	}

	public function testSetConstantReturnsFalseWhenFileNotExists(): void {
		$result = \GDC_WpConfig_Debug::set_constant('/nonexistent/wp-config.php', 'WP_DEBUG', 'true');

		$this->assertFalse($result);
	}

	public function testParseRawValueReturnsTrueForTrueString(): void {
		$this->assertTrue(\GDC_WpConfig_Debug::parse_raw_value('true'));
		$this->assertTrue(\GDC_WpConfig_Debug::parse_raw_value('1'));
		$this->assertTrue(\GDC_WpConfig_Debug::parse_raw_value('TRUE'));
	}

	public function testParseRawValueReturnsFalseForFalseString(): void {
		$this->assertFalse(\GDC_WpConfig_Debug::parse_raw_value('false'));
		$this->assertFalse(\GDC_WpConfig_Debug::parse_raw_value('0'));
		$this->assertFalse(\GDC_WpConfig_Debug::parse_raw_value(''));
	}

	public function testGetWpDebugStatusReturnsNullWhenNoConfig(): void {
		if (file_exists($this->config_path)) {
			unlink($this->config_path);
		}

		$status = \GDC_WpConfig_Debug::get_wp_debug_status();

		$this->assertNull($status);
	}

	public function testGetWpDebugStatusReturnsTrueWhenEnabled(): void {
		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', true );\n");

		$status = \GDC_WpConfig_Debug::get_wp_debug_status();

		$this->assertTrue($status);
	}

	public function testGetWpDebugStatusReturnsFalseWhenDisabled(): void {
		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', false );\n");

		$status = \GDC_WpConfig_Debug::get_wp_debug_status();

		$this->assertFalse($status);
	}

	public function testRenderOutputsFormWithCheckboxes(): void {
		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', true );\n");

		ob_start();
		\GDC_WpConfig_Debug::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('WP_DEBUG', $html);
		$this->assertStringContainsString('gdc_wpcfg[WP_DEBUG]', $html);
	}

	public function testRenderShowsWarningWhenConfigNotFound(): void {
		if (file_exists($this->config_path)) {
			unlink($this->config_path);
		}

		ob_start();
		\GDC_WpConfig_Debug::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('not found', $html);
	}

	public function testHandleActionSkipsWhenNoPOSTKey(): void {
		$_POST = [];

		$this->writeConfig("<?php\ndefine( 'WP_DEBUG', false );\n");

		\GDC_WpConfig_Debug::handle_action();

		$content = file_get_contents($this->config_path);
		$this->assertStringContainsString('false', $content);
	}
}

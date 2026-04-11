<?php
declare(strict_types=1);

namespace GDC\Tests;

final class UninstallTest extends WordPressTestCase {
	public function testUninstallDeletesOptionsAndOwnedMuDropin(): void {
		$path = WPMU_PLUGIN_DIR . '/gdc-early-loader.php';
		file_put_contents($path, "<?php\n/* GLOBUS Debug Control */\n");

		$GLOBALS['gdc_test_state']['options']['globus_debug_control_options'] = ['enabled' => 1];
		$GLOBALS['gdc_test_state']['options']['gdc_options'] = ['enabled' => 1];

		include dirname(__DIR__) . '/globus-debug-control/uninstall.php';

		$this->assertContains('globus_debug_control_options', $this->state('deleted_options'));
		$this->assertContains('gdc_options', $this->state('deleted_options'));
		$this->assertContains($path, $this->state('deleted_files'));
		$this->assertFileDoesNotExist($path);
	}

	public function testUninstallDoesNotDeleteForeignMuDropin(): void {
		$path = WPMU_PLUGIN_DIR . '/gdc-early-loader.php';
		file_put_contents($path, "<?php\n/* Foreign dropin */\n");

		include dirname(__DIR__) . '/globus-debug-control/uninstall.php';

		$this->assertFileExists($path);
		$this->assertNotContains($path, $this->state('deleted_files'));
	}
}
<?php
declare(strict_types=1);

namespace GDC\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

abstract class WordPressTestCase extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		gdc_test_reset_state();
		$this->refreshOptions([
			'enabled' => 0,
		]);
	}

	protected function tearDown(): void {
		gdc_test_reset_state();
		parent::tearDown();
	}

	protected function plugin(): \Globus_Debug_Control {
		return \Globus_Debug_Control::instance();
	}

	protected function refreshOptions(array $options): array {
		$GLOBALS['gdc_test_state']['options']['globus_debug_control_options'] = $options;
		unset($GLOBALS['gdc_test_state']['options']['gdc_options']);
		return $this->plugin()->get_options(true);
	}

	protected function setLegacyOnlyOptions(array $options): array {
		unset($GLOBALS['gdc_test_state']['options']['globus_debug_control_options']);
		$GLOBALS['gdc_test_state']['options']['gdc_options'] = $options;
		return $this->plugin()->get_options(true);
	}

	protected function runtimePath(string $suffix = ''): string {
		$path = gdc_test_runtime_root();
		if ('' !== $suffix) {
			$path .= '/' . ltrim($suffix, '/');
		}
		return str_replace('\\', '/', $path);
	}

	protected function invokePrivate(string $method, array $args = []) {
		$reflection = new ReflectionMethod($this->plugin(), $method);
		return $reflection->invokeArgs($this->plugin(), $args);
	}

	protected function state(?string $key = null) {
		if (null === $key) {
			return $GLOBALS['gdc_test_state'];
		}

		return $GLOBALS['gdc_test_state'][$key];
	}
}
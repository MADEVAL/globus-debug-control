<?php
declare(strict_types=1);

namespace GDC\Tests;

final class ConstantsViewerTest extends WordPressTestCase {

	// -------------------------------------------------------------------------
	// sensitive_keys()
	// -------------------------------------------------------------------------

	public function testSensitiveKeysContainsDbPassword(): void {
		$this->assertContains('DB_PASSWORD', \GDC_Constants_Viewer::sensitive_keys());
	}

	public function testSensitiveKeysContainsAuthSalts(): void {
		$sensitive = \GDC_Constants_Viewer::sensitive_keys();
		$expected  = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
		              'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];

		foreach ($expected as $key) {
			$this->assertContains($key, $sensitive, "Expected '$key' in sensitive_keys()");
		}
	}

	// -------------------------------------------------------------------------
	// format_value()
	// -------------------------------------------------------------------------

	public function testFormatValueMasksSensitiveKey(): void {
		$result = \GDC_Constants_Viewer::format_value('DB_PASSWORD', 'secret123', ['DB_PASSWORD']);

		$this->assertSame('*** (masked)', $result);
	}

	public function testFormatValueBoolTrue(): void {
		$result = \GDC_Constants_Viewer::format_value('WP_DEBUG', true, []);

		$this->assertSame('true', $result);
	}

	public function testFormatValueBoolFalse(): void {
		$result = \GDC_Constants_Viewer::format_value('WP_DEBUG', false, []);

		$this->assertSame('false', $result);
	}

	public function testFormatValueNull(): void {
		$result = \GDC_Constants_Viewer::format_value('SOME_CONST', null, []);

		$this->assertSame('null', $result);
	}

	public function testFormatValueString(): void {
		$result = \GDC_Constants_Viewer::format_value('ABSPATH', '/var/www/html/', []);

		$this->assertSame('/var/www/html/', $result);
	}

	public function testFormatValueInteger(): void {
		$result = \GDC_Constants_Viewer::format_value('WP_MEMORY_LIMIT', 256, []);

		$this->assertSame('256', $result);
	}

	public function testFormatValueArray(): void {
		$result = \GDC_Constants_Viewer::format_value('SOME_CONST', ['a', 'b', 'c'], []);

		$this->assertSame('array(3)', $result);
	}

	public function testFormatValueObject(): void {
		$result = \GDC_Constants_Viewer::format_value('SOME_CONST', new \stdClass(), []);

		$this->assertSame('object(stdClass)', $result);
	}

	// -------------------------------------------------------------------------
	// get_wp_constants()
	// -------------------------------------------------------------------------

	public function testGetWpConstantsReturnsOnlyNonEmptyGroups(): void {
		$groups = \GDC_Constants_Viewer::get_wp_constants();

		foreach ($groups as $label => $constants) {
			$this->assertNotEmpty($constants, "Group '$label' should not be empty after array_filter");
		}
	}

	public function testGetWpConstantsGroupsAreArrays(): void {
		$groups = \GDC_Constants_Viewer::get_wp_constants();

		foreach ($groups as $constants) {
			$this->assertIsArray($constants);
		}
	}

	public function testGetWpConstantsIncludesDebugGroup(): void {
		// WP_DEBUG is defined in bootstrap.php as true.
		$groups = \GDC_Constants_Viewer::get_wp_constants();

		$this->assertArrayHasKey('Debug', $groups);
		$this->assertArrayHasKey('WP_DEBUG', $groups['Debug']);
	}

	public function testGetWpConstantsIncludesAbspathInPathsGroup(): void {
		$groups = \GDC_Constants_Viewer::get_wp_constants();

		$this->assertArrayHasKey('Paths', $groups);
		$this->assertArrayHasKey('ABSPATH', $groups['Paths']);
	}

	public function testGetWpConstantsMasksDbPassword(): void {
		// DB_PASSWORD is defined in bootstrap as 'test_password'.
		$groups = \GDC_Constants_Viewer::get_wp_constants();

		if (isset($groups['Database']['DB_PASSWORD'])) {
			$this->assertSame('*** (masked)', $groups['Database']['DB_PASSWORD']);
		} else {
			// DB_PASSWORD not defined — acceptable.
			$this->assertTrue(true);
		}
	}

	public function testGetWpConstantsMasksAuthKey(): void {
		// AUTH_KEY is defined in bootstrap.
		$groups = \GDC_Constants_Viewer::get_wp_constants();

		if (isset($groups['Auth & Salts']['AUTH_KEY'])) {
			$this->assertSame('*** (masked)', $groups['Auth & Salts']['AUTH_KEY']);
		} else {
			$this->assertTrue(true);
		}
	}

	// -------------------------------------------------------------------------
	// render()
	// -------------------------------------------------------------------------

	public function testRenderOutputsGroupHeadings(): void {
		ob_start();
		\GDC_Constants_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-constant-group-heading', $html);
	}

	public function testRenderOutputsConstantsTable(): void {
		ob_start();
		\GDC_Constants_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('gdc-constants-table', $html);
	}

	public function testRenderShowsBackLinkToWpcfgTab(): void {
		ob_start();
		\GDC_Constants_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('tab=wpcfg', $html);
	}

	public function testRenderMaskedValuesUseMaskedClass(): void {
		ob_start();
		\GDC_Constants_Viewer::render();
		$html = ob_get_clean();

		// If any sensitive constant is defined, masked class should appear.
		$groups = \GDC_Constants_Viewer::get_wp_constants();
		$has_sensitive = false;
		foreach ($groups as $constants) {
			if (in_array('*** (masked)', array_values($constants), true)) {
				$has_sensitive = true;
				break;
			}
		}

		if ($has_sensitive) {
			$this->assertStringContainsString('gdc-constant-masked', $html);
		} else {
			$this->assertStringNotContainsString('*** (masked)', $html);
		}
	}

	public function testRenderWpDebugValueIsDisplayed(): void {
		ob_start();
		\GDC_Constants_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('WP_DEBUG', $html);
	}
}

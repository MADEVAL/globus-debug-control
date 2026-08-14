<?php
declare(strict_types=1);

namespace GDC\Tests;

final class ComposerJsonTest extends WordPressTestCase {
	public function testComposerJsonHasNoBom(): void {
		$bytes = (string) file_get_contents(dirname(__DIR__) . '/composer.json');

		$this->assertNotSame("\xEF\xBB\xBF", substr($bytes, 0, 3), 'composer.json must not start with a UTF-8 BOM');
	}

	public function testComposerJsonIsValidJson(): void {
		$json = (string) file_get_contents(dirname(__DIR__) . '/composer.json');

		$this->assertNotNull(json_decode($json, true));
		$this->assertSame(JSON_ERROR_NONE, json_last_error());
	}
}

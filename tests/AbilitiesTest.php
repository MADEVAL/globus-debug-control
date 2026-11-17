<?php
declare(strict_types=1);

namespace GDC\Tests;

final class AbilitiesTest extends WordPressTestCase {
	protected function setUp(): void {
		parent::setUp();
		\GDC_Abilities::register_categories();
		\GDC_Abilities::register();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function ability(string $name): array {
		$abilities = $this->state('abilities');

		return $abilities[$name];
	}

	/**
	 * @return mixed
	 */
	private function execute(string $name, array $input = []) {
		$callback = $this->ability($name)['execute_callback'];

		return $callback($input);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function iniSnapshot(): array {
		return [
			'display_errors' => ini_get('display_errors'),
			'log_errors' => ini_get('log_errors'),
			'error_log' => ini_get('error_log'),
			'error_reporting' => error_reporting(),
		];
	}

	private function restoreIni(array $snapshot): void {
		if (false !== $snapshot['display_errors']) {
			ini_set('display_errors', $snapshot['display_errors']);
		}
		if (false !== $snapshot['log_errors']) {
			ini_set('log_errors', $snapshot['log_errors']);
		}
		if (false !== $snapshot['error_log']) {
			ini_set('error_log', $snapshot['error_log']);
		}
		error_reporting($snapshot['error_reporting']);
	}

	public function testCategoryAndAbilitiesAreRegisteredForMcp(): void {
		$categories = $this->state('ability_categories');
		$this->assertArrayHasKey('gdc-debug', $categories);

		$expected = [
			'gdc/get-schema',
			'gdc/get-options',
			'gdc/update-options',
			'gdc/reset-options',
			'gdc/get-log',
			'gdc/clear-log',
			'gdc/get-wp-config',
			'gdc/update-wp-config',
		];

		foreach ($expected as $name) {
			$ability = $this->ability($name);

			$this->assertSame('gdc-debug', $ability['category']);
			$this->assertTrue($ability['meta']['public']);
			$this->assertTrue($ability['meta']['mcp']['public']);
			$this->assertSame('tool', $ability['meta']['mcp']['type']);
			$this->assertTrue(($ability['permission_callback'])());
			$this->assertSame('object', $ability['input_schema']['type']);
		}
	}

	public function testSchemaCoversEveryPluginOption(): void {
		$result = $this->execute('gdc/get-schema');
		$options = $result['options'];

		$expected = [
			'enabled',
			'suppress_notices',
			'suppress_deprecated',
			'suppress_warnings',
			'suppress_strict',
			'display_errors_mode',
			'log_enabled',
			'log_path',
			'log_max_size_mb',
			'remove_php_error_bodyclass',
			'force_in_api_contexts',
			'install_mu_dropin',
		];

		$this->assertSame($expected, array_keys($options));

		foreach ($options as $definition) {
			$this->assertArrayHasKey('type', $definition);
			$this->assertArrayHasKey('default', $definition);
			$this->assertArrayHasKey('description', $definition);
		}

		$this->assertSame(['inherit', 'on', 'off'], $options['display_errors_mode']['enum']);
		$this->assertArrayHasKey('WP_DEBUG_LOG', $result['constants']);
		$this->assertCount(8, $result['abilities']);
	}

	public function testUpdateOptionsPatchesOnlySuppliedKeysAndSanitises(): void {
		$snapshot = $this->iniSnapshot();
		$this->refreshOptions(['enabled' => 1]);

		try {
			$result = $this->execute('gdc/update-options', [
				'log_enabled' => false,
				'suppress_warnings' => true,
				'display_errors_mode' => 'off',
				'log_path' => 'logs/custom.log',
				'log_max_size_mb' => 500,
			]);
		} finally {
			$this->restoreIni($snapshot);
		}

		$this->assertSame(
			['log_enabled', 'suppress_warnings', 'display_errors_mode', 'log_path', 'log_max_size_mb'],
			$result['updated']
		);
		$this->assertSame(0, $result['options']['log_enabled']);
		$this->assertSame(1, $result['options']['suppress_warnings']);
		$this->assertSame('off', $result['options']['display_errors_mode']);
		$this->assertSame('logs/custom.log', $result['options']['log_path']);
		$this->assertSame(100, $result['options']['log_max_size_mb']);
		$this->assertSame(1, $result['options']['suppress_notices']);
		$this->assertSame(E_WARNING, $result['runtime']['suppress_mask'] & E_WARNING);
		$this->assertSame('0', $result['runtime']['log_errors']);
	}

	public function testUpdateOptionsRejectsUnknownKeys(): void {
		$result = $this->execute('gdc/update-options', ['unknown_option' => true]);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('gdc_empty_patch', $result->get_error_code());
	}

	public function testUpdateOptionsAcceptsDropdownAndPathValues(): void {
		$snapshot = $this->iniSnapshot();
		$this->refreshOptions(['enabled' => 1, 'log_path' => '../wp-config.php']);

		try {
			$result = $this->execute('gdc/update-options', [
				'display_errors_mode' => 'on',
				'log_path' => '../wp-config.php',
			]);
		} finally {
			$this->restoreIni($snapshot);
		}

		$this->assertSame('on', $result['options']['display_errors_mode']);
		$this->assertSame('', $result['options']['log_path']);
	}

	public function testResetOptionsRequiresConfirmAndRestoresDefaults(): void {
		$snapshot = $this->iniSnapshot();
		$this->refreshOptions(['enabled' => 1, 'log_enabled' => 0, 'suppress_warnings' => 1]);

		try {
			$denied = $this->execute('gdc/reset-options', []);
			$result = $this->execute('gdc/reset-options', ['confirm' => true]);
		} finally {
			$this->restoreIni($snapshot);
		}

		$this->assertInstanceOf(\WP_Error::class, $denied);
		$this->assertSame('gdc_confirm_required', $denied->get_error_code());
		$this->assertSame(1, $result['options']['enabled']);
		$this->assertSame(1, $result['options']['log_enabled']);
		$this->assertSame(0, $result['options']['suppress_warnings']);
		$this->assertSame('inherit', $result['options']['display_errors_mode']);
	}

	public function testGetLogReturnsTailWithSeverityLevels(): void {
		$path = $this->runtimePath('debug.log');
		file_put_contents($path, "line-one\nPHP Warning: two\nPHP Fatal error: three\nPHP Notice: four\ncron five\n");
		$this->refreshOptions(['log_path' => $path]);

		$result = $this->execute('gdc/get-log', ['lines' => 3]);

		$this->assertSame($path, $result['path']);
		$this->assertTrue($result['exists']);
		$this->assertSame(3, count($result['lines']));
		$this->assertSame('PHP Fatal error: three', $result['lines'][0]['text']);
		$this->assertSame('gdc-log-fatal', $result['lines'][0]['level']);
		$this->assertSame('gdc-log-notice', $result['lines'][1]['level']);
		$this->assertSame('gdc-log-cron', $result['lines'][2]['level']);
		$this->assertTrue($result['truncated']);
	}

	public function testGetLogFiltersByContains(): void {
		$path = $this->runtimePath('debug.log');
		file_put_contents($path, "alpha\nfatal beta\ngamma\n");
		$this->refreshOptions(['log_path' => $path]);

		$result = $this->execute('gdc/get-log', ['lines' => 10, 'contains' => 'fatal']);

		$this->assertCount(1, $result['lines']);
		$this->assertSame('fatal beta', $result['lines'][0]['text']);
	}

	public function testGetLogHandlesMissingFile(): void {
		$this->refreshOptions(['log_path' => $this->runtimePath('missing.log')]);

		$result = $this->execute('gdc/get-log');

		$this->assertFalse($result['exists']);
		$this->assertSame([], $result['lines']);
	}

	public function testClearLogRequiresConfirmAndTruncatesFile(): void {
		$path = $this->runtimePath('debug.log');
		file_put_contents($path, "something\n");
		$this->refreshOptions(['log_path' => $path]);

		$denied = $this->execute('gdc/clear-log', []);
		$this->assertInstanceOf(\WP_Error::class, $denied);

		$result = $this->execute('gdc/clear-log', ['confirm' => true]);

		$this->assertTrue($result['cleared']);
		$this->assertSame(10, $result['bytes']);
		$this->assertSame('', (string) file_get_contents($path));
	}

	public function testWpConfigAbilitiesReadAndWriteConstants(): void {
		if (! is_dir(ABSPATH)) {
			mkdir(ABSPATH, 0777, true);
		}

		$config = rtrim(ABSPATH, '/\\') . '/wp-config.php';
		file_put_contents(
			$config,
			"<?php\ndefine( 'WP_DEBUG', true );\n/* That's all, stop editing! Happy publishing. */\n"
		);

		$read = $this->execute('gdc/get-wp-config');
		$this->assertSame(str_replace('\\', '/', $config), str_replace('\\', '/', $read['path']));
		$this->assertTrue($read['constants']['WP_DEBUG']['value']);
		$this->assertFalse($read['constants']['SAVEQUERIES']['defined']);

		$denied = $this->execute('gdc/update-wp-config', [
			'constants' => ['WP_DEBUG' => false],
		]);
		$this->assertInstanceOf(\WP_Error::class, $denied);

		$result = $this->execute('gdc/update-wp-config', [
			'confirm' => true,
			'constants' => [
				'WP_DEBUG' => false,
				'WP_DEBUG_LOG' => 'logs/debug.log',
				'SCRIPT_DEBUG' => true,
				'UNKNOWN' => true,
			],
		]);

		$this->assertSame(['WP_DEBUG', 'WP_DEBUG_LOG', 'SCRIPT_DEBUG'], array_keys($result['updated']));
		$this->assertSame(['UNKNOWN'], $result['ignored']);
		$this->assertFalse($result['constants']['WP_DEBUG']['value']);
		$this->assertSame("'logs/debug.log'", $result['constants']['WP_DEBUG_LOG']['raw']);
		$this->assertTrue($result['constants']['SCRIPT_DEBUG']['value']);
	}

	public function testGetOptionsReturnsOptionsAndRuntimeState(): void {
		$this->refreshOptions(['enabled' => 1, 'suppress_warnings' => 1]);

		$result = $this->execute('gdc/get-options');

		$this->assertSame(1, $result['options']['suppress_warnings']);
		$this->assertArrayHasKey('error_reporting', $result['runtime']);
		$this->assertArrayHasKey('display_errors', $result['runtime']);
		$this->assertArrayHasKey('log_path', $result['runtime']);
		$this->assertArrayHasKey('mu_dropin_installed', $result['runtime']);
		$this->assertArrayHasKey('api_context', $result['runtime']);
	}
}

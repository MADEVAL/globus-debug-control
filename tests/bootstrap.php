<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function gdc_test_runtime_root(): string {
	return sys_get_temp_dir() . '/gdc-test-runtime';
}

function gdc_test_remove_tree(string $path): void {
	if (! file_exists($path)) {
		return;
	}

	if (is_file($path) || is_link($path)) {
		@unlink($path);
		return;
	}

	$items = scandir($path);
	if (false === $items) {
		return;
	}

	foreach ($items as $item) {
		if ('.' === $item || '..' === $item) {
			continue;
		}

		gdc_test_remove_tree($path . DIRECTORY_SEPARATOR . $item);
	}

	@rmdir($path);
}

function gdc_test_ensure_runtime_dirs(): void {
	$directories = [
		gdc_test_runtime_root(),
		WP_CONTENT_DIR,
		WPMU_PLUGIN_DIR,
	];

	foreach ($directories as $directory) {
		if (! is_dir($directory)) {
			mkdir($directory, 0777, true);
		}
	}
}

function gdc_test_default_state(): array {
	return [
		'options' => [
			'globus_debug_control_options' => [
				'enabled' => 0,
			],
		],
		'updated_options' => [],
		'deleted_options' => [],
		'actions' => [],
		'filters' => [],
		'removed_filters' => [],
		'deactivation_hooks' => [],
		'registered_settings' => [],
		'settings_sections' => [],
		'settings_fields' => [],
		'options_pages' => [],
		'registered_styles' => [],
		'enqueued_styles' => [],
		'enqueued_scripts' => [],
		'inline_styles' => [],
		'inline_scripts' => [],
		'deleted_files' => [],
		'current_user_can' => true,
		'admin_page_title' => 'GLOBUS Debug Control',
		'doing_ajax' => false,
		'json_request' => false,
	];
}

function gdc_test_reset_state(): void {
	$GLOBALS['gdc_test_state']  = gdc_test_default_state();
	$GLOBALS['wp_filesystem']   = null;
	$_POST = [];
	$_GET  = [];

	gdc_test_remove_tree(WP_CONTENT_DIR);
	gdc_test_ensure_runtime_dirs();
}

if (! defined('ABSPATH')) {
	define('ABSPATH', gdc_test_runtime_root() . '/wordpress/');
}

if (! defined('WP_CONTENT_DIR')) {
	define('WP_CONTENT_DIR', gdc_test_runtime_root() . '/wp-content');
}

if (! defined('WPMU_PLUGIN_DIR')) {
	define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
}

if (! defined('WP_UNINSTALL_PLUGIN')) {
	define('WP_UNINSTALL_PLUGIN', true);
}

if (! defined('FS_CHMOD_FILE')) {
	define('FS_CHMOD_FILE', 0644);
}

if (! function_exists('wp_create_nonce')) {
	function wp_create_nonce(string $action = ''): string {
		return 'test_nonce_' . md5($action);
	}
}

if (! function_exists('check_ajax_referer')) {
	function check_ajax_referer(string $action = '', $query_arg = false, bool $die = true): int {
		return 1;
	}
}

if (! function_exists('wp_send_json_success')) {
	function wp_send_json_success($data = null, int $status_code = 200): void {
		$GLOBALS['gdc_test_state']['json_response'] = array( 'success' => true, 'data' => $data );
		throw new \GDC_Test_WpDieException('wp_send_json_success');
	}
}

if (! function_exists('wp_send_json_error')) {
	function wp_send_json_error($data = null, int $status_code = 200): void {
		$GLOBALS['gdc_test_state']['json_response'] = array( 'success' => false, 'data' => $data );
		throw new \GDC_Test_WpDieException('wp_send_json_error');
	}
}

if (! function_exists('WP_Filesystem')) {
	function WP_Filesystem(): bool {
		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			$wp_filesystem = new class {
				public function get_contents(string $path): string|false {
					return @file_get_contents($path);
				}
				public function put_contents(string $path, string $content, int $mode = 0644): bool {
					$result = @file_put_contents($path, $content);
					if (false !== $result) {
						@chmod($path, $mode);
						return true;
					}
					return false;
				}
				public function delete(string $path): bool {
					return @unlink($path);
				}
			};
		}
		return true;
	}
}

if (! function_exists('gdc_test_state')) {
	function gdc_test_state(): array {
		return $GLOBALS['gdc_test_state'];
	}
}

if (! function_exists('__')) {
	function __($text, $domain = null) {
		return $text;
	}
}

if (! function_exists('esc_html__')) {
	function esc_html__($text, $domain = null) {
		return $text;
	}
}

if (! function_exists('esc_html_e')) {
	function esc_html_e($text, $domain = null): void {
		echo $text;
	}
}

if (! function_exists('esc_attr_e')) {
	function esc_attr_e($text, $domain = null): void {
		echo $text;
	}
}

if (! function_exists('esc_html')) {
	function esc_html($text) {
		return (string) $text;
	}
}

if (! function_exists('esc_attr')) {
	function esc_attr($text) {
		return (string) $text;
	}
}

if (! function_exists('esc_url')) {
	function esc_url($url) {
		return (string) $url;
	}
}

if (! function_exists('wp_kses')) {
	function wp_kses($content, $allowed_html) {
		return $content;
	}
}

if (! function_exists('__return_false')) {
	function __return_false(): bool {
		return false;
	}
}

if (! function_exists('add_action')) {
	function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool {
		$GLOBALS['gdc_test_state']['actions'][] = compact('hook', 'callback', 'priority', 'accepted_args');
		return true;
	}
}

if (! function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool {
		$GLOBALS['gdc_test_state']['filters'][] = compact('hook', 'callback', 'priority', 'accepted_args');
		return true;
	}
}

if (! function_exists('remove_filter')) {
	function remove_filter($hook, $callback, $priority = 10): bool {
		$GLOBALS['gdc_test_state']['removed_filters'][] = compact('hook', 'callback', 'priority');
		return true;
	}
}

if (! function_exists('register_deactivation_hook')) {
	function register_deactivation_hook($file, $callback): void {
		$GLOBALS['gdc_test_state']['deactivation_hooks'][] = compact('file', 'callback');
	}
}

if (! function_exists('plugin_basename')) {
	function plugin_basename($file): string {
		return basename(dirname($file)) . '/' . basename($file);
	}
}

if (! function_exists('admin_url')) {
	function admin_url($path = ''): string {
		return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
	}
}

if (! function_exists('get_option')) {
	function get_option($option, $default = false) {
		return array_key_exists($option, $GLOBALS['gdc_test_state']['options']) ? $GLOBALS['gdc_test_state']['options'][$option] : $default;
	}
}

if (! function_exists('update_option')) {
	function update_option($option, $value): bool {
		$GLOBALS['gdc_test_state']['options'][$option] = $value;
		$GLOBALS['gdc_test_state']['updated_options'][$option] = $value;
		return true;
	}
}

if (! function_exists('delete_option')) {
	function delete_option($option): bool {
		unset($GLOBALS['gdc_test_state']['options'][$option]);
		$GLOBALS['gdc_test_state']['deleted_options'][] = $option;
		return true;
	}
}

if (! function_exists('wp_parse_args')) {
	function wp_parse_args($args, $defaults = []): array {
		if (is_object($args)) {
			$args = get_object_vars($args);
		}

		if (! is_array($args)) {
			parse_str((string) $args, $args);
		}

		return array_merge($defaults, $args);
	}
}

if (! function_exists('sanitize_text_field')) {
	function sanitize_text_field($text): string {
		$text = str_replace(["\0", "\r", "\n", "\t"], '', (string) $text);
		return trim(strip_tags($text));
	}
}

if (! function_exists('wp_unslash')) {
	function wp_unslash($value) {
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return stripslashes((string) $value);
	}
}

if (! function_exists('wp_mkdir_p')) {
	function wp_mkdir_p($target): bool {
		return is_dir($target) || mkdir($target, 0777, true);
	}
}

if (! function_exists('wp_delete_file')) {
	function wp_delete_file($file): bool {
		$GLOBALS['gdc_test_state']['deleted_files'][] = $file;
		return ! file_exists($file) || unlink($file);
	}
}

if (! function_exists('register_setting')) {
	function register_setting($group, $name, $args = []): void {
		$GLOBALS['gdc_test_state']['registered_settings'][] = compact('group', 'name', 'args');
	}
}

if (! function_exists('add_settings_section')) {
	function add_settings_section($id, $title, $callback, $page): void {
		$GLOBALS['gdc_test_state']['settings_sections'][] = compact('id', 'title', 'callback', 'page');
	}
}

if (! function_exists('add_settings_field')) {
	function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = []): void {
		$GLOBALS['gdc_test_state']['settings_fields'][] = compact('id', 'title', 'callback', 'page', 'section', 'args');
	}
}

if (! function_exists('add_options_page')) {
	function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = ''): void {
		$GLOBALS['gdc_test_state']['options_pages'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug', 'callback');
	}
}

if (! function_exists('current_user_can')) {
	function current_user_can($capability): bool {
		return (bool) $GLOBALS['gdc_test_state']['current_user_can'];
	}
}

if (! function_exists('get_admin_page_title')) {
	function get_admin_page_title(): string {
		return (string) $GLOBALS['gdc_test_state']['admin_page_title'];
	}
}

if (! function_exists('settings_fields')) {
	function settings_fields($option_group): void {
	}
}

if (! function_exists('do_settings_sections')) {
	function do_settings_sections($page): void {
	}
}

if (! function_exists('submit_button')) {
	function submit_button($text = null): void {
	}
}

if (! function_exists('checked')) {
	function checked($checked, $current = true, $display = true): string {
		$result = ((string) $checked === (string) $current) ? 'checked="checked"' : '';
		if ($display) {
			echo $result;
		}
		return $result;
	}
}

if (! function_exists('selected')) {
	function selected($selected, $current = true, $display = true): string {
		$result = ((string) $selected === (string) $current) ? 'selected="selected"' : '';
		if ($display) {
			echo $result;
		}
		return $result;
	}
}

if (! function_exists('size_format')) {
	function size_format($bytes): string {
		return (string) $bytes . ' B';
	}
}

if (! function_exists('wp_enqueue_script')) {
	function wp_enqueue_script($handle, $src = false, $deps = [], $ver = false, $in_footer = false): void {
		$GLOBALS['gdc_test_state']['enqueued_scripts'][] = compact('handle', 'src', 'deps', 'ver', 'in_footer');
	}
}

if (! function_exists('wp_localize_script')) {
	function wp_localize_script(string $handle, string $name, array $data): bool {
		return true;
	}
}

if (! function_exists('wp_enqueue_code_editor')) {
	function wp_enqueue_code_editor(array $args): array {
		return [];
	}
}

if (! function_exists('esc_textarea')) {
	function esc_textarea(string $text): string {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
}

if (! function_exists('plugin_dir_url')) {
	function plugin_dir_url(string $file): string {
		return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
	}
}

if (! function_exists('wp_register_style')) {
	function wp_register_style($handle, $src = false, $deps = [], $ver = false, $media = 'all'): bool {
		$GLOBALS['gdc_test_state']['registered_styles'][] = compact('handle', 'src', 'deps', 'ver', 'media');
		return true;
	}
}

if (! function_exists('wp_enqueue_style')) {
	function wp_enqueue_style($handle): bool {
		$GLOBALS['gdc_test_state']['enqueued_styles'][] = $handle;
		return true;
	}
}

if (! function_exists('wp_add_inline_style')) {
	function wp_add_inline_style($handle, $data): bool {
		$GLOBALS['gdc_test_state']['inline_styles'][] = compact('handle', 'data');
		return true;
	}
}

if (! function_exists('wp_add_inline_script')) {
	function wp_add_inline_script($handle, $data, $position = 'after'): bool {
		$GLOBALS['gdc_test_state']['inline_scripts'][] = compact('handle', 'data', 'position');
		return true;
	}
}

if (! function_exists('wp_doing_ajax')) {
	function wp_doing_ajax(): bool {
		return (bool) $GLOBALS['gdc_test_state']['doing_ajax'];
	}
}

if (! function_exists('wp_is_json_request')) {
	function wp_is_json_request(): bool {
		return (bool) $GLOBALS['gdc_test_state']['json_request'];
	}
}

if (! function_exists('_get_cron_array')) {
	function _get_cron_array(): array {
		return $GLOBALS['gdc_test_state']['cron_array'] ?? [];
	}
}

if (! function_exists('human_time_diff')) {
	function human_time_diff(int $from, int $to = 0): string {
		$diff = abs($to - $from);
		if ($diff < 60) {
			return $diff . ' seconds';
		}
		if ($diff < 3600) {
			return (int) ($diff / 60) . ' minutes';
		}
		return (int) ($diff / 3600) . ' hours';
	}
}

if (! class_exists('GDC_Test_WpDieException')) {
	class GDC_Test_WpDieException extends \RuntimeException {}
}

if (! function_exists('wp_die')) {
	function wp_die($message = '', $title = '', $args = []): void {
		throw new \GDC_Test_WpDieException((string) $message);
	}
}

if (! function_exists('wp_nonce_url')) {
	function wp_nonce_url(string $actionurl, string $action): string {
		$sep = str_contains($actionurl, '?') ? '&' : '?';
		return $actionurl . $sep . '_wpnonce=test_nonce';
	}
}

if (! function_exists('sanitize_file_name')) {
	function sanitize_file_name(string $name): string {
		return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name) ?? $name;
	}
}

if (! class_exists('GDC_Test_RedirectException')) {
	class GDC_Test_RedirectException extends \RuntimeException {
		public string $location;
		public function __construct(string $location) {
			$this->location = $location;
			parent::__construct('Redirect to: ' . $location);
		}
	}
}

if (! function_exists('wp_safe_redirect')) {
	function wp_safe_redirect(string $location, int $status = 302): bool {
		$GLOBALS['gdc_test_state']['redirected_to'] = $location;
		throw new \GDC_Test_RedirectException($location);
	}
}

if (! function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true): string {
		$field = '<input type="hidden" name="' . esc_attr($name) . '" value="test_nonce">';
		if ($echo) {
			echo $field;
		}
		return $field;
	}
}

if (! function_exists('check_admin_referer')) {
	function check_admin_referer(string $action): int {
		return 1;
	}
}

if (! function_exists('add_query_arg')) {
	function add_query_arg($args, string $url = ''): string {
		$query = http_build_query($args);
		return $url . (str_contains((string) $url, '?') ? '&' : '?') . $query;
	}
}

if (! function_exists('wp_json_encode')) {
	function wp_json_encode($data): string {
		return (string) json_encode($data);
	}
}

if (! function_exists('disabled')) {
	function disabled($disabled, $current = true, $display = true): string {
		$result = ((string) $disabled === (string) $current) ? 'disabled="disabled"' : '';
		if ($display) {
			echo $result;
		}
		return $result;
	}
}

if (! function_exists('esc_js')) {
	function esc_js(string $text): string {
		return addslashes($text);
	}
}

if (! function_exists('_n')) {
	function _n(string $single, string $plural, int $number, string $domain = ''): string {
		return $number === 1 ? $single : $plural;
	}
}

if (! function_exists('number_format_i18n')) {
	function number_format_i18n(float $number): string {
		return number_format($number);
	}
}

gdc_test_reset_state();

require dirname(__DIR__) . '/globus-debug-control/globus-debug-control.php';

gdc_test_reset_state();
<?php
/**
 * Plugin Name: GLOBUS Debug Control
 * Description: Полный контроль режима отладки WordPress: фильтрация типов ошибок, управление выводом и логированием, удаление php-error класса. Разработчик: GLOBUS.studio.
 * Version: 2.1.0
 * Author: GLOBUS.studio
 * Author URI: https://globus.studio
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: globus-debug-control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Globus_Debug_Control {
	
	private const OPT     = 'gdc_options';
	private const VERSION = '2.1.0';
	
	private static $instance = null;
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$this->early_init();
		
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 1 );
		add_action( 'wp_loaded', [ $this, 'on_wp_loaded' ], PHP_INT_MAX );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'after_setup_theme', [ $this, 'early_body_class_filter' ], 1 );
		add_action( 'admin_head', [ $this, 'admin_remove_php_error_class' ], 1 );
		add_action( 'admin_footer', [ $this, 'admin_remove_php_error_class_js' ], 999 );
		add_action( 'wp_head', [ $this, 'frontend_remove_php_error_class' ], 1 );
		add_action( 'wp_footer', [ $this, 'frontend_remove_php_error_class_js' ], 999 );
		
		add_filter( 'body_class', [ $this, 'filter_body_class' ], PHP_INT_MAX );
		add_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ], PHP_INT_MAX );
		// Effective only as mu-plugin; as regular plugin wp_debug_mode() has already executed.
		add_filter( 'enable_wp_debug_mode_checks', '__return_false', 999 );
	}
	
	public function early_body_class_filter() {
		remove_filter( 'body_class', [ $this, 'filter_body_class' ], PHP_INT_MAX );
		remove_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ], PHP_INT_MAX );
		add_filter( 'body_class', [ $this, 'filter_body_class' ], PHP_INT_MAX );
		add_filter( 'admin_body_class', [ $this, 'filter_admin_body_class' ], PHP_INT_MAX );
	}
	
	private function early_init() {
		$opts = $this->get_options();
		
		if ( empty( $opts['enabled'] ) ) {
			return;
		}
		
		$this->apply_error_reporting( $opts );
		$this->apply_display_settings( $opts );
		$this->apply_log_settings( $opts );
	}
	
	public function plugins_loaded() {
		$opts = $this->get_options();
		
		if ( empty( $opts['enabled'] ) ) {
			return;
		}
		
		$this->apply_error_reporting( $opts );
		$this->apply_display_settings( $opts );
		$this->apply_log_settings( $opts );
	}
	
	public function on_wp_loaded() {
		$opts = $this->get_options();
		
		if ( empty( $opts['enabled'] ) ) {
			return;
		}
		
		// Re-apply in case other plugins/themes overrode error_reporting.
		$this->apply_error_reporting( $opts );
		$this->install_error_handler( $opts );
	}
	
	private function defaults(): array {
		return [
			'enabled'                    => 1,
			'suppress_notices'           => 1,
			'suppress_deprecated'        => 1,
			'suppress_warnings'          => 0,
			'suppress_strict'            => 1,
			'display_errors_mode'        => 'inherit',
			'log_enabled'                => 1,
			'log_path'                   => '',
			'log_max_size_mb'            => 10,
			'remove_php_error_bodyclass' => 1,
			'force_in_api_contexts'      => 0,
		];
	}
	
	public function get_options(): array {
		static $cached_options = null;
		
		if ( null !== $cached_options ) {
			return $cached_options;
		}
		
		$saved = get_option( self::OPT, [] );
		$opts  = wp_parse_args( $saved, $this->defaults() );
		
		$bool_keys = [
			'enabled',
			'suppress_notices',
			'suppress_deprecated',
			'suppress_warnings',
			'suppress_strict',
			'log_enabled',
			'remove_php_error_bodyclass',
			'force_in_api_contexts',
		];
		
		foreach ( $bool_keys as $key ) {
			$opts[ $key ] = (int) ! empty( $opts[ $key ] );
		}
		
		$allowed_modes = [ 'inherit', 'on', 'off' ];
		if ( ! in_array( $opts['display_errors_mode'], $allowed_modes, true ) ) {
			$opts['display_errors_mode'] = 'inherit';
		}
		
		$opts['log_max_size_mb'] = max( 1, min( 100, (int) $opts['log_max_size_mb'] ) );
		$opts['log_path']        = trim( $opts['log_path'] );
		
		$cached_options = $opts;
		return $opts;
	}
	
	private function build_suppress_mask( array $opts ): int {
		$mask = 0;
		
		if ( $opts['suppress_deprecated'] ) {
			$mask |= E_DEPRECATED | E_USER_DEPRECATED;
		}
		
		if ( $opts['suppress_notices'] ) {
			$mask |= E_NOTICE | E_USER_NOTICE;
		}
		
		if ( $opts['suppress_warnings'] ) {
			$mask |= E_WARNING | E_USER_WARNING;
		}
		
		if ( $opts['suppress_strict'] && version_compare( PHP_VERSION, '8.0.0', '<' ) && defined( 'E_STRICT' ) ) {
			$mask |= E_STRICT;
		}
		
		return $mask;
	}
	
	private function apply_error_reporting( array $opts ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		
		error_reporting( E_ALL & ~$this->build_suppress_mask( $opts ) );
	}
	
	private function install_error_handler( array $opts ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		
		$suppress_mask = $this->build_suppress_mask( $opts );
		
		if ( 0 === $suppress_mask ) {
			return;
		}
		
		$previous = set_error_handler( function( $errno, $errstr, $errfile, $errline ) use ( $suppress_mask, &$previous ) {
			if ( $errno & $suppress_mask ) {
				return true;
			}
			
			if ( is_callable( $previous ) ) {
				return call_user_func( $previous, $errno, $errstr, $errfile, $errline );
			}
			
			return false;
		} );
	}
	
	private function apply_display_settings( array $opts ) {
		$is_api_context = $this->is_api_context();
		
		$display = '0';
		
		if ( $is_api_context && empty( $opts['force_in_api_contexts'] ) ) {
			$display = '0';
		} else {
			if ( 'on' === $opts['display_errors_mode'] ) {
				$display = '1';
			} elseif ( 'off' === $opts['display_errors_mode'] ) {
				$display = '0';
			} else {
				$display = ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) ? '1' : '0';
			}
		}
		
		ini_set( 'display_errors', $display );
		ini_set( 'display_startup_errors', $display );
	}
	
	private function apply_log_settings( array $opts ) {
		if ( empty( $opts['log_enabled'] ) ) {
			ini_set( 'log_errors', '0' );
			return;
		}
		
		$log_path = $this->resolve_log_path( $opts );
		
		if ( empty( $log_path ) ) {
			$log_path = WP_CONTENT_DIR . '/debug.log';
		}
		
		$this->rotate_log_if_needed( $log_path, $opts['log_max_size_mb'] );
		
		if ( $this->ensure_log_writable( $log_path ) ) {
			ini_set( 'log_errors', '1' );
			ini_set( 'error_log', $log_path );
		}
	}
	
	private function resolve_log_path( array $opts ): string {
		if ( ! empty( $opts['log_path'] ) ) {
			$path = $opts['log_path'];
			
			if ( ! $this->is_absolute_path( $path ) ) {
				$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
			}
			
			return $this->sanitize_path( $path );
		}
		
		if ( defined( 'WP_DEBUG_LOG' ) ) {
			if ( true === WP_DEBUG_LOG || 1 === WP_DEBUG_LOG ) {
				return WP_CONTENT_DIR . '/debug.log';
			}
			
			if ( is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
				$path = WP_DEBUG_LOG;
				
				if ( ! $this->is_absolute_path( $path ) ) {
					$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/' );
				}
				
				return $this->sanitize_path( $path );
			}
		}
		
		return '';
	}
	
	private function is_absolute_path( string $path ): bool {
		return ( isset( $path[0] ) && '/' === $path[0] ) || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path );
	}
	
	private function sanitize_path( string $path ): string {
		$path = str_replace( [ "\0", "\r", "\n" ], '', $path );
		$path = str_replace( '\\', '/', $path );
		return preg_replace( '#/+#', '/', $path );
	}
	
	private function ensure_log_writable( string $log_path ): bool {
		$dir = dirname( $log_path );
		
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		
		if ( ! is_writable( $dir ) ) {
			return false;
		}
		
		if ( ! file_exists( $log_path ) ) {
			$handle = @fopen( $log_path, 'a' );
			if ( false === $handle ) {
				return false;
			}
			fclose( $handle );
			@chmod( $log_path, 0644 );
		}
		
		return is_writable( $log_path );
	}
	
	private function rotate_log_if_needed( string $log_path, int $max_size_mb ) {
		if ( ! file_exists( $log_path ) ) {
			return;
		}
		
		$size_bytes = @filesize( $log_path );
		
		if ( false === $size_bytes ) {
			return;
		}
		
		$max_bytes = $max_size_mb * 1024 * 1024;
		
		if ( $size_bytes > $max_bytes ) {
			$backup = $log_path . '.' . gmdate( 'Y-m-d_H-i-s' ) . '.old';
			@rename( $log_path, $backup );
			@touch( $log_path );
			@chmod( $log_path, 0644 );
		}
	}
	
	private function is_api_context(): bool {
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
		     ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
		     ( defined( 'MS_FILES_REQUEST' ) && MS_FILES_REQUEST ) ||
		     ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ||
		     ( defined( 'DOING_CRON' ) && DOING_CRON ) ||
		     ( defined( 'WP_CLI' ) && WP_CLI ) ||
		     ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ||
		     ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ||
		     ( isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'] ) ) {
			return true;
		}
		
		return false;
	}
	
	public function register_settings() {
		register_setting( 'globus_debug_control', self::OPT, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_options' ],
		] );
		
		add_settings_section( 'gdc_main', 'Основные настройки', '__return_false', 'globus_debug_control' );
		
		add_settings_field( 'enabled', 'Включить плагин', [ $this, 'field_enabled' ], 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'suppress', 'Фильтрация типов ошибок', [ $this, 'field_suppress' ], 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'display_errors_mode', 'Режим display_errors', [ $this, 'field_display_mode' ], 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'logging', 'Настройки логирования', [ $this, 'field_logging' ], 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'remove_php_error_bodyclass', 'Класс php-error', [ $this, 'field_body_class' ], 'globus_debug_control', 'gdc_main' );
		add_settings_field( 'force_in_api_contexts', 'API контексты', [ $this, 'field_api_contexts' ], 'globus_debug_control', 'gdc_main' );
	}
	
	public function sanitize_options( $input ): array {
		$output = $this->defaults();
		
		if ( ! is_array( $input ) ) {
			return $output;
		}
		
		$bool_fields = [
			'enabled', 'suppress_notices', 'suppress_deprecated', 'suppress_warnings',
			'suppress_strict', 'log_enabled', 'remove_php_error_bodyclass', 'force_in_api_contexts',
		];
		
		foreach ( $bool_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? 1 : 0;
		}
		
		if ( isset( $input['display_errors_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $input['display_errors_mode'] ) );
			if ( in_array( $mode, [ 'inherit', 'on', 'off' ], true ) ) {
				$output['display_errors_mode'] = $mode;
			}
		}
		
		if ( isset( $input['log_path'] ) ) {
			$output['log_path'] = sanitize_text_field( wp_unslash( $input['log_path'] ) );
		}
		
		if ( isset( $input['log_max_size_mb'] ) ) {
			$output['log_max_size_mb'] = max( 1, min( 100, (int) $input['log_max_size_mb'] ) );
		}
		
		return $output;
	}
	
	public function field_enabled() {
		$opts = $this->get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[enabled]" value="1" <?php checked( $opts['enabled'], 1 ); ?>>
			<strong>Активировать управление отладкой</strong>
		</label>
		<p class="description">Когда выключено, плагин не влияет на настройки PHP и WordPress.</p>
		<?php
	}
	
	public function field_suppress() {
		$opts   = $this->get_options();
		$is_php8 = version_compare( PHP_VERSION, '8.0.0', '>=' );
		?>
		<fieldset>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_notices]" value="1" <?php checked( $opts['suppress_notices'], 1 ); ?>>
				<strong>Убрать E_NOTICE</strong> и E_USER_NOTICE
			</label><br>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_deprecated]" value="1" <?php checked( $opts['suppress_deprecated'], 1 ); ?>>
				<strong>Убрать E_DEPRECATED</strong> и E_USER_DEPRECATED
			</label><br>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_warnings]" value="1" <?php checked( $opts['suppress_warnings'], 1 ); ?>>
				<strong>Убрать E_WARNING</strong> и E_USER_WARNING (осторожно!)
			</label><br>
			<?php if ( ! $is_php8 ) : ?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[suppress_strict]" value="1" <?php checked( $opts['suppress_strict'], 1 ); ?>>
				<strong>Убрать E_STRICT</strong>
			</label>
			<?php else : ?>
			<label style="opacity: 0.5; cursor: not-allowed;">
				<input type="checkbox" disabled>
				<strong>Убрать E_STRICT</strong> <em>(недоступно в PHP 8.0+)</em>
			</label>
			<?php endif; ?>
		</fieldset>
		<p class="description">Выбранные типы ошибок не будут отображаться и логироваться. Ошибки (E_ERROR) всегда видны.</p>
		<?php
	}
	
	public function field_display_mode() {
		$opts = $this->get_options();
		?>
		<select name="<?php echo esc_attr( self::OPT ); ?>[display_errors_mode]">
			<option value="inherit" <?php selected( $opts['display_errors_mode'], 'inherit' ); ?>>Наследовать из WP_DEBUG_DISPLAY</option>
			<option value="on" <?php selected( $opts['display_errors_mode'], 'on' ); ?>>Всегда включено</option>
			<option value="off" <?php selected( $opts['display_errors_mode'], 'off' ); ?>>Всегда выключено</option>
		</select>
		<p class="description">Управляет выводом ошибок на экран. В API контекстах вывод автоматически отключается.</p>
		<?php
	}
	
	public function field_logging() {
		$opts = $this->get_options();
		?>
		<fieldset>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[log_enabled]" value="1" <?php checked( $opts['log_enabled'], 1 ); ?>>
				<strong>Включить логирование ошибок</strong>
			</label><br><br>
			
			<label for="gdc_log_path">Путь к лог-файлу:</label><br>
			<input type="text" id="gdc_log_path" class="regular-text code" name="<?php echo esc_attr( self::OPT ); ?>[log_path]" value="<?php echo esc_attr( $opts['log_path'] ); ?>" placeholder="Оставьте пустым для автоопределения">
			<p class="description">Если пусто: используется WP_DEBUG_LOG (обычно wp-content/debug.log).<br>Можно указать абсолютный или относительный (от wp-content) путь.</p>
			
			<label for="gdc_log_max_size">Максимальный размер лога (МБ):</label><br>
			<input type="number" id="gdc_log_max_size" name="<?php echo esc_attr( self::OPT ); ?>[log_max_size_mb]" value="<?php echo esc_attr( $opts['log_max_size_mb'] ); ?>" min="1" max="100" style="width: 80px;">
			<p class="description">При превышении файл будет переименован с датой, создан новый. Диапазон: 1-100 МБ.</p>
		</fieldset>
		<?php
	}
	
	public function field_body_class() {
		$opts = $this->get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[remove_php_error_bodyclass]" value="1" <?php checked( $opts['remove_php_error_bodyclass'], 1 ); ?>>
			<strong>Удалять класс <code>php-error</code> из &lt;body&gt;</strong>
		</label>
		<p class="description">WordPress добавляет этот класс при обнаружении ошибок. Может мешать стилизации.</p>
		<?php
	}
	
	public function field_api_contexts() {
		$opts = $this->get_options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[force_in_api_contexts]" value="1" <?php checked( $opts['force_in_api_contexts'], 1 ); ?>>
			<strong>Показывать ошибки в API контекстах</strong>
		</label>
		<p class="description">По умолчанию вывод отключён в AJAX, REST API, Cron, CLI для корректной работы.<br>Включайте только для отладки API.</p>
		<?php
	}
	
	public function add_settings_page() {
		add_options_page(
			'GLOBUS Debug Control',
			'GLOBUS Debug',
			'manage_options',
			'globus-debug-control',
			[ $this, 'render_settings_page' ]
		);
	}
	
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$opts     = $this->get_options();
		$log_path = $this->resolve_log_path( $opts );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form action="options.php" method="post">
				<?php
				settings_fields( 'globus_debug_control' );
				do_settings_sections( 'globus_debug_control' );
				submit_button( 'Сохранить настройки' );
				?>
			</form>
			
			<hr>
			
			<h2>Системная информация</h2>
			<table class="widefat striped">
				<tr>
					<td style="width: 250px;"><strong>Версия плагина:</strong></td>
					<td><?php echo esc_html( self::VERSION ); ?></td>
				</tr>
				<tr>
					<td><strong>Версия PHP:</strong></td>
					<td><?php echo esc_html( PHP_VERSION ); ?></td>
				</tr>
				<tr>
					<td><strong>WP_DEBUG:</strong></td>
					<td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span style="color: #d63638;">●</span> Включен' : '<span style="color: #999;">○</span> Выключен'; ?></td>
				</tr>
				<tr>
					<td><strong>WP_DEBUG_DISPLAY:</strong></td>
					<td><?php echo defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? '<span style="color: #d63638;">●</span> Включен' : '<span style="color: #999;">○</span> Выключен'; ?></td>
				</tr>
				<tr>
					<td><strong>WP_DEBUG_LOG:</strong></td>
					<td><?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '<span style="color: #d63638;">●</span> Включен' : '<span style="color: #999;">○</span> Выключен'; ?></td>
				</tr>
				<tr>
					<td><strong>Текущий error_reporting:</strong></td>
					<td><code><?php echo esc_html( error_reporting() ); ?></code></td>
				</tr>
				<tr>
					<td><strong>display_errors:</strong></td>
					<td><code><?php echo esc_html( ini_get( 'display_errors' ) ); ?></code></td>
				</tr>
				<tr>
					<td><strong>log_errors:</strong></td>
					<td><code><?php echo esc_html( ini_get( 'log_errors' ) ); ?></code></td>
				</tr>
				<tr>
					<td><strong>Путь к лог-файлу:</strong></td>
					<td>
						<?php
						if ( $log_path ) {
							echo '<code>' . esc_html( $log_path ) . '</code>';
							if ( file_exists( $log_path ) ) {
								$size = filesize( $log_path );
								echo ' <span style="color: #2271b1;">(размер: ' . esc_html( size_format( $size ) ) . ')</span>';
							} else {
								echo ' <span style="color: #999;">(файл не существует)</span>';
							}
						} else {
							echo '<span style="color: #999;">Не настроен</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong>Контекст выполнения:</strong></td>
					<td><?php echo $this->is_api_context() ? '<span style="color: #d63638;">API контекст</span>' : '<span style="color: #2271b1;">Обычный</span>'; ?></td>
				</tr>
			</table>
			
			<p style="margin-top: 20px;"><small>© 2025 GLOBUS.studio | Версия <?php echo esc_html( self::VERSION ); ?></small></p>
		</div>
		<?php
	}
	
	public function filter_body_class( array $classes ): array {
		$opts = $this->get_options();
		if ( ! empty( $opts['remove_php_error_bodyclass'] ) && ! empty( $opts['enabled'] ) ) {
			$classes = array_values( array_filter( $classes, function( $class ) {
				return 'php-error' !== $class;
			} ) );
		}
		return $classes;
	}
	
	public function filter_admin_body_class( string $classes ): string {
		$opts = $this->get_options();
		if ( ! empty( $opts['remove_php_error_bodyclass'] ) && ! empty( $opts['enabled'] ) ) {
			$list = array_filter( preg_split( '/\s+/', trim( $classes ), -1, PREG_SPLIT_NO_EMPTY ), function( $class ) {
				return 'php-error' !== trim( $class );
			} );
			return implode( ' ', $list );
		}
		return $classes;
	}
	
	public function admin_remove_php_error_class() {
		$opts = $this->get_options();
		if ( empty( $opts['remove_php_error_bodyclass'] ) || empty( $opts['enabled'] ) ) {
			return;
		}
		echo '<style id="gdc-remove-php-error">body.php-error{}</style>';
	}
	
	public function admin_remove_php_error_class_js() {
		$opts = $this->get_options();
		if ( empty( $opts['remove_php_error_bodyclass'] ) || empty( $opts['enabled'] ) ) {
			return;
		}
		?>
		<script id="gdc-remove-php-error-js">
		(function() {
			function removePhpErrorClass() {
				var body = document.body || document.getElementsByTagName('body')[0];
				if (body && body.classList.contains('php-error')) {
					body.classList.remove('php-error');
				}
			}
			
			// Удаляем сразу
			removePhpErrorClass();
			
			// Удаляем после загрузки DOM
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', removePhpErrorClass);
			}
			
			// Удаляем после полной загрузки
			window.addEventListener('load', removePhpErrorClass);
			
			// Следим за изменениями (на случай динамического добавления)
			if (window.MutationObserver) {
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
							removePhpErrorClass();
						}
					});
				});
				
				var body = document.body || document.getElementsByTagName('body')[0];
				if (body) {
					observer.observe(body, { attributes: true, attributeFilter: ['class'] });
				}
			}
			
			// Дополнительная проверка каждые 100ms первые 3 секунды
			var checks = 0;
			var interval = setInterval(function() {
				removePhpErrorClass();
				checks++;
				if (checks >= 30) {
					clearInterval(interval);
				}
			}, 100);
		})();
		</script>
		<?php
	}
	
	public function frontend_remove_php_error_class() {
		$opts = $this->get_options();
		if ( empty( $opts['remove_php_error_bodyclass'] ) || empty( $opts['enabled'] ) ) {
			return;
		}
		echo '<style id="gdc-remove-php-error">body.php-error{}</style>';
	}
	
	public function frontend_remove_php_error_class_js() {
		$opts = $this->get_options();
		if ( empty( $opts['remove_php_error_bodyclass'] ) || empty( $opts['enabled'] ) ) {
			return;
		}
		?>
		<script id="gdc-remove-php-error-js">
		(function() {
			function removePhpErrorClass() {
				var body = document.body || document.getElementsByTagName('body')[0];
				if (body && body.classList.contains('php-error')) {
					body.classList.remove('php-error');
				}
			}
			
			removePhpErrorClass();
			
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', removePhpErrorClass);
			}
			
			window.addEventListener('load', removePhpErrorClass);
			
			if (window.MutationObserver) {
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
							removePhpErrorClass();
						}
					});
				});
				
				var body = document.body || document.getElementsByTagName('body')[0];
				if (body) {
					observer.observe(body, { attributes: true, attributeFilter: ['class'] });
				}
			}
		})();
		</script>
		<?php
	}
}

Globus_Debug_Control::instance();
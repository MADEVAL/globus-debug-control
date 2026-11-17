<?php
/**
 * GLOBUS Debug Control - MCP / Abilities API integration
 *
 * Registers abilities that expose the plugin options, error log and
 * wp-config debug constants to MCP and REST clients.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ability registration for GLOBUS Debug Control.
 */
final class GDC_Abilities {

	/**
	 * Ability category slug.
	 */
	private const CATEGORY = 'gdc-debug';

	/**
	 * Maximum number of log lines returned in one call.
	 */
	private const MAX_LOG_LINES = 1000;

	/**
	 * Registers the ability category.
	 */
	public static function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'GDC: debug control', 'globus-debug-control' ),
				'description' => __( 'Read and change debug options, error logs and wp-config debug constants.', 'globus-debug-control' ),
			)
		);
	}

	/**
	 * Registers all plugin abilities.
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::add(
			'gdc/get-schema',
			array(
				'label'            => __( 'Get debug control schema', 'globus-debug-control' ),
				'description'      => __( 'Returns every plugin option with its type and default, plus the wp-config debug constants and the list of GDC abilities. Call this first to discover field names.', 'globus-debug-control' ),
				'input_schema'     => self::empty_schema(),
				'execute_callback' => static function (): array {
					return array(
						'options'   => self::options_schema(),
						'constants' => self::wp_config_schema(),
						'abilities' => array(
							'gdc/get-schema',
							'gdc/get-options',
							'gdc/update-options',
							'gdc/reset-options',
							'gdc/get-log',
							'gdc/clear-log',
							'gdc/get-wp-config',
							'gdc/update-wp-config',
						),
						'log_lines' => self::MAX_LOG_LINES,
					);
				},
				'meta'             => self::meta( true ),
			)
		);

		self::add(
			'gdc/get-options',
			array(
				'label'            => __( 'Get debug control options', 'globus-debug-control' ),
				'description'      => __( 'Returns all plugin options and the effective runtime state: error_reporting, display_errors, log_errors, error_log path, resolved log file, mu-plugin dropin status and API context.', 'globus-debug-control' ),
				'input_schema'     => self::empty_schema(),
				'execute_callback' => static function (): array {
					$plugin = \Globus_Debug_Control::instance();

					return array(
						'options' => $plugin->get_options( true ),
						'runtime' => $plugin->get_runtime_state(),
					);
				},
				'meta'             => self::meta( true ),
			)
		);

		self::add(
			'gdc/update-options',
			array(
				'label'            => __( 'Update debug control options', 'globus-debug-control' ),
				'description'      => __( 'Updates any plugin option by key. Only provided keys change; booleans are real booleans. Settings are sanitised and applied immediately. Setting install_mu_dropin installs or removes the early-loader dropin.', 'globus-debug-control' ),
				'input_schema'     => self::options_input_schema(),
				'execute_callback' => static function ( $input = array() ) {
					$input = is_array( $input ) ? $input : array();
					$patch = array_intersect_key( $input, self::option_definitions() );

					if ( empty( $patch ) ) {
						return new \WP_Error( 'gdc_empty_patch', __( 'No known option keys supplied.', 'globus-debug-control' ) );
					}

					$plugin = \Globus_Debug_Control::instance();

					return array(
						'updated' => array_keys( $patch ),
						'options' => $plugin->update_options( $patch ),
						'runtime' => $plugin->get_runtime_state(),
					);
				},
				'meta'             => self::meta( false, false, true ),
			)
		);

		self::add(
			'gdc/reset-options',
			array(
				'label'            => __( 'Reset debug control options', 'globus-debug-control' ),
				'description'      => __( 'Restores every plugin option to its default value and reapplies runtime settings. Requires confirm=true.', 'globus-debug-control' ),
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'confirm' => array(
							'type'        => 'boolean',
							'description' => __( 'Must be true to reset all options.', 'globus-debug-control' ),
						),
					),
					'required'             => array( 'confirm' ),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'execute_callback' => static function ( $input = array() ) {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['confirm'] ) ) {
						return new \WP_Error( 'gdc_confirm_required', __( 'Pass confirm=true to reset all options.', 'globus-debug-control' ) );
					}

					return array( 'options' => \Globus_Debug_Control::instance()->reset_options() );
				},
				'meta'             => self::meta( false, true, true ),
			)
		);

		self::add(
			'gdc/get-log',
			array(
				'label'            => __( 'Read error log', 'globus-debug-control' ),
				'description'      => __( 'Returns the tail of the configured error log with a severity level per line. Optionally filters lines by a case-insensitive substring.', 'globus-debug-control' ),
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'lines'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => self::MAX_LOG_LINES,
							'default'     => 200,
							'description' => __( 'Number of lines to return from the end of the file.', 'globus-debug-control' ),
						),
						'contains' => array(
							'type'        => 'string',
							'description' => __( 'Optional case-insensitive substring filter.', 'globus-debug-control' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'execute_callback' => static function ( $input = array() ) {
					$input    = is_array( $input ) ? $input : array();
					$limit    = isset( $input['lines'] ) ? (int) $input['lines'] : 200;
					$limit    = max( 1, min( self::MAX_LOG_LINES, $limit ) );
					$contains = isset( $input['contains'] ) ? (string) $input['contains'] : '';
					$path     = \GDC_Log_Viewer::get_log_path();
					$read     = self::read_tail( $path, '' === $contains ? $limit : self::MAX_LOG_LINES );
					$lines    = array();

					foreach ( $read[0] as $line ) {
						if ( '' !== $contains && false === stripos( $line, $contains ) ) {
							continue;
						}

						$lines[] = array(
							'text'  => $line,
							'level' => \GDC_Log_Viewer::classify_line( $line ),
						);
					}

					$lines = array_slice( $lines, -$limit );

					return array(
						'path'      => $path,
						'exists'    => file_exists( $path ),
						'size'      => file_exists( $path ) ? (int) @filesize( $path ) : 0,
						'mtime'     => file_exists( $path ) ? (int) @filemtime( $path ) : 0,
						'lines'     => $lines,
						'truncated' => $read[1],
					);
				},
				'meta'             => self::meta( true ),
			)
		);

		self::add(
			'gdc/clear-log',
			array(
				'label'            => __( 'Clear error log', 'globus-debug-control' ),
				'description'      => __( 'Truncates the configured error log file. Requires confirm=true.', 'globus-debug-control' ),
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'confirm' => array(
							'type'        => 'boolean',
							'description' => __( 'Must be true to truncate the log file.', 'globus-debug-control' ),
						),
					),
					'required'             => array( 'confirm' ),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'execute_callback' => static function ( $input = array() ) {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['confirm'] ) ) {
						return new \WP_Error( 'gdc_confirm_required', __( 'Pass confirm=true to clear the log file.', 'globus-debug-control' ) );
					}

					$path = \GDC_Log_Viewer::get_log_path();

					if ( ! file_exists( $path ) ) {
						return array(
							'path'    => $path,
							'cleared' => true,
							'bytes'   => 0,
						);
					}

					$size = (int) @filesize( $path );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct truncation of the configured log file.
					$ok = false !== @file_put_contents( $path, '' );

					if ( ! $ok ) {
						return new \WP_Error( 'gdc_log_clear_failed', __( 'The log file could not be truncated.', 'globus-debug-control' ) );
					}

					return array(
						'path'    => $path,
						'cleared' => true,
						'bytes'   => $size,
					);
				},
				'meta'             => self::meta( false, true, true ),
			)
		);

		self::add(
			'gdc/get-wp-config',
			array(
				'label'            => __( 'Get wp-config debug constants', 'globus-debug-control' ),
				'description'      => __( 'Returns the located wp-config.php path and the current value of WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG and SAVEQUERIES.', 'globus-debug-control' ),
				'input_schema'     => self::empty_schema(),
				'execute_callback' => static function () {
					$path = \GDC_WpConfig_Debug::find_wp_config();

					if ( '' === $path ) {
						return new \WP_Error( 'gdc_wp_config_missing', __( 'wp-config.php was not found.', 'globus-debug-control' ) );
					}

					return array(
						'path'      => $path,
						'writable'  => wp_is_writable( $path ),
						'constants' => \GDC_WpConfig_Debug::read_constants( $path ),
					);
				},
				'meta'             => self::meta( true ),
			)
		);

		self::add(
			'gdc/update-wp-config',
			array(
				'label'            => __( 'Update wp-config debug constants', 'globus-debug-control' ),
				'description'      => __( 'Writes debug constants into wp-config.php. Accepts a constants object with any of WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES. WP_DEBUG_LOG also accepts a log file path. Requires confirm=true.', 'globus-debug-control' ),
				'input_schema'     => array(
					'type'                 => 'object',
					'properties'           => array(
						'constants' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'description'          => __( 'Map of constant name to boolean, or to a string for WP_DEBUG_LOG.', 'globus-debug-control' ),
						),
						'confirm'   => array(
							'type'        => 'boolean',
							'description' => __( 'Must be true to modify wp-config.php.', 'globus-debug-control' ),
						),
					),
					'required'             => array( 'constants', 'confirm' ),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'execute_callback' => static function ( $input = array() ) {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['confirm'] ) ) {
						return new \WP_Error( 'gdc_confirm_required', __( 'Pass confirm=true to modify wp-config.php.', 'globus-debug-control' ) );
					}

					$constants = isset( $input['constants'] ) && is_array( $input['constants'] ) ? $input['constants'] : array();

					if ( empty( $constants ) ) {
						return new \WP_Error( 'gdc_empty_patch', __( 'No constants supplied.', 'globus-debug-control' ) );
					}

					$path = \GDC_WpConfig_Debug::find_wp_config();

					if ( '' === $path || ! wp_is_writable( $path ) ) {
						return new \WP_Error( 'gdc_wp_config_not_writable', __( 'wp-config.php is missing or not writable.', 'globus-debug-control' ) );
					}

					$backup = \GDC_WpConfig_Debug::backup_wp_config( $path );
					$schema = self::wp_config_schema();
					$updated = array();
					$ignored = array();

					foreach ( $constants as $name => $value ) {
						$name = (string) $name;

						if ( ! isset( $schema[ $name ] ) ) {
							$ignored[] = $name;
							continue;
						}

						if ( 'bool_or_string' === $schema[ $name ]['type'] && is_string( $value ) ) {
							$path_value = trim( sanitize_text_field( $value ) );
							$raw        = '' === $path_value ? 'true' : "'" . addslashes( $path_value ) . "'";
						} else {
							$raw = $value ? 'true' : 'false';
						}

						if ( ! \GDC_WpConfig_Debug::set_constant( $path, $name, $raw ) ) {
							return new \WP_Error(
								'gdc_wp_config_write_failed',
								/* translators: %s: constant name. */
								sprintf( __( 'Could not update %s in wp-config.php.', 'globus-debug-control' ), $name )
							);
						}

						$updated[ $name ] = $value;
					}

					return array(
						'path'      => $path,
						'backup'    => $backup,
						'updated'   => $updated,
						'ignored'   => $ignored,
						'constants' => \GDC_WpConfig_Debug::read_constants( $path ),
					);
				},
				'meta'             => self::meta( false, false, true ),
			)
		);
	}

	/**
	 * Registers a single ability with a safe default input schema.
	 *
	 * @param string              $name Ability name.
	 * @param array<string,mixed> $args Ability arguments.
	 */
	private static function add( string $name, array $args ): void {
		if ( ! isset( $args['input_schema'] ) || ! is_array( $args['input_schema'] ) ) {
			$args['input_schema'] = array( 'type' => 'object' );
		}

		if ( ! isset( $args['input_schema']['default'] ) ) {
			$args['input_schema']['default'] = array();
		}

		if ( ! isset( $args['permission_callback'] ) ) {
			$args['permission_callback'] = static function (): bool {
				return current_user_can( \Globus_Debug_Control::manage_capability() );
			};
		}

		if ( ! isset( $args['category'] ) ) {
			$args['category'] = self::CATEGORY;
		}

		wp_register_ability( $name, $args );
	}

	/**
	 * Builds ability meta annotations for MCP and REST exposure.
	 *
	 * @param bool $is_readonly Ability does not change anything.
	 * @param bool $destructive Ability can delete data.
	 * @param bool $idempotent  Repeated calls have no extra effect.
	 * @return array<string,mixed>
	 */
	private static function meta( bool $is_readonly, bool $destructive = false, bool $idempotent = true ): array {
		return array(
			'annotations'  => array(
				'readonly'    => $is_readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
		);
	}

	/**
	 * Empty input schema (object without properties).
	 *
	 * @return array<string,mixed>
	 */
	private static function empty_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * Input schema for the options patch ability.
	 *
	 * @return array<string,mixed>
	 */
	private static function options_input_schema(): array {
		$properties = array();

		foreach ( self::option_definitions() as $key => $definition ) {
			$property = array( 'type' => $definition['type'] );

			if ( ! empty( $definition['description'] ) ) {
				$property['description'] = $definition['description'];
			}

			if ( isset( $definition['enum'] ) ) {
				$property['enum'] = $definition['enum'];
			}

			if ( isset( $definition['minimum'] ) ) {
				$property['minimum'] = $definition['minimum'];
			}

			if ( isset( $definition['maximum'] ) ) {
				$property['maximum'] = $definition['maximum'];
			}

			$properties[ $key ] = $property;
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * Human-readable option schema for the get-schema ability.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function options_schema(): array {
		$schema = array();

		foreach ( self::option_definitions() as $key => $definition ) {
			$schema[ $key ] = array(
				'type'        => $definition['type'],
				'default'     => $definition['default'],
				'description' => $definition['description'],
			);

			if ( isset( $definition['enum'] ) ) {
				$schema[ $key ]['enum'] = $definition['enum'];
			}

			if ( isset( $definition['minimum'] ) ) {
				$schema[ $key ]['minimum'] = $definition['minimum'];
			}

			if ( isset( $definition['maximum'] ) ) {
				$schema[ $key ]['maximum'] = $definition['maximum'];
			}
		}

		return $schema;
	}

	/**
	 * Option definitions mirroring the settings form.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function option_definitions(): array {
		return array(
			'enabled'                    => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Master switch: apply any plugin settings at all.', 'globus-debug-control' ),
			),
			'suppress_notices'           => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Suppress E_NOTICE and E_USER_NOTICE.', 'globus-debug-control' ),
			),
			'suppress_deprecated'        => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Suppress E_DEPRECATED and E_USER_DEPRECATED.', 'globus-debug-control' ),
			),
			'suppress_warnings'          => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Suppress E_WARNING and E_USER_WARNING.', 'globus-debug-control' ),
			),
			'suppress_strict'            => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Suppress E_STRICT (PHP below 8.0 only).', 'globus-debug-control' ),
			),
			'display_errors_mode'        => array(
				'type'        => 'string',
				'default'     => 'inherit',
				'enum'        => array( 'inherit', 'on', 'off' ),
				'description' => __( 'On-screen error display: inherit from WP_DEBUG_DISPLAY, always on, or always off.', 'globus-debug-control' ),
			),
			'log_enabled'                => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Write PHP errors and error_log() calls to the log file. Disabling also points error_log at the OS null device.', 'globus-debug-control' ),
			),
			'log_path'                   => array(
				'type'        => 'string',
				'default'     => '',
				'description' => __( 'Absolute log file path or path relative to wp-content. Empty uses WP_DEBUG_LOG or wp-content/debug.log.', 'globus-debug-control' ),
			),
			'log_max_size_mb'            => array(
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
				'description' => __( 'Rotate the log file when it exceeds this size in megabytes.', 'globus-debug-control' ),
			),
			'remove_php_error_bodyclass' => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Remove the php-error CSS class from body.', 'globus-debug-control' ),
			),
			'force_in_api_contexts'      => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Keep error display enabled in AJAX, REST, cron and CLI contexts.', 'globus-debug-control' ),
			),
			'install_mu_dropin'          => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Install the early-loader mu-plugin dropin so settings apply before regular plugins load.', 'globus-debug-control' ),
			),
		);
	}

	/**
	 * WP-CONFIG debug constants supported by the plugin.
	 *
	 * @return array<string,array<string,string>>
	 */
	private static function wp_config_schema(): array {
		return array(
			'WP_DEBUG'         => array(
				'type'  => 'bool',
				'label' => 'WP_DEBUG',
			),
			'WP_DEBUG_LOG'     => array(
				'type'  => 'bool_or_string',
				'label' => 'WP_DEBUG_LOG',
			),
			'WP_DEBUG_DISPLAY' => array(
				'type'  => 'bool',
				'label' => 'WP_DEBUG_DISPLAY',
			),
			'SCRIPT_DEBUG'     => array(
				'type'  => 'bool',
				'label' => 'SCRIPT_DEBUG',
			),
			'SAVEQUERIES'      => array(
				'type'  => 'bool',
				'label' => 'SAVEQUERIES',
			),
		);
	}

	/**
	 * Reads the last lines of a file without loading the whole file.
	 *
	 * @param string $path  File path.
	 * @param int    $limit Maximum number of lines.
	 * @return array{0: string[], 1: bool} Lines and whether older lines were dropped.
	 */
	private static function read_tail( string $path, int $limit ): array {
		if ( ! file_exists( $path ) ) {
			return array( array(), false );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Low-level tail read for large log files.
		$handle = @fopen( $path, 'rb' );

		if ( false === $handle ) {
			return array( array(), false );
		}

		$buffer = '';
		$chunk  = 8192;

		fseek( $handle, 0, SEEK_END );
		$pos = (int) ftell( $handle );

		while ( $pos > 0 && substr_count( $buffer, "\n" ) <= $limit ) {
			$read = min( $chunk, $pos );
			$pos -= $read;
			fseek( $handle, $pos );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Low-level tail read for large log files.
			$data = fread( $handle, $read );

			if ( false === $data ) {
				break;
			}

			$buffer = $data . $buffer;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$all = preg_split( '/\r\n|\r|\n/', $buffer );

		if ( ! is_array( $all ) ) {
			$all = array();
		}

		if ( $all && '' === end( $all ) ) {
			array_pop( $all );
		}

		$truncated = count( $all ) > $limit;
		$lines     = array_slice( $all, -$limit );

		return array( array_values( $lines ), $truncated );
	}
}

add_action( 'wp_abilities_api_categories_init', array( GDC_Abilities::class, 'register_categories' ) );
add_action( 'wp_abilities_api_init', array( GDC_Abilities::class, 'register' ) );

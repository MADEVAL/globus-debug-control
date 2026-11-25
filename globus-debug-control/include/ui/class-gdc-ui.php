<?php
/**
 * Reusable admin UI fragments for the GLOBUS design system.
 *
 * Templates stay readable: escaping and markup live here.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Markup helpers for the plugin admin screens.
 */
final class GDC_UI {

	/**
	 * Renders the inline SVG sprite once per page.
	 */
	public static function sprite(): void {
		$renderer = require GDC_INCLUDES . '/ui/icons-sprite.php';

		if ( is_callable( $renderer ) ) {
			$renderer();
		}
	}

	/**
	 * Returns the admin page URL, optionally with extra query arguments.
	 *
	 * @param array<string, mixed> $args Extra query arguments.
	 * @return string
	 */
	public static function page_url( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => 'globus-debug-control' ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns the URL of a specific module tab.
	 *
	 * @param string               $module Module id.
	 * @param array<string, mixed> $args   Extra query arguments.
	 * @return string
	 */
	public static function module_url( string $module, array $args = array() ): string {
		return self::page_url( array_merge( array( 'tab' => $module ), $args ) );
	}

	/**
	 * Module registry: one source for tabs, sidebar nav and search.
	 *
	 * @return array<string, array{label: string, icon: string, group: string}>
	 */
	public static function modules(): array {
		return array(
			'overview'   => array(
				'label' => __( 'Overview', 'globus-debug-control' ),
				'desc'  => __( 'Health, log and diagnostics at a glance.', 'globus-debug-control' ),
				'icon'  => 'gauge',
				'group' => 'overview',
			),
			'settings'   => array(
				'label' => __( 'Settings', 'globus-debug-control' ),
				'desc'  => __( 'Filter PHP errors, control display and logging.', 'globus-debug-control' ),
				'icon'  => 'gear',
				'group' => 'control',
			),
			'wpcfg'      => array(
				'label' => __( 'WP_DEBUG', 'globus-debug-control' ),
				'desc'  => __( 'Toggle wp-config.php constants without editing files.', 'globus-debug-control' ),
				'icon'  => 'debug',
				'group' => 'control',
			),
			'log'        => array(
				'label' => __( 'Error Log', 'globus-debug-control' ),
				'desc'  => __( 'Read, search and clear the PHP error log.', 'globus-debug-control' ),
				'icon'  => 'code',
				'group' => 'diagnostics',
			),
			'cron'       => array(
				'label' => __( 'Cron Tasks', 'globus-debug-control' ),
				'desc'  => __( 'Inspect scheduled events and remove orphaned hooks.', 'globus-debug-control' ),
				'icon'  => 'clock',
				'group' => 'diagnostics',
			),
			'transients' => array(
				'label' => __( 'Transients', 'globus-debug-control' ),
				'desc'  => __( 'List stored transients and purge expired rows.', 'globus-debug-control' ),
				'icon'  => 'transient',
				'group' => 'diagnostics',
			),
			'autoloaded' => array(
				'label' => __( 'Autoloaded Options', 'globus-debug-control' ),
				'desc'  => __( 'Find options that inflate every page load.', 'globus-debug-control' ),
				'icon'  => 'database',
				'group' => 'diagnostics',
			),
			'http'       => array(
				'label' => __( 'HTTP Log', 'globus-debug-control' ),
				'desc'  => __( 'Review outgoing HTTP API requests and failures.', 'globus-debug-control' ),
				'icon'  => 'globe',
				'group' => 'diagnostics',
			),
			'constants'  => array(
				'label' => __( 'Constants', 'globus-debug-control' ),
				'desc'  => __( 'Browse defined PHP constants with masked secrets.', 'globus-debug-control' ),
				'icon'  => 'list',
				'group' => 'diagnostics',
			),
		);
	}

	/**
	 * Sidebar navigation groups for the current tab.
	 *
	 * @param string $current Active module id.
	 * @return array<int, array{title: string, items: array<int, array{id: string, label: string, icon: string, url: string, active: bool}>}>
	 */
	public static function nav( string $current ): array {
		$groups = array(
			'overview'    => array(
				'title' => '',
				'items' => array(),
			),
			'control'     => array(
				'title' => __( 'Control', 'globus-debug-control' ),
				'items' => array(),
			),
			'diagnostics' => array(
				'title' => __( 'Diagnostics', 'globus-debug-control' ),
				'items' => array(),
			),
		);

		foreach ( self::modules() as $id => $module ) {
			if ( ! isset( $groups[ $module['group'] ] ) ) {
				continue;
			}

			$groups[ $module['group'] ]['items'][] = array(
				'id'     => $id,
				'label'  => $module['label'],
				'icon'   => $module['icon'],
				'url'    => self::module_url( $id ),
				'active' => $id === $current,
			);
		}

		return array_values( $groups );
	}

	/**
	 * Search index for the command palette.
	 *
	 * @return array<int, array{t: string, g: string, i: string, u: string, k: string}>
	 */
	public static function search_index(): array {
		$index = array();
		$nav   = self::nav( '' );

		foreach ( $nav as $group ) {
			foreach ( $group['items'] as $item ) {
				$index[] = array(
					't' => $item['label'],
					'g' => '' !== (string) $group['title'] ? (string) $group['title'] : (string) $item['label'],
					'i' => $item['icon'],
					'u' => $item['url'],
					'k' => strtolower( $item['id'] ),
				);
			}
		}

		$anchors = array(
			'gdc-setting-enabled'   => array( __( 'Enable plugin', 'globus-debug-control' ), 'bolt' ),
			'gdc-setting-filtering' => array( __( 'Error type filtering', 'globus-debug-control' ), 'sliders' ),
			'gdc-setting-display'   => array( __( 'display_errors mode', 'globus-debug-control' ), 'eye' ),
			'gdc-setting-logging'   => array( __( 'Error logging', 'globus-debug-control' ), 'doc' ),
			'gdc-setting-logpath'   => array( __( 'Log file path', 'globus-debug-control' ), 'path' ),
			'gdc-setting-logsize'   => array( __( 'Maximum log size', 'globus-debug-control' ), 'memory' ),
			'gdc-setting-bodyclass' => array( __( 'php-error body class', 'globus-debug-control' ), 'code' ),
			'gdc-setting-api'       => array( __( 'API contexts', 'globus-debug-control' ), 'server' ),
			'gdc-setting-mu'        => array( __( 'Early error interception', 'globus-debug-control' ), 'bolt' ),
			'gdc-setting-system'    => array( __( 'System information', 'globus-debug-control' ), 'system' ),
		);

		foreach ( $anchors as $anchor => $meta ) {
			$index[] = array(
				't' => $meta[0],
				'g' => __( 'Settings', 'globus-debug-control' ),
				'i' => $meta[1],
				'u' => self::module_url( 'settings' ) . '#' . $anchor,
				'k' => '',
			);
		}

		return $index;
	}

	/**
	 * SVG sprite icon.
	 *
	 * @param string $name        Icon name, without the gdc-i- prefix.
	 * @param string $extra_class Extra class, e.g. gdc-ico--md.
	 * @return string
	 */
	public static function icon( string $name, string $extra_class = 'gdc-ico--sm' ): string {
		$name = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $name );

		return '<svg class="gdc-ico ' . esc_attr( $extra_class ) . '" aria-hidden="true" focusable="false"><use href="#gdc-i-' . esc_attr( $name ) . '"></use></svg>';
	}

	/**
	 * Escaped translated text.
	 *
	 * @param string $text Source string.
	 * @return string
	 */
	public static function te( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Status chip.
	 *
	 * @param bool   $on        State.
	 * @param string $on_label  Label when on.
	 * @param string $off_label Label when off.
	 * @return string
	 */
	public static function status_chip( bool $on, string $on_label = '', string $off_label = '' ): string {
		$on_label  = '' !== $on_label ? $on_label : __( 'On', 'globus-debug-control' );
		$off_label = '' !== $off_label ? $off_label : __( 'Off', 'globus-debug-control' );

		return '<span class="gdc-chip ' . ( $on ? 'gdc-chip--green' : 'gdc-chip--amber' ) . '">'
			. '<span class="gdc-dot ' . ( $on ? '' : 'gdc-dot--amber' ) . '"></span>'
			. esc_html( $on ? $on_label : $off_label )
			. '</span>';
	}

	/**
	 * Chip with a colour variant.
	 *
	 * @param string $text    Text.
	 * @param string $variant green|amber|red|blue.
	 * @return string
	 */
	public static function chip( string $text, string $variant = 'blue' ): string {
		$allowed = array( 'green', 'amber', 'red', 'blue' );
		$variant = in_array( $variant, $allowed, true ) ? $variant : 'blue';

		return '<span class="gdc-chip gdc-chip--' . esc_attr( $variant ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Alert block.
	 *
	 * @param string $html    Escaped inner HTML.
	 * @param string $variant success|info|warning|danger.
	 * @return string
	 */
	public static function alert( string $html, string $variant = 'info' ): string {
		$allowed = array( 'success', 'info', 'warning', 'danger' );
		$variant = in_array( $variant, $allowed, true ) ? $variant : 'info';

		return '<div class="gdc-alert gdc-alert--' . esc_attr( $variant ) . '" role="status">' . $html . '</div>';
	}

	/**
	 * Inline help tooltip.
	 *
	 * @param string $text Tooltip text.
	 * @return string
	 */
	public static function help( string $text ): string {
		return '<span class="gdc-help"><span class="gdc-help-q" role="button" tabindex="0" aria-label="'
			. esc_attr__( 'Help', 'globus-debug-control' ) . '">?</span>'
			. '<span class="gdc-help-tip">' . esc_html( $text ) . '</span></span>';
	}

	/**
	 * Field label with an optional tooltip.
	 *
	 * @param string $label Label text.
	 * @param string $help  Tooltip text.
	 * @return string
	 */
	public static function field_label( string $label, string $help = '' ): string {
		return '<span class="gdc-field-label"><span>' . esc_html( $label ) . '</span>'
			. ( '' !== $help ? self::help( $help ) : '' ) . '</span>';
	}

	/**
	 * Checkbox styled as a switch.
	 *
	 * @param string $name     Input name.
	 * @param bool   $checked  Checked state.
	 * @param string $id       Element id.
	 * @param string $color    '', green or amber.
	 * @param bool   $disabled Disabled state.
	 * @param string $label    Accessible label.
	 * @return string
	 */
	public static function switch_control( string $name, bool $checked, string $id = '', string $color = '', bool $disabled = false, string $label = '' ): string {
		$classes = 'gdc-switch' . ( '' !== $color ? ' gdc-switch--' . $color : '' );
		$id      = '' !== $id ? $id : 'gdc-sw-' . (string) preg_replace( '/[^a-z0-9]+/i', '-', $name );

		$html  = '<label class="' . esc_attr( $classes ) . '">';
		$html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"'
			. checked( $checked, true, false ) . disabled( $disabled, true, false )
			. ' aria-label="' . esc_attr( $label ) . '" />';
		$html .= '<span class="gdc-switch-track"></span>';
		$html .= '</label>';

		return $html;
	}

	/**
	 * Full settings row with a switch on the right.
	 *
	 * @param string               $name     Input name.
	 * @param bool                 $checked  Checked state.
	 * @param string               $label    Row label.
	 * @param string               $desc     Row description.
	 * @param array<string, mixed> $opts Optional: id, color, disabled, help, value_label.
	 * @return string
	 */
	public static function switch_row( string $name, bool $checked, string $label, string $desc = '', array $opts = array() ): string {
		$id    = isset( $opts['id'] ) ? (string) $opts['id'] : '';
		$color = isset( $opts['color'] ) ? (string) $opts['color'] : '';
		$dis   = ! empty( $opts['disabled'] );
		$help  = isset( $opts['help'] ) ? (string) $opts['help'] : '';
		$value = isset( $opts['value_label'] ) ? (string) $opts['value_label'] : '';

		$html  = '<div class="gdc-togglerow">';
		$html .= '<div class="gdc-togglerow-main">';
		$html .= '<div class="gdc-togglerow-label">' . esc_html( $label ) . ( '' !== $help ? ' ' . self::help( $help ) : '' ) . '</div>';
		if ( '' !== $desc ) {
			$html .= '<div class="gdc-togglerow-desc">' . esc_html( $desc ) . '</div>';
		}
		$html .= '</div>';
		if ( '' !== $value ) {
			$html .= '<span class="gdc-fs-xs ' . ( $checked ? 'gdc-tx-green' : 'gdc-dim' ) . '">' . esc_html( $value ) . '</span>';
		}
		$html .= self::switch_control( $name, $checked, $id, $color, $dis, $label );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Empty state block.
	 *
	 * @param string $text Message.
	 * @param string $icon Icon name.
	 * @return string
	 */
	public static function empty_state( string $text, string $icon = 'search' ): string {
		return '<div class="gdc-empty">' . self::icon( $icon, 'gdc-ico--lg' ) . '<span>' . esc_html( $text ) . '</span></div>';
	}

	/**
	 * Key/value row.
	 *
	 * @param string $label    Label.
	 * @param string $value    Pre-escaped value HTML.
	 * @param bool   $mono     Use monospace for the value.
	 * @return string
	 */
	public static function kv( string $label, string $value, bool $mono = false ): string {
		return '<div class="gdc-kv"><span class="gdc-muted">' . esc_html( $label ) . '</span>'
			. '<b class="' . ( $mono ? 'gdc-mono' : '' ) . '">' . $value . '</b></div>';
	}

	/**
	 * Boolean value rendered as a dot plus label.
	 *
	 * @param bool   $on    State.
	 * @param string $label Optional label override.
	 * @return string
	 */
	public static function bool_text( bool $on, string $label = '' ): string {
		if ( '' === $label ) {
			$label = $on ? __( 'Enabled', 'globus-debug-control' ) : __( 'Disabled', 'globus-debug-control' );
		}

		return '<span class="' . ( $on ? 'gdc-tx-green' : 'gdc-dim' ) . '">'
			. '<span class="gdc-dot ' . ( $on ? '' : 'gdc-dot--grey' ) . '"></span> '
			. esc_html( $label ) . '</span>';
	}

	/**
	 * Conic-gradient score gauge.
	 *
	 * @param int    $score 0-100.
	 * @param string $label Caption under the number.
	 * @return string
	 */
	public static function gauge( int $score, string $label ): string {
		$score = max( 0, min( 100, $score ) );
		$color = GDC_Overview::score_color( $score );

		return '<div class="gdc-gauge ' . esc_attr( $color ) . '" style="background: conic-gradient(var(--gdc-gauge-color) 0 ' . $score . '%, var(--gdc-s3) ' . $score . '% 100%);">'
			. '<div class="gdc-gauge-inner"><span class="gdc-gauge-n">' . $score . '%</span>'
			. '<span class="gdc-gauge-lbl">' . esc_html( $label ) . '</span></div></div>';
	}

	/**
	 * Checklist row used by the Overview health card.
	 *
	 * @param array<string, mixed> $item Item with state, label, hint and link.
	 * @return string
	 */
	public static function checklist_item( array $item ): string {
		$state = isset( $item['state'] ) ? (string) $item['state'] : 'off';
		$label = isset( $item['label'] ) ? (string) $item['label'] : '';
		$hint  = isset( $item['hint'] ) ? (string) $item['hint'] : '';
		$link  = isset( $item['link'] ) ? (string) $item['link'] : '';

		$classes = 'gdc-status gdc-status--' . ( 'ok' === $state ? 'ok' : ( 'warn' === $state ? 'warn' : 'off' ) );
		$icon    = 'ok' === $state ? 'check' : ( 'warn' === $state ? 'warning' : 'x' );

		$inner  = '<span class="gdc-status-ic">' . self::icon( $icon ) . '</span>';
		$inner .= '<span class="gdc-status-label">' . esc_html( $label );

		if ( '' !== $hint ) {
			$inner .= '<span class="gdc-dim gdc-fs-2xs" style="display:block;">' . esc_html( $hint ) . '</span>';
		}

		$inner .= '</span>';

		if ( '' !== $link ) {
			$inner .= self::icon( 'arrowR', 'gdc-ico--xs gdc-ml-auto' );

			return '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $link ) . '">' . $inner . '</a>';
		}

		return '<div class="' . esc_attr( $classes ) . '">' . $inner . '</div>';
	}
}

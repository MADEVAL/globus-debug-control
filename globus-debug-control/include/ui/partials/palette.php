<?php
/**
 * Command palette overlay. Opened by #gdc-search, #gdc-search-mob or Ctrl+K.
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gdc-pal-overlay" id="gdc-palette" hidden>
	<div class="gdc-pal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search', 'globus-debug-control' ); ?>">
		<div class="gdc-pal-top">
			<?php echo GDC_UI::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
			<input type="text" class="gdc-pal-input" id="gdc-pal-input" placeholder="<?php esc_attr_e( 'Find a setting or screen...', 'globus-debug-control' ); ?>" aria-label="<?php esc_attr_e( 'Find a setting or screen', 'globus-debug-control' ); ?>" autocomplete="off" />
			<button type="button" class="gdc-pal-close" id="gdc-pal-close" aria-label="<?php esc_attr_e( 'Close', 'globus-debug-control' ); ?>">
				<?php echo GDC_UI::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
			</button>
		</div>
		<div class="gdc-pal-list" id="gdc-pal-list" role="listbox"></div>
		<div class="gdc-pal-foot">
			<span><span class="gdc-chip gdc-chip--kbd">&uarr;&darr;</span> <?php esc_html_e( 'navigate', 'globus-debug-control' ); ?></span>
			<span><span class="gdc-chip gdc-chip--kbd">Enter</span> <?php esc_html_e( 'open', 'globus-debug-control' ); ?></span>
			<span><span class="gdc-chip gdc-chip--kbd">Esc</span> <?php esc_html_e( 'close', 'globus-debug-control' ); ?></span>
		</div>
	</div>
</div>

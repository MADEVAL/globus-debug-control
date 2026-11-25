<?php
/**
 * Section navigation sidebar.
 *
 * Expects:
 * - $gdc_nav           Groups from GDC_UI::nav().
 * - $gdc_active_label  Label of the current module (mobile toggle).
 *
 * @package GlobusDebugControl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gdc_nav          = isset( $gdc_nav ) && is_array( $gdc_nav ) ? $gdc_nav : GDC_UI::nav( '' );
$gdc_active_label = isset( $gdc_active_label ) ? (string) $gdc_active_label : __( 'Settings', 'globus-debug-control' );
?>
<nav id="gdc-snav" class="gdc-snav" aria-label="<?php esc_attr_e( 'Plugin sections', 'globus-debug-control' ); ?>">
	<button type="button" class="gdc-snav-toggle" aria-expanded="false">
		<?php echo GDC_UI::icon( 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		<span class="gdc-snav-toggle-label"><?php echo esc_html( $gdc_active_label ); ?></span>
		<?php echo GDC_UI::icon( 'chevron', 'gdc-ico--sm gdc-snav-toggle-chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
	</button>

	<div class="gdc-snav-header">
		<div class="gdc-snav-search">
			<div class="gdc-snav-search-field">
				<?php echo GDC_UI::icon( 'search', 'gdc-snav-search-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
				<input type="text" class="gdc-snav-search-input" placeholder="<?php esc_attr_e( 'Find a screen...', 'globus-debug-control' ); ?>" aria-label="<?php esc_attr_e( 'Find a screen', 'globus-debug-control' ); ?>" autocomplete="off" />
				<button type="button" class="gdc-snav-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'globus-debug-control' ); ?>">
					<?php echo GDC_UI::icon( 'x', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
				</button>
			</div>
		</div>
	</div>

	<div class="gdc-snav-groups">
		<?php foreach ( $gdc_nav as $gdc_group ) : ?>
			<div class="gdc-snav-group">
				<?php if ( '' !== (string) $gdc_group['title'] ) : ?>
					<div class="gdc-snav-group-head">
						<?php echo GDC_UI::icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<span><?php echo esc_html( (string) $gdc_group['title'] ); ?></span>
					</div>
				<?php endif; ?>
				<?php foreach ( $gdc_group['items'] as $gdc_item ) : ?>
					<a
						class="gdc-snav-item<?php echo $gdc_item['active'] ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( (string) $gdc_item['url'] ); ?>"
						data-snav-label="<?php echo esc_attr( (string) $gdc_item['label'] ); ?>"
						<?php echo $gdc_item['active'] ? ' aria-current="page"' : ''; ?>
					>
						<?php echo GDC_UI::icon( (string) $gdc_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
						<span class="gdc-snav-label"><?php echo esc_html( (string) $gdc_item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="gdc-snav-foot gdc-dim gdc-fs-2xs">
		<?php echo GDC_UI::icon( 'command', 'gdc-ico--xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static sprite markup. ?>
		<?php esc_html_e( 'Press Ctrl+K for search', 'globus-debug-control' ); ?>
	</div>
</nav>

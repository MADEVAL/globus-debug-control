<?php
/**
 * GLOBUS Debug Control — Uninstall
 *
 * Removes plugin options from the database when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gdc_options' );

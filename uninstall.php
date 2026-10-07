<?php
/**
 * Remove stored data on uninstall.
 *
 * @package PluginOrphanWatch
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'orwatch_results' );
delete_option( 'orwatch_ignored' );

if ( is_multisite() ) {
	delete_site_option( 'orwatch_results' );
	delete_site_option( 'orwatch_ignored' );
}

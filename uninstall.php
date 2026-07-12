<?php
/**
 * MightyShare uninstall.
 *
 * Removes plugin options when the plugin is deleted from the WordPress
 * admin. Per-post meta is intentionally preserved so that re-installing
 * the plugin restores per-post overrides.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! is_multisite() ) {
	delete_option( 'mightyshare' );
	return;
}

global $wpdb;

$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

foreach ( $blog_ids as $blog_id ) {
	switch_to_blog( (int) $blog_id );
	delete_option( 'mightyshare' );
	restore_current_blog();
}

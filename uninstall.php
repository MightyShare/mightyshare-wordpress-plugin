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

function mightyshare_uninstall_site() {
	delete_option( 'mightyshare' );
	delete_option( 'mightyshare_bulk_prewarm' );
	delete_transient( 'mightyshare_template_catalog' );
	delete_transient( 'mightyshare_plan_checked' );
	wp_clear_scheduled_hook( 'mightyshare_bulk_prewarm_batch' );
	wp_unschedule_hook( 'mightyshare_prewarm_og_image' );
}

if ( ! is_multisite() ) {
	mightyshare_uninstall_site();
	return;
}

foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $mightyshare_blog_id ) {
	switch_to_blog( (int) $mightyshare_blog_id );
	mightyshare_uninstall_site();
	restore_current_blog();
}

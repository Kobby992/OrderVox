<?php
/**
 * Removes Ordervox data on uninstall, only if the store owner opted in under Settings.
 *
 * @package Ordervox
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the current site's Ordervox data when its settings allow it.
 */
function ordervox_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'ordervox_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	foreach ( array( 'ordervox_messages', 'ordervox_webhook_events', 'ordervox_optouts' ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping our own tables on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
	}

	foreach ( array( 'ordervox_settings', 'ordervox_credentials', 'ordervox_db_version', 'ordervox_templates', 'ordervox_health', 'ordervox_webhook_last_at', 'ordervox_quality_update' ) as $option ) {
		delete_option( $option );
	}

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'ordervox' );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $ordervox_site_id ) {
		switch_to_blog( (int) $ordervox_site_id );
		ordervox_uninstall_site();
		restore_current_blog();
	}
} else {
	ordervox_uninstall_site();
}

<?php
/**
 * Plugin uninstall handler.
 *
 * Executed only when the user deletes the plugin through the WP admin
 * (not on deactivation). Removes all plugin data when the
 * "Delete all data on uninstall" option is enabled.
 *
 * @package AIProductOptimizer
 */

declare( strict_types=1 );

// Safety check — WordPress sets this constant before calling uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Only purge data if the admin opted in.
$delete_data = (bool) get_option( 'aipo_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

// ------------------------------------------------------------------
// 1. Drop custom table.
// ------------------------------------------------------------------
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aipo_job_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ------------------------------------------------------------------
// 2. Delete all plugin options.
// ------------------------------------------------------------------
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aipo_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ------------------------------------------------------------------
// 3. Delete all AI-related post meta from every product.
//    The meta keys follow two naming conventions:
//    - _ai_optimizer_*  (plugin-owned fields)
//    - _ai_search_keywords (search boost field)
// ------------------------------------------------------------------
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ai_optimizer_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ai_search_keywords' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ------------------------------------------------------------------
// 4. Remove all Action Scheduler actions in the 'aipo' group.
//    AS may not be loaded during uninstall; check before calling.
// ------------------------------------------------------------------
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'aipo' );
}

// ------------------------------------------------------------------
// 5. Clear any transients with our prefix.
// ------------------------------------------------------------------
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aipo_%' OR option_name LIKE '_transient_timeout_aipo_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Flush object cache in case any of the above are in memory.
wp_cache_flush();

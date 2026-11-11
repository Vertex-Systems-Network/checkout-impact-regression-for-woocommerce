<?php
/**
 * Preserve plugin data unless an administrator explicitly opted into erasure.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( 'yes' !== get_option( 'wpecir_erase_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

$wpecir_tables = array(
	$wpdb->prefix . 'wpecir_findings',
	$wpdb->prefix . 'wpecir_observations',
	$wpdb->prefix . 'wpecir_runs',
	$wpdb->prefix . 'wpecir_change_events',
	$wpdb->prefix . 'wpecir_baselines',
	$wpdb->prefix . 'wpecir_scenario_versions',
	$wpdb->prefix . 'wpecir_scenarios',
);

foreach ( $wpecir_tables as $wpecir_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- explicit, opt-in removal of the plugin's own tables.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpecir_table ) );
}

delete_option( 'wpecir_schema_version' );
delete_option( 'wpecir_erase_data_on_uninstall' );
delete_option( 'wpecir_retention_days' );
wp_clear_scheduled_hook( 'wpecir_purge_expired_run_records' );

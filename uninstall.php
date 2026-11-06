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

$tables = array(
	$wpdb->prefix . 'wpecir_findings',
	$wpdb->prefix . 'wpecir_observations',
	$wpdb->prefix . 'wpecir_runs',
	$wpdb->prefix . 'wpecir_change_events',
	$wpdb->prefix . 'wpecir_baselines',
	$wpdb->prefix . 'wpecir_scenario_versions',
	$wpdb->prefix . 'wpecir_scenarios',
);

foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
}

delete_option( 'wpecir_schema_version' );
delete_option( 'wpecir_erase_data_on_uninstall' );

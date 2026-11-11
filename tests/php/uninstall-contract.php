<?php
/**
 * Contract tests for preserve-by-default and explicit uninstall erasure.
 *
 * Runs uninstall.php with WordPress option/database doubles only.
 *
 * @package WPEssential\\CheckoutImpactRegression
 */

$mode = isset( $argv[1] ) ? $argv[1] : 'preserve';
if ( ! in_array( $mode, array( 'preserve', 'erase' ), true ) ) {
	fwrite( STDERR, "Usage: php uninstall-contract.php preserve|erase\n" );
	exit( 2 );
}

define( 'WP_UNINSTALL_PLUGIN', true );
$GLOBALS['wpecir_uninstall_option'] = 'erase' === $mode ? 'yes' : 'no';
$GLOBALS['wpecir_uninstall_queries'] = array();
$GLOBALS['wpecir_uninstall_deleted_options'] = array();

function get_option( $option, $default = false ) {
	return 'wpecir_erase_data_on_uninstall' === $option ? $GLOBALS['wpecir_uninstall_option'] : $default;
}
$GLOBALS['wpecir_uninstall_cleared_hooks'] = array();
function wp_clear_scheduled_hook( $hook ) {
	$GLOBALS['wpecir_uninstall_cleared_hooks'][] = $hook;
	return 0;
}
function delete_option( $option ) {
	$GLOBALS['wpecir_uninstall_deleted_options'][] = $option;
	return true;
}

class WPECIR_Uninstall_Test_WPDB {
	public $prefix = 'wp_';
	public function prepare( $query, ...$args ) {
		return str_replace( '%i', (string) $args[0], $query );
	}
	public function query( $query ) {
		$GLOBALS['wpecir_uninstall_queries'][] = $query;
		return true;
	}
}
$GLOBALS['wpdb'] = new WPECIR_Uninstall_Test_WPDB();

require dirname( __DIR__, 2 ) . '/uninstall.php';

function uninstall_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

if ( 'preserve' === $mode ) {
	uninstall_check( array() === $GLOBALS['wpecir_uninstall_queries'], 'default uninstall preserves plugin tables' );
	uninstall_check( array() === $GLOBALS['wpecir_uninstall_deleted_options'], 'default uninstall preserves plugin options' );
	uninstall_check( array() === $GLOBALS['wpecir_uninstall_cleared_hooks'], 'default uninstall leaves scheduling to deactivation' );
	echo "Uninstall preserve contract passed (mock database only).\n";
	exit( 0 );
}

$expected_tables = array(
	'wp_wpecir_findings',
	'wp_wpecir_observations',
	'wp_wpecir_runs',
	'wp_wpecir_change_events',
	'wp_wpecir_baselines',
	'wp_wpecir_scenario_versions',
	'wp_wpecir_scenarios',
);
$expected_queries = array_map(
	static function ( $table ) {
		return 'DROP TABLE IF EXISTS ' . $table;
	},
	$expected_tables
);
uninstall_check( $expected_queries === $GLOBALS['wpecir_uninstall_queries'], 'explicit erasure drops only the seven plugin tables' );
uninstall_check(
	array( 'wpecir_schema_version', 'wpecir_erase_data_on_uninstall', 'wpecir_retention_days' ) === $GLOBALS['wpecir_uninstall_deleted_options'],
	'explicit erasure removes only the plugin schema, erase-preference and retention options'
);
uninstall_check( array( 'wpecir_purge_expired_run_records' ) === $GLOBALS['wpecir_uninstall_cleared_hooks'], 'explicit erasure clears the retention cron hook' );
echo "Uninstall erase contract passed (mock database only).\n";

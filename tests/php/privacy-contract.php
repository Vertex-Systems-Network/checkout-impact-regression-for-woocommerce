<?php
/**
 * Contract tests for WordPress privacy exporter and eraser callbacks.
 *
 * Tests use synthetic users/records and an in-memory wpdb double; no live data
 * or WordPress database is accessed.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

function __( $text, $domain = '' ) { return $text; }
function add_filter( $hook, $callback ) { $GLOBALS['wpecir_test_hooks'][ $hook ][] = $callback; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function get_user_by( $field, $value ) {
	if ( 'email' === $field && 'synthetic@example.test' === $value ) {
		return (object) array( 'ID' => 37 );
	}
	return false;
}

class WPECIR_Test_WPDB {
	public $prefix = 'wp_';
	public $prepared;
	public $rows = array();
	public $updates = array();

	public function prepare( $query, ...$args ) {
		$this->prepared = array( 'query' => $query, 'args' => $args );
		return 'prepared synthetic query';
	}

	public function get_results( $query, $output = null ) {
		return $this->rows;
	}

	public function update( $table, $data, $where, $data_formats = null, $where_formats = null ) {
		$this->updates[] = array(
			'table' => $table,
			'data' => $data,
			'where' => $where,
			'data_formats' => $data_formats,
			'where_formats' => $where_formats,
		);
		return 1;
	}
}

$GLOBALS['wpecir_test_hooks'] = array();
$GLOBALS['wpdb'] = new WPECIR_Test_WPDB();

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Privacy\PersonalData;

function privacy_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$hooks = array();
PersonalData::register_hooks();
$exporters = PersonalData::register_exporter( $hooks );
$erasers = PersonalData::register_eraser( $hooks );
privacy_check( isset( $GLOBALS['wpecir_test_hooks']['wp_privacy_personal_data_exporters'] ), 'exporter privacy hook is registered' );
privacy_check( isset( $GLOBALS['wpecir_test_hooks']['wp_privacy_personal_data_erasers'] ), 'eraser privacy hook is registered' );
privacy_check( isset( $exporters['wpecir-record-authorship']['callback'] ), 'export callback is registered' );
privacy_check( isset( $erasers['wpecir-record-authorship']['callback'] ), 'eraser callback is registered' );

$GLOBALS['wpdb']->rows = array(
	array( 'record_type' => 'scenario', 'record_id' => '51', 'record_version' => '0', 'record_date' => '2026-11-07 10:00:00' ),
	array( 'record_type' => 'scenario_version', 'record_id' => '52', 'record_version' => '2', 'record_date' => '2026-11-07 10:01:00' ),
	array( 'record_type' => 'baseline_approval', 'record_id' => '53', 'record_version' => '3', 'record_date' => '2026-11-07 10:02:00' ),
);

$export = PersonalData::export_user_data( 'synthetic@example.test', 2 );
privacy_check( 3 === count( $export['data'] ) && true === $export['done'], 'export returns only the bounded synthetic record fixture' );
privacy_check( 37 === $GLOBALS['wpdb']->prepared['args'][0] && 37 === $GLOBALS['wpdb']->prepared['args'][1] && 37 === $GLOBALS['wpdb']->prepared['args'][2], 'export query is scoped to the resolved author/approver ID' );
privacy_check( 50 === $GLOBALS['wpdb']->prepared['args'][3] && 50 === $GLOBALS['wpdb']->prepared['args'][4], 'export query uses the fixed page size and correct page offset' );
$export_json = json_encode( $export );
privacy_check( false === strpos( $export_json, 'synthetic@example.test' ), 'export output omits the email address' );
privacy_check( false === strpos( $export_json, 'cart_contents' ), 'export output does not expose scenario/cart payloads' );
privacy_check( 'scenario-51' === $export['data'][0]['item_id'], 'export item identifier is stable and sanitized' );

$GLOBALS['wpdb']->updates = array();
$erase = PersonalData::erase_user_data( 'synthetic@example.test' );
privacy_check( 3 === count( $GLOBALS['wpdb']->updates ), 'eraser updates scenario, version, and baseline author records' );
privacy_check( 'wp_wpecir_scenarios' === $GLOBALS['wpdb']->updates[0]['table'], 'scenario table is updated' );
privacy_check( 'wp_wpecir_scenario_versions' === $GLOBALS['wpdb']->updates[1]['table'], 'version table is updated' );
privacy_check( 'wp_wpecir_baselines' === $GLOBALS['wpdb']->updates[2]['table'], 'baseline table is updated' );
foreach ( $GLOBALS['wpdb']->updates as $update ) {
	$id_key = isset( $update['data']['created_by'] ) ? 'created_by' : 'approved_by';
	privacy_check( 0 === $update['data'][ $id_key ], 'eraser anonymizes the author/approver ID' );
	privacy_check( 37 === $update['where'][ $id_key ], 'eraser scopes update to the resolved user ID' );
}
privacy_check( false === $erase['items_removed'] && true === $erase['items_retained'] && true === $erase['done'], 'eraser reports anonymization with history retention, not deletion' );

$GLOBALS['wpdb']->updates = array();
$missing_user = PersonalData::erase_user_data( 'missing@example.test' );
privacy_check( true === $missing_user['done'] && array() === $GLOBALS['wpdb']->updates, 'unknown user is a no-op' );

echo "Privacy exporter/eraser contract checks passed (synthetic fixtures only; no live database accessed).\n";

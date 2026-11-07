<?php
/**
 * Contract tests for explicit, immutable baseline approvals.
 *
 * Uses only synthetic repository/database doubles; no WordPress tables are read.
 *
 * @package WPEssential\\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'ARRAY_A', 'ARRAY_A' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

$GLOBALS['wpecir_baseline_capabilities'] = array();
$GLOBALS['wpecir_baseline_user_id'] = 27;

function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['wpecir_baseline_capabilities'], true ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_json_encode( $value, $options = 0 ) { return json_encode( $value, $options ); }
function is_email( $value ) { return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function current_time( $type, $gmt = false ) { return '2026-11-07 12:00:00'; }
function get_current_user_id() { return $GLOBALS['wpecir_baseline_user_id']; }

class WPECIR_Baseline_Test_WPDB {
	public $prefix = 'wp_';
	public $version_row = null;
	public $max_revision = 0;
	public $insert_id = 0;
	public $prepared = array();
	public $inserts = array();
	public $queries = 0;
	public $insert_result = 1;
	public $rows = array();

	public function prepare( $query, ...$args ) {
		$this->prepared[] = array( $query, $args );
		return array( 'query' => $query, 'args' => $args );
	}

	public function get_row( $prepared ) {
		$this->queries++;
		return $this->version_row;
	}

	public function get_results( $prepared, $output = null ) {
		$this->queries++;
		return $this->rows;
	}

	public function get_var( $prepared ) {
		$this->queries++;
		return $this->max_revision;
	}

	public function insert( $table, $data, $formats = null ) {
		$this->inserts[] = array( $table, $data, $formats );
		if ( false === $this->insert_result ) {
			return false;
		}
		$this->insert_id++;
		return $this->insert_result;
	}
}

$GLOBALS['wpdb'] = new WPECIR_Baseline_Test_WPDB();

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Storage\Repository;

function baseline_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$repository = new Repository();
$observation = array(
	'shipping' => array( 'method' => 'flat_rate', 'cost' => '5.00' ),
	'currency' => 'EUR',
);
$context = str_repeat( 'a', 64 );

// Capability denial must not even query for the referenced scenario version.
$result = $repository->approve_baseline( 11, 22, $observation, $context );
baseline_check( is_wp_error( $result ) && 'wpecir_forbidden' === $result->get_error_code(), 'unauthorized baseline approval is denied' );
baseline_check( 0 === $GLOBALS['wpdb']->queries && empty( $GLOBALS['wpdb']->inserts ), 'unauthorized approval performs no database query or write' );

$result = $repository->list_baselines( 11 );
baseline_check( is_wp_error( $result ) && 'wpecir_forbidden' === $result->get_error_code(), 'unauthorized baseline history is denied' );
baseline_check( 0 === $GLOBALS['wpdb']->queries && empty( $GLOBALS['wpdb']->inserts ), 'unauthorized history performs no database query or write' );

// Authorized approval still requires a version belonging to the selected scenario.
$GLOBALS['wpecir_baseline_capabilities'] = array( 'manage_woocommerce' );
$GLOBALS['wpdb']->rows = array(
	array( 'id' => 9, 'revision' => 2, 'observation_json' => '{"shipping":{"cost":"6.00"}}' ),
	array( 'id' => 8, 'revision' => 1, 'observation_json' => '{"shipping":{"cost":"5.00"}}' ),
);
$writes_before_history = count( $GLOBALS['wpdb']->inserts );
$history = $repository->list_baselines( 11 );
baseline_check( is_array( $history ) && 2 === count( $history ), 'authorized user can read baseline revisions' );
baseline_check( 2 === $history[0]['revision'] && 1 === $history[1]['revision'], 'history preserves newest-first revision order' );
baseline_check( array( 'shipping' => array( 'cost' => '6.00' ) ) === $history[0]['observation'], 'stored observation JSON is decoded for the report view' );
baseline_check( ! isset( $history[0]['observation_json'] ), 'raw storage JSON is not exposed in the returned record' );
baseline_check( $writes_before_history === count( $GLOBALS['wpdb']->inserts ), 'reading baseline history performs no database write' );
$history_query = end( $GLOBALS['wpdb']->prepared );
baseline_check( false !== strpos( $history_query[0], 'FROM wp_wpecir_baselines' ) && false !== strpos( $history_query[0], 'ORDER BY revision DESC' ), 'history query is scoped to the plugin baseline table and ordered by revision' );
baseline_check( array( 11 ) === $history_query[1], 'history query is scoped to the requested scenario ID' );
$GLOBALS['wpdb']->version_row = null;
$result = $repository->approve_baseline( 11, 22, $observation, $context );
baseline_check( is_wp_error( $result ) && 'wpecir_version_not_found' === $result->get_error_code(), 'approval rejects a version that is not linked to the scenario' );
baseline_check( empty( $GLOBALS['wpdb']->inserts ), 'unlinked version is never persisted as a baseline' );

// Invalid context and identity-bearing observations must fail before insertion.
$GLOBALS['wpdb']->version_row = (object) array( 'id' => 22, 'scenario_id' => 11 );
$result = $repository->approve_baseline( 11, 22, $observation, 'not-a-fingerprint' );
baseline_check( is_wp_error( $result ) && 'wpecir_invalid_context' === $result->get_error_code(), 'approval requires a SHA-256 context fingerprint' );
$result = $repository->approve_baseline( 11, 22, array( 'contact_email' => 'synthetic@example.test' ), $context );
baseline_check( is_wp_error( $result ) && 'wpecir_sensitive_payload' === $result->get_error_code(), 'identity-bearing observation is rejected' );
baseline_check( empty( $GLOBALS['wpdb']->inserts ), 'invalid baseline payloads cause no write' );

// Explicit approval stores an immutable revision with approver and context provenance.
$GLOBALS['wpdb']->max_revision = 0;
$result = $repository->approve_baseline( 11, 22, $observation, $context );
baseline_check( ! is_wp_error( $result ) && 1 === $result['revision'] && 'approved' === $result['state'], 'first explicit approval creates revision 1' );
$first = $GLOBALS['wpdb']->inserts[0];
baseline_check( 'wp_wpecir_baselines' === $first[0], 'approval writes only to the plugin baseline table' );
baseline_check( 27 === $first[1]['approved_by'] && $context === $first[1]['context_fingerprint'], 'approval records actor and context fingerprint' );
baseline_check( $observation === json_decode( $first[1]['observation_json'], true ), 'baseline preserves only the supplied synthetic observation' );
baseline_check( 'approved' === $first[1]['state'], 'baseline state is explicitly approved' );

// A subsequent approval advances revision; prior rows are not updated or deleted.
$GLOBALS['wpdb']->max_revision = 1;
$result = $repository->approve_baseline( 11, 22, $observation, $context );
baseline_check( ! is_wp_error( $result ) && 2 === $result['revision'], 'superseding approval advances revision' );
baseline_check( 2 === count( $GLOBALS['wpdb']->inserts ), 'superseding approval inserts a new row instead of replacing history' );

$GLOBALS['wpdb']->insert_result = false;
$result = $repository->approve_baseline( 11, 22, $observation, $context );
baseline_check( is_wp_error( $result ) && 'wpecir_baseline_create_failed' === $result->get_error_code(), 'database insertion failure is returned as a controlled error' );

echo "PASS: explicit immutable baseline approval contracts" . PHP_EOL;

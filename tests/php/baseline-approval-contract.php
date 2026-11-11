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
define( 'DAY_IN_SECONDS', 86400 );


if ( ! function_exists( 'wpecir_test_identifiers' ) ) {
	/**
	 * Resolve %i identifier placeholders like wpdb::prepare() so doubles see table names.
	 *
	 * @param string $query Query.
	 * @param array  $args  Arguments.
	 * @return array
	 */
	function wpecir_test_identifiers( $query, $args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$args = array_values( $args );
		$rest = array();
		$i    = 0;
		$out  = preg_replace_callback(
			'/%[ids]/',
			static function ( $m ) use ( &$i, &$rest, $args ) {
				$arg = $args[ $i ] ?? null;
				++$i;
				if ( '%i' === $m[0] ) {
					return (string) $arg;
				}
				$rest[] = $arg;
				return $m[0];
			},
			$query
		);
		return array( $out, $rest );
	}
}

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
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ); }
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
	public $baseline_row = null;
	public $baseline_match = 0;
	public $last_insert = null;

	public function prepare( $query, ...$args ) {
		list( $query, $args ) = wpecir_test_identifiers( $query, $args );
		$this->prepared[] = array( $query, $args );
		return array( 'query' => $query, 'args' => $args );
	}

	public function get_row( $prepared ) {
		$this->queries++;
		if ( false !== strpos( $prepared['query'], 'FROM wp_wpecir_baselines' ) ) {
			return $this->baseline_row;
		}
		return $this->version_row;
	}

	public function get_results( $prepared, $output = null ) {
		$this->queries++;
		return $this->rows;
	}

	public function get_var( $prepared ) {
		$this->queries++;
		if ( false !== strpos( $prepared['query'], 'FROM wp_wpecir_baselines' ) && false !== strpos( $prepared['query'], 'SELECT id' ) ) {
			return $this->baseline_match;
		}
		return $this->max_revision;
	}

	public function insert( $table, $data, $formats = null ) {
		$this->inserts[] = array( $table, $data, $formats );
		$this->last_insert = array( $table, $data, $formats );
		if ( false === $this->insert_result ) {
			return false;
		}
		$this->insert_id++;
		return $this->insert_result;
	}
}

$GLOBALS['wpdb'] = new WPECIR_Baseline_Test_WPDB();

require __DIR__ . '/stubs.php';
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
$result = $repository->get_baseline( 11, 9 );
baseline_check( is_wp_error( $result ) && 'wpecir_forbidden' === $result->get_error_code(), 'unauthorized baseline lookup is denied' );
baseline_check( 0 === $GLOBALS['wpdb']->queries, 'unauthorized baseline lookup performs no query' );

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
$GLOBALS['wpdb']->baseline_row = array(
	'id'                  => 9,
	'scenario_id'         => 11,
	'scenario_version_id' => 22,
	'revision'            => 2,
	'state'               => 'approved',
	'context_fingerprint' => str_repeat( 'a', 64 ),
	'observation_json'    => '{"shipping.cost":{"state":"observed","value":{"currency_code":"EUR","amount":"6"}}}',
);
$baseline = $repository->get_baseline( 11, 9 );
baseline_check( 9 === (int) $baseline['id'] && 22 === (int) $baseline['scenario_version_id'], 'baseline lookup returns the selected scenario revision' );
baseline_check( '6' === $baseline['observation']['shipping.cost']['value']['amount'], 'baseline lookup decodes approved normalized observations' );
$baseline_query = end( $GLOBALS['wpdb']->prepared );
baseline_check( false !== strpos( $baseline_query[0], 'WHERE id = %d AND scenario_id = %d AND state = %s' ), 'baseline lookup is constrained by ID, scenario and approved state' );
$GLOBALS['wpdb']->baseline_row = null;
$result = $repository->get_baseline( 12, 9 );
baseline_check( is_wp_error( $result ) && 'wpecir_baseline_not_found' === $result->get_error_code(), 'baseline cannot be fetched through another scenario ID' );
$GLOBALS['wpdb']->baseline_row = array(
	'id'                  => 9,
	'scenario_id'         => 11,
	'scenario_version_id' => 22,
	'revision'            => 2,
	'state'               => 'approved',
	'context_fingerprint' => str_repeat( 'a', 64 ),
	'observation_json'    => '{"shipping.method":{"state":"observed","value":"Standard"}}',
);
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

// Manual reports may reference only an approved baseline for the exact version.
$GLOBALS['wpdb']->baseline_match = 31;
$before = array( 'shipping.method' => array( 'state' => 'observed', 'value' => 'Standard' ) );
$after  = array( 'shipping.method' => array( 'state' => 'missing' ) );
$changes = array( 'shipping_method' => array( 'state' => 'missing', 'baseline' => 'Standard' ) );
$inserts_before_report = count( $GLOBALS['wpdb']->inserts );
$report = $repository->create_manual_comparison( 11, 22, $context, $before, $after, $changes, 31 );
baseline_check( ! is_wp_error( $report ) && 'wp_wpecir_runs' === $GLOBALS['wpdb']->inserts[ $inserts_before_report ][0] && 31 === $GLOBALS['wpdb']->inserts[ $inserts_before_report ][1]['baseline_id'], 'manual report stores the exact approved baseline ID' );
$insert_count = count( $GLOBALS['wpdb']->inserts );
$GLOBALS['wpdb']->baseline_match = 0;
$report = $repository->create_manual_comparison( 11, 22, $context, $before, $after, $changes, 99 );
baseline_check( is_wp_error( $report ) && 'wpecir_baseline_context_mismatch' === $report->get_error_code(), 'manual report rejects a baseline not linked to the selected scenario version' );
baseline_check( $insert_count === count( $GLOBALS['wpdb']->inserts ), 'mismatched baseline is rejected before report writes' );

$GLOBALS['wpdb']->insert_result = false;
$result = $repository->approve_baseline( 11, 22, $observation, $context );
baseline_check( is_wp_error( $result ) && 'wpecir_baseline_create_failed' === $result->get_error_code(), 'database insertion failure is returned as a controlled error' );

echo "PASS: explicit immutable baseline approval contracts" . PHP_EOL;

<?php
/**
 * Contracts for comparing a manual after-state against an approved baseline.
 *
 * Uses synthetic repository rows only; no checkout, order, payment, or live DB calls.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ); }
function is_email( $value ) { return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function __( $message, $domain = null ) { return $message; }
function current_user_can( $capability ) { return 'manage_woocommerce' === $capability; }

class WPECIR_Manual_Baseline_Test_WPDB {
	public $prefix = 'wp_';
	public $scenario;
	public $version;
	public $baseline;

	public function prepare( $query, ...$args ) {
		return array( 'query' => $query, 'args' => $args );
	}

	public function get_row( $prepared, $output = null ) {
		$query = $prepared['query'];
		$args  = $prepared['args'];
		if ( false !== strpos( $query, 'FROM wp_wpecir_scenarios' ) ) {
			return $this->scenario && (int) $args[0] === (int) $this->scenario['id'] ? $this->scenario : null;
		}
		if ( false !== strpos( $query, 'FROM wp_wpecir_scenario_versions' ) ) {
			return $this->version && (int) $args[0] === (int) $this->version['scenario_id'] ? $this->version : null;
		}
		if ( false !== strpos( $query, 'FROM wp_wpecir_baselines' ) ) {
			return $this->baseline && (int) $args[0] === (int) $this->baseline['id'] && (int) $args[1] === (int) $this->baseline['scenario_id'] && 'approved' === $args[2] ? $this->baseline : null;
		}
		return null;
	}
}

$context = str_repeat( 'b', 64 );
$GLOBALS['wpdb'] = new WPECIR_Manual_Baseline_Test_WPDB();
$GLOBALS['wpdb']->scenario = array( 'id' => 11, 'title' => 'Synthetic DE checkout', 'status' => 'active' );
$GLOBALS['wpdb']->version = array(
	'id'                  => 22,
	'scenario_id'         => 11,
	'version'             => 3,
	'context_fingerprint' => $context,
	'input_json'          => '{"market":{"country":"DE"},"currency":"EUR"}',
	'expectation_json'    => '{}',
);
$GLOBALS['wpdb']->baseline = array(
	'id'                  => 31,
	'scenario_id'         => 11,
	'scenario_version_id' => 22,
	'revision'            => 4,
	'state'               => 'approved',
	'context_fingerprint' => $context,
	'observation_json'    => '{"shipping.method":{"state":"observed","value":"Standard"},"shipping.cost":{"state":"observed","value":{"currency_code":"EUR","amount":"7"}},"coupon.code":{"state":"missing"},"coupon.discount":{"state":"missing"},"payment.methods":{"state":"observed","value":"cod, stripe"}}',
);

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Rest\ScenarioController;

function baseline_comparison_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

class WPECIR_Manual_Baseline_Test_Request {
	private $params;
	public function __construct( $params ) { $this->params = $params; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}

$prepare = new ReflectionMethod( ScenarioController::class, 'prepare_manual_comparison' );
$prepare->setAccessible( true );
$request = new WPECIR_Manual_Baseline_Test_Request(
	array(
		'scenario_id' => 11,
		'baseline_id' => 31,
		// A forged client before-map is intentionally ignored when baseline_id is present.
		'before'      => array( 'coupon.code' => array( 'state' => 'observed', 'value' => 'fake@example.test' ) ),
		'after'       => array(
			'shipping.method' => array( 'state' => 'observed', 'value' => 'Express' ),
			'shipping.cost'   => array( 'state' => 'observed', 'value' => '12.00' ),
			'coupon.code'     => array( 'state' => 'missing' ),
			'coupon.discount' => array( 'state' => 'missing' ),
			'payment.methods' => array( 'state' => 'observed', 'value' => 'stripe, cod' ),
		),
	)
);
$result = $prepare->invoke( null, $request );
baseline_comparison_check( ! is_wp_error( $result ), 'current approved baseline and manual after observations produce a preview' );
baseline_comparison_check( 31 === (int) $result['baseline']['id'] && 4 === (int) $result['baseline']['revision'], 'preview identifies the approved baseline revision' );
baseline_comparison_check( 'Standard' === $result['result']['observations']['before']['shipping.method']['value'], 'server uses the approved baseline rather than a client-provided before-map' );
baseline_comparison_check( '7' === $result['result']['observations']['before']['shipping.cost']['value']['amount'], 'stored money envelope is normalized for comparison in the scenario currency' );
baseline_comparison_check( 'changed' === $result['result']['changes']['shipping_method']['state'] && 'changed' === $result['result']['changes']['shipping_cost']['state'], 'baseline-to-after differences are computed by the shared comparator' );

$GLOBALS['wpdb']->baseline['observation_json'] = '{"shipping.cost":{"state":"observed","value":{"currency_code":{},"amount":7}}}';
$malformed_money = $prepare->invoke( null, new WPECIR_Manual_Baseline_Test_Request( array( 'scenario_id' => 11, 'baseline_id' => 31, 'after' => array() ) ) );
baseline_comparison_check( is_wp_error( $malformed_money ) && 'wpecir_invalid_baseline_observation' === $malformed_money->get_error_code(), 'malformed stored currency values fail closed without a type error' );
$GLOBALS['wpdb']->baseline['observation_json'] = '{"shipping.method":{"state":"observed","value":"Standard"},"shipping.cost":{"state":"observed","value":{"currency_code":"EUR","amount":"7"}},"coupon.code":{"state":"missing"},"coupon.discount":{"state":"missing"},"payment.methods":{"state":"observed","value":"cod, stripe"}}';

$GLOBALS['wpdb']->baseline['scenario_version_id'] = 21;
$stale = $prepare->invoke( null, new WPECIR_Manual_Baseline_Test_Request( array( 'scenario_id' => 11, 'baseline_id' => 31, 'after' => array() ) ) );
baseline_comparison_check( is_wp_error( $stale ) && 'wpecir_baseline_context_mismatch' === $stale->get_error_code(), 'baseline from a prior scenario version is rejected' );

$GLOBALS['wpdb']->baseline['scenario_version_id'] = 22;
$GLOBALS['wpdb']->scenario['id'] = 12;
$GLOBALS['wpdb']->version['scenario_id'] = 12;
$foreign = $prepare->invoke( null, new WPECIR_Manual_Baseline_Test_Request( array( 'scenario_id' => 12, 'baseline_id' => 31, 'after' => array() ) ) );
baseline_comparison_check( is_wp_error( $foreign ) && 'wpecir_baseline_not_found' === $foreign->get_error_code(), 'baseline from another scenario is not accessible' );

echo "PASS: manual comparison uses an approved, version-matched baseline\n";


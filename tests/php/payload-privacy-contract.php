<?php
/**
 * Contract tests for sensitive and oversized scenario payload rejection.
 *
 * Uses a synthetic in-memory database double. Invalid payloads must be
 * rejected before any write is attempted.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can( $capability ) { return 'manage_woocommerce' === $capability; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $value ) ); }
function is_email( $value ) { return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function current_time( $type, $gmt = false ) { return '2026-11-07 12:00:00'; }
function get_current_user_id() { return 37; }
function wp_generate_password( $length, $special = true, $extra = false ) { return str_repeat( 'a', $length ); }

class WPECIR_Payload_Test_WPDB {
	public $prefix = 'wp_';
	public $insert_calls = 0;

	public function insert( $table, $data, $formats = null ) {
		++$this->insert_calls;
		return false;
	}
}

$GLOBALS['wpdb'] = new WPECIR_Payload_Test_WPDB();

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Storage\Repository;

function payload_privacy_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$repository = new Repository();
$base_input = array(
	'market' => array( 'country' => 'DE' ),
	'currency' => 'EUR',
	'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ),
);

$key_leak = $base_input;
$key_leak['customer'] = array( 'email' => 'synthetic@example.test' );
$key_error = $repository->create_scenario( 'Synthetic key leak', $key_leak, array() );
payload_privacy_check( is_wp_error( $key_error ) && 'wpecir_sensitive_payload' === $key_error->get_error_code(), 'identity-bearing payload keys are rejected' );

$value_leak = $base_input;
$value_leak['customer_reference'] = 'private.person@example.test';
$value_error = $repository->create_scenario( 'Synthetic value leak', $value_leak, array() );
payload_privacy_check( is_wp_error( $value_error ) && 'wpecir_sensitive_payload' === $value_error->get_error_code(), 'email-like scalar values are rejected' );

$secret_leak = $base_input;
$secret_leak['integrations'] = array( 'gateway_api_key' => 'synthetic-secret' );
$secret_error = $repository->create_scenario( 'Synthetic secret leak', $secret_leak, array() );
payload_privacy_check( is_wp_error( $secret_error ) && 'wpecir_sensitive_payload' === $secret_error->get_error_code(), 'nested credentials and API keys are rejected' );

$large_input = $base_input;
$large_input['synthetic_blob'] = str_repeat( 'x', 65537 );
$large_error = $repository->create_scenario( 'Synthetic large payload', $large_input, array() );
payload_privacy_check( is_wp_error( $large_error ) && 'wpecir_payload_too_large' === $large_error->get_error_code(), 'payloads larger than 64 KB are rejected' );

payload_privacy_check( 0 === $GLOBALS['wpdb']->insert_calls, 'invalid payloads cause no database insert' );

echo "Scenario privacy/size contracts passed (synthetic fixtures only; no database writes).\n";

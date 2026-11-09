<?php
/**
 * Contract checks for manual before/after previews.
 *
 * @package WPEssential\\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function is_email( $value ) { return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\\CheckoutImpactRegression\\Comparison\\ManualComparison;

function manual_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$context = str_repeat( 'a', 64 );
$before = array(
	'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:3' ),
	'shipping.cost' => array( 'state' => 'observed', 'value' => '7.00' ),
	'coupon.discount' => array( 'state' => 'missing' ),
	'coupon.code' => array( 'state' => 'not_observed' ),
);
$after = array(
	'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:3' ),
	'shipping.cost' => array( 'state' => 'observed', 'value' => '12.00' ),
	'coupon.discount' => array( 'state' => 'observed', 'value' => '1.00' ),
	'coupon.code' => array( 'state' => 'observed', 'value' => 'WELCOME10' ),
);

$result = ManualComparison::preview( $before, $after, $context, 'USD' );
manual_check( is_array( $result ), 'supported manual observation maps return a preview' );
manual_check( 'unchanged' === $result['changes']['shipping_method']['state'], 'equal shipping method stays unchanged' );
manual_check( 'changed' === $result['changes']['shipping_cost']['state'], 'shipping cost change is identified' );
manual_check( 'added' === $result['changes']['coupon_discount']['state'], 'newly observed coupon discount is added' );
manual_check( 'added' === $result['changes']['coupon_code']['state'], 'new coupon code is shown as added' );
manual_check( 1 === $result['summary']['changed'] && 2 === $result['summary']['added'] && 1 === $result['summary']['unchanged'], 'summary counts match field results' );

$equal_amounts = ManualComparison::preview(
	array( 'shipping.cost' => array( 'state' => 'observed', 'value' => '7.0' ) ),
	array( 'shipping.cost' => array( 'state' => 'observed', 'value' => '7.00' ) ),
	$context,
	'USD'
);
manual_check( 'unchanged' === $equal_amounts['changes']['shipping_cost']['state'], 'decimal formatting differences do not create false changes' );

$unknown = ManualComparison::preview( array( 'customer.email' => array( 'state' => 'observed', 'value' => 'x@y.test' ) ), array(), $context, 'USD' );
manual_check( is_wp_error( $unknown ) && 'wpecir_invalid_observation_field' === $unknown->get_error_code(), 'unapproved personal-data fields are rejected' );
$bad_amount = ManualComparison::preview( array( 'shipping.cost' => array( 'state' => 'observed', 'value' => '1e2' ) ), array(), $context, 'USD' );
manual_check( is_wp_error( $bad_amount ) && 'wpecir_invalid_manual_amount' === $bad_amount->get_error_code(), 'scientific notation is rejected' );
$unobserved = ManualComparison::preview(
	array( 'shipping.method' => array( 'state' => 'not_observed' ) ),
	array( 'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:3' ) ),
	$context,
	'EUR'
);
manual_check( 'not_comparable' === $unobserved['changes']['shipping_method']['state'], 'unobserved baseline never becomes a regression or unchanged result' );

echo "Manual comparison contracts passed.\\n";

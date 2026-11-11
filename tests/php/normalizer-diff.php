<?php
/**
 * Standalone tests for pure normalization/diff behavior.
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
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Comparison\DiffEngine;
use WPEssential\CheckoutImpactRegression\Comparison\Normalizer;

function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$money = Normalizer::money( '12.34', 'eur', 2 );
check( is_array( $money ) && '1234' === $money['minor_units'], 'decimal amount converts to exact minor units' );
check( is_wp_error( Normalizer::money( '1.234', 'EUR', 2 ) ), 'excess precision is rejected' );
check( is_wp_error( Normalizer::money( 1.25, 'EUR', 2 ) ), 'binary float input is rejected' );

$baseline = array(
	'shipping.cost' => array( 'state' => 'observed', 'value' => Normalizer::money( '7.00', 'USD', 2 ) ),
	'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate' ),
	'coupon.discount' => array( 'state' => 'missing' ),
	'payment.available' => array( 'state' => 'unsupported' ),
);
$current = array(
	'shipping.cost' => array( 'state' => 'observed', 'value' => Normalizer::money( '12.00', 'USD', 2 ) ),
	'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate' ),
	'coupon.discount' => array( 'state' => 'observed', 'value' => '10.00' ),
	'payment.available' => array( 'state' => 'observed', 'value' => true ),
);
$diff = DiffEngine::compare( $baseline, $current, str_repeat( 'a', 64 ), str_repeat( 'a', 64 ) );
check( 'changed' === $diff['shipping_cost']['state'], 'changed monetary value is reported' );
check( 'unchanged' === $diff['shipping_method']['state'], 'equal observed value is unchanged' );
check( 'added' === $diff['coupon_discount']['state'], 'new current value is added' );
check( 'not_comparable' === $diff['payment_available']['state'], 'unsupported baseline cannot become unchanged' );

check( is_wp_error( DiffEngine::compare( $baseline, $current, '', str_repeat( 'b', 64 ) ) ), 'missing context fingerprints are rejected' );
$mismatch = DiffEngine::compare( $baseline, $current, str_repeat( 'a', 64 ), str_repeat( 'b', 64 ) );
check( 'not_comparable' === $mismatch['shipping_cost']['state'], 'different context blocks comparison' );

echo "Normalization and diff checks passed.\n";

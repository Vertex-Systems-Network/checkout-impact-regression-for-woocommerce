<?php
/**
 * Contract dry-run for multilingual and currency context comparability.
 *
 * This proves only the fail-closed fingerprint/comparator contract. It does not
 * exercise WPML, WCML, Polylang, WooCommerce checkout, or runtime integrations.
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
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Comparison\DiffEngine;
use WPEssential\CheckoutImpactRegression\Comparison\Normalizer;

function dry_run_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function context_fingerprint( $context ) {
	ksort( $context, SORT_STRING );
	return hash( 'sha256', json_encode( $context, JSON_UNESCAPED_SLASHES ) );
}

$base_context = array(
	'language' => 'en_GB',
	'currency' => 'GBP',
	'currency_source' => 'store_default',
	'checkout_mode' => 'classic',
	'multilingual_stack' => 'none',
	'shipping_destination' => 'GB',
);
$shipping_before = array(
	'shipping.method' => array( 'state' => 'observed', 'value' => 'flat_rate:3' ),
	'shipping.cost' => array( 'state' => 'observed', 'value' => Normalizer::money( '7.25', 'GBP', 2 ) ),
);

$same_context_diff = DiffEngine::compare(
	$shipping_before,
	$shipping_before,
	context_fingerprint( $base_context ),
	context_fingerprint( $base_context )
);
dry_run_check( 'unchanged' === $same_context_diff['shipping_cost']['state'], 'same language/currency/stack/mode context allows comparison' );

$changed_contexts = array(
	'language change' => array_merge( $base_context, array( 'language' => 'de_DE' ) ),
	'currency change' => array_merge( $base_context, array( 'currency' => 'EUR', 'currency_source' => 'location' ) ),
	'checkout mode change' => array_merge( $base_context, array( 'checkout_mode' => 'blocks' ) ),
	'WPML plus WCML context' => array_merge( $base_context, array( 'multilingual_stack' => 'wpml+wcml', 'stack_version' => 'synthetic' ) ),
	'Polylang context' => array_merge( $base_context, array( 'multilingual_stack' => 'polylang-for-woocommerce', 'stack_version' => 'synthetic' ) ),
);

foreach ( $changed_contexts as $label => $changed_context ) {
	$diff = DiffEngine::compare(
		$shipping_before,
		$shipping_before,
		context_fingerprint( $base_context ),
		context_fingerprint( $changed_context )
	);
	dry_run_check( 'not_comparable' === $diff['shipping_cost']['state'], $label . ' is blocked as not comparable' );
	dry_run_check( 'context_mismatch' === $diff['shipping_cost']['reason'], $label . ' exposes the context mismatch reason' );
}

$gbp = Normalizer::money( '7.25', 'GBP', 2 );
$eur = Normalizer::money( '7.25', 'EUR', 2 );
$jpy = Normalizer::money( '725', 'JPY', 0 );
dry_run_check( is_array( $gbp ) && '725' === $gbp['minor_units'], 'GBP decimal amount uses two-place exact minor units' );
dry_run_check( is_array( $eur ) && '725' === $eur['minor_units'], 'EUR decimal amount uses two-place exact minor units' );
dry_run_check( is_array( $jpy ) && '725' === $jpy['minor_units'], 'zero-decimal JPY amount uses zero-place exact minor units' );
dry_run_check( is_wp_error( Normalizer::money( '7.25', 'JPY', 0 ) ), 'fractional amount is rejected at zero-decimal currency precision' );

echo "Multilingual/currency context contract dry-run passed (synthetic only; no plugin integration or checkout executed).\n";

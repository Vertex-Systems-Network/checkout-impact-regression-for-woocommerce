<?php
/**
 * Tests the REST scenario payload contract without a WordPress installation.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Rest\ScenarioController;

function check_contract( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$validate = new ReflectionMethod( ScenarioController::class, 'validate_scenario' );
$validate->setAccessible( true );
$input = array(
	'market' => array( 'country' => 'GB' ),
	'cart' => array( 'items' => array( array( 'product_id' => 1, 'quantity' => 1 ) ) ),
	'currency' => 'GBP',
);
$valid_expectation = array(
	'shipping_cost' => '7.25',
	'coupon_code' => 'WELCOME10',
	'coupon_discount' => '1.50',
);

check_contract( true === $validate->invoke( null, 'UK decimal scenario', $input, $valid_expectation ), 'exact decimal-string expectations are accepted' );
check_contract( is_wp_error( $validate->invoke( null, 'Float scenario', $input, array( 'shipping_cost' => 0.1 ) ) ), 'floating-point monetary values are rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Scientific notation scenario', $input, array( 'shipping_cost' => '1e2' ) ) ), 'scientific notation is rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Excess precision scenario', $input, array( 'shipping_cost' => '0.1234567' ) ) ), 'more than six fractional digits are rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Negative scenario', $input, array( 'coupon_discount' => '-1.00', 'coupon_code' => 'WELCOME10' ) ) ), 'negative expectations are rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Coupon-less scenario', $input, array( 'coupon_discount' => '1.00' ) ) ), 'coupon discount requires its coupon code' );

echo "Scenario payload contract checks passed.\n";

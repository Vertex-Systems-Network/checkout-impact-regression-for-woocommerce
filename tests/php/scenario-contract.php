<?php
/**
 * Tests the REST scenario payload and authorization contracts without WordPress.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

$GLOBALS['wpecir_test_capabilities'] = array();
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function __( $value, $domain = null ) { return $value; }
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['wpecir_test_capabilities'], true ); }

require __DIR__ . '/stubs.php';
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

$invalid_input = $input;
$invalid_input['market'] = 'DE';
check_contract( is_wp_error( $validate->invoke( null, 'Malformed market', $invalid_input, array() ) ), 'non-array market is rejected without nested-offset errors' );

$invalid_input = $input;
$invalid_input['market']['country'] = array( 'DE' );
check_contract( is_wp_error( $validate->invoke( null, 'Malformed country', $invalid_input, array() ) ), 'non-string country is rejected' );

$invalid_input = $input;
$invalid_input['cart'] = 'cart';
check_contract( is_wp_error( $validate->invoke( null, 'Malformed cart', $invalid_input, array() ) ), 'non-array cart is rejected' );

$invalid_input = $input;
$invalid_input['cart']['items'][0]['product_id'] = array( 1 );
check_contract( is_wp_error( $validate->invoke( null, 'Malformed product ID', $invalid_input, array() ) ), 'array product ID is rejected without integer coercion' );

$invalid_input = $input;
$invalid_input['cart']['items'][0]['quantity'] = 1.5;
check_contract( is_wp_error( $validate->invoke( null, 'Fractional quantity', $invalid_input, array() ) ), 'fractional quantity is rejected' );

$invalid_input = $input;
$invalid_input['cart']['items'][0]['quantity'] = 101;
check_contract( is_wp_error( $validate->invoke( null, 'Excess quantity', $invalid_input, array() ) ), 'quantity greater than 100 is rejected' );

check_contract( is_wp_error( $validate->invoke( null, 'Malformed method', $input, array( 'shipping_method' => array( 'flat_rate' ) ) ) ), 'non-string shipping method is rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Malformed coupon', $input, array( 'coupon_code' => array( 'WELCOME10' ) ) ) ), 'non-string coupon code is rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Float scenario', $input, array( 'shipping_cost' => 0.1 ) ) ), 'floating-point monetary values are rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Scientific notation scenario', $input, array( 'shipping_cost' => '1e2' ) ) ), 'scientific notation is rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Excess precision scenario', $input, array( 'shipping_cost' => '0.1234567' ) ) ), 'more than six fractional digits are rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Negative scenario', $input, array( 'coupon_discount' => '-1.00', 'coupon_code' => 'WELCOME10' ) ) ), 'negative expectations are rejected' );
check_contract( is_wp_error( $validate->invoke( null, 'Coupon-less scenario', $input, array( 'coupon_discount' => '1.00' ) ) ), 'coupon discount requires its coupon code' );

$GLOBALS['wpecir_test_capabilities'] = array();
$permission = ScenarioController::permissions_check();
check_contract( is_wp_error( $permission ) && 'wpecir_forbidden' === $permission->get_error_code(), 'users without store-management capabilities are denied' );

$GLOBALS['wpecir_test_capabilities'] = array( 'manage_woocommerce' );
check_contract( true === ScenarioController::permissions_check(), 'WooCommerce managers can access scenario endpoints' );

$GLOBALS['wpecir_test_capabilities'] = array( 'manage_options' );
check_contract( true === ScenarioController::permissions_check(), 'WordPress administrators can access scenario endpoints' );

echo "Scenario payload and authorization contract checks passed.\n";

<?php
/**
 * Contract tests for registered REST route methods and permission callbacks.
 *
 * Route registration only; no HTTP request, option write, or checkout call.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

class WP_REST_Server {
	const READABLE = 'GET';
	const CREATABLE = 'POST';
	const EDITABLE = 'POST, PUT, PATCH';
	const DELETABLE = 'DELETE';
}
$GLOBALS['wpecir_registered_routes'] = array();
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['wpecir_registered_routes'][] = array( $namespace, $route, $args );
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Rest\ScenarioController;

function route_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

ScenarioController::register_routes();
$routes = $GLOBALS['wpecir_registered_routes'];
route_check( 3 === count( $routes ), 'only scenario collection, item, and history routes are registered' );
route_check( 'wpecir/v1' === $routes[0][0] && '/scenarios' === $routes[0][1], 'scenario collection route uses the expected namespace and path' );
route_check( 'wpecir/v1' === $routes[1][0] && '/scenarios/(?P<id>\\d+)' === $routes[1][1], 'scenario item route uses an integer identifier pattern' );
route_check( 2 === count( $routes[0][2] ) && 3 === count( $routes[1][2] ), 'collection exposes read/create and item exposes read/update/delete only' );
route_check( 'wpecir/v1' === $routes[2][0] && false !== strpos( $routes[2][1], '/scenarios/(?P<id>' ) && false !== strpos( $routes[2][1], '/versions' ), 'version history is scoped to a scenario item' );
route_check( 1 === count( $routes[2][2] ) && 'GET' === $routes[2][2][0]['methods'], 'version history exposes read-only access' );

foreach ( $routes as $route ) {
	foreach ( $route[2] as $endpoint ) {
		route_check( isset( $endpoint['permission_callback'] ), 'every endpoint has an explicit permission callback' );
		route_check(
			array( ScenarioController::class, 'permissions_check' ) === $endpoint['permission_callback'],
			'every endpoint uses the shared capability gate'
		);
		route_check( isset( $endpoint['callback'] ) && is_callable( $endpoint['callback'] ), 'every endpoint callback is callable' );
	}
}

echo "REST route registration contract checks passed (no HTTP or checkout request).
";

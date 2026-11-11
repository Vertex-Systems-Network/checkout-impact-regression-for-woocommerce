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

require __DIR__ . '/stubs.php';
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
route_check( 9 === count( $routes ), 'scenario, baseline, event, comparison save, comparison history, preview and settings routes are registered' );
route_check( 'wpecir/v1' === $routes[0][0] && '/scenarios' === $routes[0][1], 'scenario collection route uses the expected namespace and path' );
route_check( 'wpecir/v1' === $routes[1][0] && '/scenarios/(?P<id>\\d+)' === $routes[1][1], 'scenario item route uses an integer identifier pattern' );
route_check( 2 === count( $routes[0][2] ) && 3 === count( $routes[1][2] ), 'collection exposes read/create and item exposes read/update/delete only' );
route_check( 'wpecir/v1' === $routes[2][0] && false !== strpos( $routes[2][1], '/scenarios/(?P<id>' ) && false !== strpos( $routes[2][1], '/versions' ), 'version history is scoped to a scenario item' );
route_check( 1 === count( $routes[2][2] ) && 'GET' === $routes[2][2][0]['methods'], 'version history exposes read-only access' );
route_check( 'wpecir/v1' === $routes[3][0] && false !== strpos( $routes[3][1], '/scenarios/(?P<id>' ) && false !== strpos( $routes[3][1], '/baselines' ), 'baseline history is scoped to a scenario item' );
route_check( 2 === count( $routes[3][2] ) && 'GET' === $routes[3][2][0]['methods'], 'baseline history remains read-only' );
route_check( 'POST' === $routes[3][2][1]['methods'] && array( ScenarioController::class, 'approve_manual_baseline' ) === $routes[3][2][1]['callback'], 'baseline approval uses the admin-only manual observation callback' );
route_check( isset( $routes[3][2][1]['args']['observations']['required'] ) && true === $routes[3][2][1]['args']['observations']['required'], 'baseline approval requires explicit observations' );
route_check( 'wpecir/v1' === $routes[4][0] && '/change-events' === $routes[4][1], 'change-event history uses the expected route' );
route_check( 1 === count( $routes[4][2] ) && 'GET' === $routes[4][2][0]['methods'], 'change-event history exposes read-only access' );
route_check( 'wpecir/v1' === $routes[5][0] && false !== strpos( $routes[5][1], '/scenarios/(?P<id>' ) && false !== strpos( $routes[5][1], '/comparisons' ), 'scenario comparison history uses a scenario-scoped route' );
route_check( 'GET' === $routes[5][2][0]['methods'], 'comparison history exposes read-only access' );
route_check( 'wpecir/v1' === $routes[6][0] && '/comparisons' === $routes[6][1], 'manual comparison persistence uses the expected route' );
route_check( 1 === count( $routes[6][2] ) && 'POST' === $routes[6][2][0]['methods'], 'manual comparison save accepts only a create request' );
route_check( false === $routes[6][2][0]['args']['before']['required'] && false === $routes[6][2][0]['args']['baseline_id']['required'], 'manual comparison can use either explicit before values or a saved baseline' );
route_check( 'wpecir/v1' === $routes[7][0] && '/comparisons/preview' === $routes[7][1], 'manual comparison preview uses the expected route' );
route_check( 1 === count( $routes[7][2] ) && 'POST' === $routes[7][2][0]['methods'], 'manual comparison preview accepts only a create request' );
route_check( isset( $routes[7][2][0]['args']['scenario_id']['required'] ) && true === $routes[7][2][0]['args']['scenario_id']['required'], 'comparison preview requires a saved scenario context' );
route_check( false === $routes[7][2][0]['args']['before']['required'] && false === $routes[7][2][0]['args']['baseline_id']['required'], 'comparison preview accepts saved-baseline or manual before-state input' );

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

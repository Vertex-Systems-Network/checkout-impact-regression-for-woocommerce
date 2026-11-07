<?php
/**
 * Contract tests for bounded expired-run cleanup.
 *
 * Uses a synthetic wpdb double; no WordPress database is touched.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['wpecir_retention_can_manage'] = false;

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can( $capability ) { return $GLOBALS['wpecir_retention_can_manage'] && 'manage_woocommerce' === $capability; }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type, $gmt = false ) { return '2026-11-07 00:00:00'; }

class WPECIR_Retention_Test_WPDB {
	public $prefix = 'wp_';
	public $ids = array();
	public $prepared = array();
	public $queries = array();

	public function prepare( $query, ...$args ) {
		$this->prepared[] = array( $query, $args );
		return $query;
	}
	public function get_col( $query ) {
		$this->selection = $query;
		return $this->ids;
	}
	public function query( $query ) {
		$this->queries[] = $query;
		return 1;
	}
}
$GLOBALS['wpdb'] = new WPECIR_Retention_Test_WPDB();

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Storage\Repository;

function retention_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$repository = new Repository();
$denied     = $repository->purge_expired_runs( 10 );
retention_check( is_wp_error( $denied ) && 'wpecir_forbidden' === $denied->get_error_code(), 'unauthorized cleanup is rejected' );
retention_check( array() === $GLOBALS['wpdb']->prepared, 'unauthorized cleanup does not query run records' );

$GLOBALS['wpecir_retention_can_manage'] = true;
$GLOBALS['wpdb']->ids = array( '11', '12' );
$purged = $repository->purge_expired_runs( 999 );
retention_check( 2 === $purged, 'cleanup reports the number of synthetic expired runs' );
retention_check( 500 === $GLOBALS['wpdb']->prepared[0][1][1], 'requested batch size is capped at 500' );
retention_check(
	'SELECT id FROM wp_wpecir_runs WHERE expires_at IS NOT NULL AND expires_at < %s ORDER BY expires_at ASC LIMIT %d' === $GLOBALS['wpdb']->prepared[0][0],
	'selection includes only expired runs in expiry order with a bounded limit'
);
retention_check(
	array(
		'DELETE FROM wp_wpecir_observations WHERE run_id IN (11,12)',
		'DELETE FROM wp_wpecir_findings WHERE run_id IN (11,12)',
		'DELETE FROM wp_wpecir_runs WHERE id IN (11,12)',
	) === $GLOBALS['wpdb']->queries,
	'expired cleanup removes observations and findings before their parent runs'
);

$GLOBALS['wpdb']->prepared = array();
$GLOBALS['wpdb']->queries  = array();
$GLOBALS['wpdb']->ids      = array();
retention_check( 0 === $repository->purge_expired_runs( 0 ), 'empty expiry selection is a no-op' );
retention_check( 1 === $GLOBALS['wpdb']->prepared[0][1][1], 'batch size is clamped to at least one' );
retention_check( array() === $GLOBALS['wpdb']->queries, 'empty expiry selection deletes nothing' );

echo "Expired-run retention contract checks passed (synthetic database only).\n";

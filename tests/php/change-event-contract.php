<?php
/**
 * Contract tests for redacted, scoped, idempotent change-event capture.
 *
 * Synthetic settings and an in-memory wpdb double are used; no live options or
 * database rows are accessed.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['wpecir_event_capabilities'] = array();
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['wpecir_event_capabilities'], true ); }
function absint( $value ) { return abs( (int) $value ); }
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wpecir_event_hooks'][ $hook ] = array( $callback, $priority, $accepted_args );
}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function is_email( $value ) { return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function apply_filters( $hook, $value ) { return $GLOBALS['wpecir_extra_tracked_options']; }
function current_time( $type, $gmt = false ) { return '2026-11-07 12:00:00'; }

class WPECIR_Event_Test_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 100;
	public $inserts = array();
	public $events = array();
	public $queries = array();
	public $history_rows = array();

	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$quoted = "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%s/', $quoted, $query, 1 );
		}
		return $query;
	}

	public function get_var( $query ) {
		if ( preg_match( "/WHERE event_key = '([a-f0-9]{64})'/", $query, $matches ) ) {
			return isset( $this->events[ $matches[1] ] ) ? $this->events[ $matches[1] ] : null;
		}
		return null;
	}

	public function get_results( $query, $output = null ) { $this->queries[] = $query; return $this->history_rows; }

	public function insert( $table, $data, $formats = null ) {
		$this->inserts[] = array( 'table' => $table, 'data' => $data );
		$this->events[ $data['event_key'] ] = ++$this->insert_id;
		return 1;
	}
}

$GLOBALS['wpecir_event_hooks'] = array();
$GLOBALS['wpecir_extra_tracked_options'] = array( 'custom_carrier_settings' );
$GLOBALS['wpdb'] = new WPECIR_Event_Test_WPDB();

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Events\ChangeEventCollector;
use WPEssential\CheckoutImpactRegression\Storage\Repository;

function event_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

ChangeEventCollector::register_hooks();
event_check( isset( $GLOBALS['wpecir_event_hooks']['upgrader_process_complete'] ), 'plugin update hook is registered' );
event_check( isset( $GLOBALS['wpecir_event_hooks']['updated_option'] ), 'updated option hook is registered' );
event_check( isset( $GLOBALS['wpecir_event_hooks']['added_option'] ), 'added option hook is registered' );
event_check( isset( $GLOBALS['wpecir_event_hooks']['deleted_option'] ), 'deleted option hook is registered' );

ChangeEventCollector::capture_updated_option( 'wordpress_untracked_option', array( 'label' => 'ignored' ), array( 'label' => 'ignored' ) );
event_check( 0 === count( $GLOBALS['wpdb']->inserts ), 'untracked options are ignored' );

$settings = array(
	'label' => 'Flat rate',
	'consumer_secret' => 'synthetic-secret',
	'contact_email' => 'private@example.test',
	'phone_number' => '+1 555 0100',
	'merchant_note' => 'private@example.test',
	'enabled' => true,
);
ChangeEventCollector::capture_updated_option( 'woocommerce_flat_rate_settings', array(), $settings );
event_check( 1 === count( $GLOBALS['wpdb']->inserts ), 'tracked WooCommerce settings produce one event' );
$insert = $GLOBALS['wpdb']->inserts[0]['data'];
$projection = array( 'label' => 'Flat rate', 'merchant_note' => null, 'enabled' => true );
$expected_context = hash( 'sha256', json_encode( $projection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
event_check( $expected_context === $insert['context_fingerprint'], 'context fingerprint includes only the redacted projection' );
$stored_json = json_encode( $insert );
event_check( false === strpos( $stored_json, 'synthetic-secret' ) && false === strpos( $stored_json, 'private@example.test' ) && false === strpos( $stored_json, '+1 555 0100' ), 'stored event metadata contains no secret, email, or phone value' );

ChangeEventCollector::capture_updated_option( 'woocommerce_flat_rate_settings', array(), $settings );
event_check( 1 === count( $GLOBALS['wpdb']->inserts ), 'identical event context is idempotent' );

ChangeEventCollector::capture_added_option( 'custom_carrier_settings', array( 'enabled' => true ) );
event_check( 2 === count( $GLOBALS['wpdb']->inserts ), 'registered extension option names are tracked' );

ChangeEventCollector::capture_deleted_option( 'woocommerce_flat_rate_settings' );
event_check( 3 === count( $GLOBALS['wpdb']->inserts ), 'tracked option deletion is recorded without its deleted value' );
event_check( 'woocommerce_flat_rate_settings' === $GLOBALS['wpdb']->inserts[2]['data']['object_fingerprint'] || 64 === strlen( $GLOBALS['wpdb']->inserts[2]['data']['object_fingerprint'] ), 'deletion stores only the option fingerprint, not an old value' );

$large_settings = array();
for ( $index = 0; $index < 140; ++$index ) {
	$large_settings[ 'setting_' . $index ] = str_repeat( 'x', 500 );
}
ChangeEventCollector::capture_updated_option( 'woocommerce_flat_rate_settings', array(), $large_settings );
event_check( 3 === count( $GLOBALS['wpdb']->inserts ), 'oversized redacted projections are ignored' );

$repository = new Repository();
$denied_events = $repository->list_change_events( 50 );
event_check( is_wp_error( $denied_events ) && 'wpecir_forbidden' === $denied_events->get_error_code(), 'unauthorized event history is denied' );
event_check( empty( $GLOBALS['wpdb']->queries ), 'unauthorized event history does not query the database' );
$GLOBALS['wpecir_event_capabilities'] = array( 'manage_woocommerce' );
$GLOBALS['wpdb']->history_rows = array(
	array( 'id' => 4, 'source' => 'woocommerce_setting', 'object_type' => 'woocommerce_flat_rate_settings', 'object_fingerprint' => str_repeat( 'a', 64 ), 'context_fingerprint' => str_repeat( 'b', 64 ), 'occurred_at' => '2026-11-07 12:00:00', 'created_at' => '2026-11-07 12:00:00' ),
);
$event_history = $repository->list_change_events( 999 );
event_check( is_array( $event_history ) && 1 === count( $event_history ), 'authorized user can read privacy-safe event history' );
event_check( 1 === count( $GLOBALS['wpdb']->queries ), 'authorized event history issues one read query' );
event_check( false !== strpos( $GLOBALS['wpdb']->queries[0], 'ORDER BY occurred_at DESC, id DESC LIMIT 100' ), 'event history is newest-first and clamps oversized page requests to 100' );
event_check( false === strpos( $GLOBALS['wpdb']->queries[0], 'event_key' ), 'event history query omits the idempotency key' );

echo "Change-event redaction/scope/idempotency/history checks passed (synthetic fixtures only; no live settings read).\n";

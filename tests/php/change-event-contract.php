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

echo "Change-event redaction/scope/idempotency checks passed (synthetic fixtures only; no live settings read).\n";

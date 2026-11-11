<?php
/**
 * Contract tests for daily retention scheduling and deactivation cleanup.
 *
 * WordPress Cron functions are in-memory doubles; no real cron event is scheduled.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['wpecir_cron_events'] = array();
$GLOBALS['wpecir_cron_scheduled_args'] = array();
$GLOBALS['wpecir_cron_unscheduled'] = array();
$GLOBALS['wpecir_cron_hooks'] = array();

function add_action( $hook, $callback ) {
	$GLOBALS['wpecir_cron_hooks'][ $hook ] = $callback;
}
function wp_next_scheduled( $hook ) {
	$events = isset( $GLOBALS['wpecir_cron_events'][ $hook ] ) ? $GLOBALS['wpecir_cron_events'][ $hook ] : array();
	return $events ? min( $events ) : false;
}
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['wpecir_cron_scheduled_args'][] = array( $timestamp, $recurrence, $hook );
	$GLOBALS['wpecir_cron_events'][ $hook ][] = $timestamp;
	return true;
}
function wp_unschedule_event( $timestamp, $hook ) {
	$GLOBALS['wpecir_cron_unscheduled'][] = array( $timestamp, $hook );
	$events = &$GLOBALS['wpecir_cron_events'][ $hook ];
	foreach ( $events as $index => $event_timestamp ) {
		if ( $timestamp === $event_timestamp ) {
			unset( $events[ $index ] );
			$events = array_values( $events );
			break;
		}
	}
	return true;
}

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Storage\RetentionScheduler;

function cron_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

RetentionScheduler::register_hooks();
$hook = RetentionScheduler::HOOK;
cron_check( isset( $GLOBALS['wpecir_cron_hooks'][ $hook ] ), 'purge callback is registered on the plugin hook' );
cron_check( 1 === count( $GLOBALS['wpecir_cron_scheduled_args'] ), 'registration schedules one event when none exists' );
$scheduled = $GLOBALS['wpecir_cron_scheduled_args'][0];
cron_check( $scheduled[0] >= time() + HOUR_IN_SECONDS - 2, 'first purge is delayed by approximately one hour' );
cron_check( 'daily' === $scheduled[1] && $hook === $scheduled[2], 'purge uses the daily recurrence and expected hook' );

RetentionScheduler::register_hooks();
cron_check( 1 === count( $GLOBALS['wpecir_cron_scheduled_args'] ), 'repeated registration does not duplicate the scheduled event' );

$GLOBALS['wpecir_cron_events'][ $hook ] = array( 100, 200, 300 );
RetentionScheduler::unschedule();
cron_check( array() === $GLOBALS['wpecir_cron_events'][ $hook ], 'deactivation removes every duplicate event for the plugin hook' );
cron_check(
	array( array( 100, $hook ), array( 200, $hook ), array( 300, $hook ) ) === $GLOBALS['wpecir_cron_unscheduled'],
	'each scheduled timestamp is individually unscheduled'
);

echo "Retention scheduler contract checks passed (in-memory WordPress Cron doubles only).\n";

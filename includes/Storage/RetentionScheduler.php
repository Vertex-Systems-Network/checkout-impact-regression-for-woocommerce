<?php
/**
 * Schedules bounded deletion of expired run records.
 *
 * @package WPEssential\CheckoutImpactRegression\Storage
 */

namespace WPEssential\CheckoutImpactRegression\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Cron bridge for run-history retention.
 */
final class RetentionScheduler {
	const HOOK = 'wpecir_purge_expired_run_records';

	/**
	 * Register and ensure the daily cleanup event exists.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( self::HOOK, array( __CLASS__, 'purge' ) );
		self::schedule();
	}

	/**
	 * Schedule a once-daily bounded purge.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Unschedule plugin cleanup on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Purge one bounded batch. This never creates or executes a checkout run.
	 *
	 * @return int|\\WP_Error
	 */
	public static function purge() {
		$result = ( new Repository() )->purge_expired_runs( 100, true );
		if ( is_wp_error( $result ) ) {
			do_action( 'wpecir_retention_purge_failed', $result );
		}
		return $result;
	}
}

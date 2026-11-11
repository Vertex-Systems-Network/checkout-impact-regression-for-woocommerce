<?php
/**
 * Plugin settings stored as small, non-autoloaded options.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

namespace WPEssential\CheckoutImpactRegression;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Report retention and uninstall preferences (decision D-015).
 */
final class Settings {
	const RETENTION_OPTION = 'wpecir_retention_days';
	const ERASE_OPTION     = 'wpecir_erase_data_on_uninstall';
	const DEFAULT_DAYS     = 30;
	const MIN_DAYS         = 7;
	const MAX_DAYS         = 365;

	/**
	 * Number of days a saved comparison report is kept.
	 *
	 * @return int
	 */
	public static function retention_days() {
		$days = absint( get_option( self::RETENTION_OPTION, self::DEFAULT_DAYS ) );
		return min( self::MAX_DAYS, max( self::MIN_DAYS, $days ? $days : self::DEFAULT_DAYS ) );
	}

	/**
	 * Whether uninstall should erase all plugin tables and options.
	 *
	 * @return bool
	 */
	public static function erase_on_uninstall() {
		return 'yes' === get_option( self::ERASE_OPTION, 'no' );
	}

	/**
	 * Settings as returned to the admin screen.
	 *
	 * @return array
	 */
	public static function to_array() {
		return array(
			'retention_days'     => self::retention_days(),
			'erase_on_uninstall' => self::erase_on_uninstall(),
			'limits'             => array(
				'min_days' => self::MIN_DAYS,
				'max_days' => self::MAX_DAYS,
			),
		);
	}

	/**
	 * Validate and store settings. Applies to reports saved afterwards.
	 *
	 * @param mixed $retention_days Days to keep new reports.
	 * @param mixed $erase          Erase data on uninstall.
	 * @return array|\WP_Error
	 */
	public static function update( $retention_days, $erase ) {
		$is_int_like = is_int( $retention_days ) || ( is_string( $retention_days ) && preg_match( '/^[0-9]{1,3}$/', $retention_days ) );
		$days        = $is_int_like ? (int) $retention_days : 0;
		if ( $days < self::MIN_DAYS || $days > self::MAX_DAYS ) {
			return new \WP_Error(
				'wpecir_invalid_retention',
				sprintf(
					/* translators: 1: minimum days, 2: maximum days. */
					__( 'Report retention must be between %1$d and %2$d days.', 'checkout-impact-regression-for-woocommerce' ),
					self::MIN_DAYS,
					self::MAX_DAYS
				),
				array( 'status' => 400 )
			);
		}
		if ( ! is_bool( $erase ) ) {
			return new \WP_Error( 'wpecir_invalid_erase_setting', __( 'The uninstall preference must be true or false.', 'checkout-impact-regression-for-woocommerce' ), array( 'status' => 400 ) );
		}

		update_option( self::RETENTION_OPTION, $days, false );
		update_option( self::ERASE_OPTION, $erase ? 'yes' : 'no', false );

		return self::to_array();
	}
}

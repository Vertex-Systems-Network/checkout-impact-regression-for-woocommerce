<?php
/**
 * Canonicalizes typed observations and exact monetary values.
 *
 * @package WPEssential\CheckoutImpactRegression\Comparison
 */

namespace WPEssential\CheckoutImpactRegression\Comparison;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure normalization helpers. No checkout or database operations occur here.
 */
final class Normalizer {
	const STATES = array( 'observed', 'missing', 'not_applicable', 'not_observed', 'unsupported', 'collection_error', 'context_mismatch' );

	/**
	 * Normalize an observation envelope, preserving non-comparable states.
	 *
	 * @param mixed $observation Raw observation.
	 * @return array|\WP_Error
	 */
	public static function observation( $observation ) {
		if ( ! is_array( $observation ) || empty( $observation['state'] ) || ! in_array( $observation['state'], self::STATES, true ) ) {
			return new \WP_Error( 'wpecir_invalid_observation', 'Observation state is missing or unsupported.' );
		}

		$normalized = array( 'state' => $observation['state'] );
		if ( 'observed' === $observation['state'] ) {
			if ( ! array_key_exists( 'value', $observation ) ) {
				return new \WP_Error( 'wpecir_observation_value_missing', 'An observed field must include a value.' );
			}
			$normalized['value'] = self::canonicalize( $observation['value'] );
		}

		return $normalized;
	}

	/**
	 * Convert a decimal string to exact minor units without floating point.
	 *
	 * @param mixed  $amount Decimal string.
	 * @param string $currency Three-letter currency code.
	 * @param int    $scale Decimal places.
	 * @return array|\WP_Error
	 */
	public static function money( $amount, $currency, $scale ) {
		$currency = strtoupper( sanitize_text_field( $currency ) );
		$scale    = absint( $scale );
		if ( ! is_string( $amount ) && ! is_int( $amount ) ) {
			return new \WP_Error( 'wpecir_invalid_money', 'Money must be supplied as an exact decimal string or integer.' );
		}
		$amount = (string) $amount;
		if ( ! preg_match( '/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $amount, $matches ) || ! preg_match( '/^[A-Z]{3}$/', $currency ) || $scale > 6 ) {
			return new \WP_Error( 'wpecir_invalid_money', 'Money value, currency, or decimal scale is invalid.' );
		}
		$fraction = isset( $matches[2] ) ? $matches[2] : '';
		if ( strlen( $fraction ) > $scale ) {
			return new \WP_Error( 'wpecir_money_precision', 'Money value exceeds the declared currency precision.' );
		}
		$fraction = str_pad( $fraction, $scale, '0' );
		$minor    = ltrim( $matches[1] . $fraction, '0' );
		$minor    = '' === $minor ? '0' : $minor;

		return array(
			'currency_code' => $currency,
			'decimal_scale' => $scale,
			'minor_units'   => $minor,
		);
	}

	/**
	 * Recursively sort associative keys to make fingerprints deterministic.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_values( $value ) === $value ) {
			return array_map( array( __CLASS__, 'canonicalize' ), $value );
		}
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}
}

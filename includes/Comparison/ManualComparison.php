<?php
/**
 * Safe manual observation comparison preview.
 *
 * @package WPEssential\CheckoutImpactRegression\Comparison
 */

namespace WPEssential\CheckoutImpactRegression\Comparison;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compares merchant-recorded observations without contacting checkout.
 */
final class ManualComparison {
	private const FIELDS = array( 'shipping.method', 'shipping.cost', 'coupon.code', 'coupon.discount', 'payment.methods' );

	/**
	 * Validate a pair of manual observations and return a deterministic preview.
	 *
	 * @param mixed  $before Baseline observation field map.
	 * @param mixed  $after Current observation field map.
	 * @param string $context_fingerprint Saved scenario context.
	 * @param string $currency Scenario currency.
	 * @return array|\WP_Error
	 */
	public static function preview( $before, $after, $context_fingerprint, $currency ) {
		$currency = strtoupper( sanitize_text_field( (string) $currency ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $context_fingerprint ) || ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			return new \WP_Error( 'wpecir_invalid_comparison_context', 'A saved scenario context and valid currency are required.' );
		}

		$before = self::normalize_fields( $before, $currency );
		$after  = self::normalize_fields( $after, $currency );
		if ( is_wp_error( $before ) ) {
			return $before;
		}
		if ( is_wp_error( $after ) ) {
			return $after;
		}

		$changes = DiffEngine::compare( $before, $after, $context_fingerprint, $context_fingerprint );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}

		$summary = array(
			'changed'         => 0,
			'missing'         => 0,
			'added'           => 0,
			'unchanged'       => 0,
			'not_comparable'  => 0,
		);
		foreach ( $changes as $change ) {
			$state = isset( $change['state'] ) ? $change['state'] : 'not_comparable';
			if ( ! isset( $summary[ $state ] ) ) {
				$state = 'not_comparable';
			}
			++$summary[ $state ];
		}

		return array(
			'changes'      => $changes,
			'summary'      => $summary,
			'observations' => array(
				'before' => $before,
				'after'  => $after,
			),
		);
	}

	/**
	 * Normalize a fixed allowlist of observations.
	 *
	 * @param mixed  $fields Input field map.
	 * @param string $currency Currency code.
	 * @return array|\WP_Error
	 */
	private static function normalize_fields( $fields, $currency ) {
		if ( ! is_array( $fields ) || count( $fields ) > count( self::FIELDS ) ) {
			return new \WP_Error( 'wpecir_invalid_observation_map', 'Observations must use the supported checkout fields.' );
		}

		$normalized = array();
		foreach ( $fields as $field => $observation ) {
			if ( ! is_string( $field ) || ! in_array( $field, self::FIELDS, true ) || ! is_array( $observation ) || ! isset( $observation['state'] ) ) {
				return new \WP_Error( 'wpecir_invalid_observation_field', 'An observation contains an unsupported field or state.' );
			}

			$state = $observation['state'];
			if ( ! in_array( $state, array( 'observed', 'missing', 'not_observed', 'unsupported', 'collection_error' ), true ) ) {
				return new \WP_Error( 'wpecir_invalid_observation_state', 'Choose observed, missing, not recorded, unsupported, or collection error.' );
			}

			if ( 'observed' !== $state ) {
				$normalized[ $field ] = array( 'state' => $state );
				continue;
			}

			if ( ! array_key_exists( 'value', $observation ) ) {
				return new \WP_Error( 'wpecir_observation_value_missing', 'Enter a value for every field marked observed.' );
			}

			if ( in_array( $field, array( 'shipping.cost', 'coupon.discount' ), true ) ) {
				$value = self::normalize_amount( $observation['value'], $currency );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
			} elseif ( 'payment.methods' === $field ) {
				$value = self::normalize_payment_methods( $observation['value'] );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
			} else {
				$value = $observation['value'];
				if ( ! is_string( $value ) || '' === trim( $value ) || strlen( $value ) > 191 || is_email( $value ) || preg_match( '/(?:password|secret|token|credential|api.?key)/i', $value ) ) {
					return new \WP_Error( 'wpecir_invalid_observation_value', 'Use a short synthetic shipping or coupon value; personal or secret data is not allowed.' );
				}
				$value = sanitize_text_field( $value );
			}

			$normalized[ $field ] = array(
				'state' => 'observed',
				'value' => $value,
			);
		}

		return $normalized;
	}

	/**
	 * Canonicalize an exact decimal string without floating-point conversion.
	 *
	 * @param mixed  $value Raw decimal.
	 * @param string $currency Currency code.
	 * @return array|\WP_Error
	 */
	private static function normalize_amount( $value, $currency ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return new \WP_Error( 'wpecir_invalid_manual_amount', 'Money must be entered as an exact decimal string.' );
		}
		$value = (string) $value;
		if ( ! preg_match( '/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,6}))?$/', $value, $matches ) ) {
			return new \WP_Error( 'wpecir_invalid_manual_amount', 'Enter a non-negative decimal with at most 6 fractional digits.' );
		}
		$fraction = isset( $matches[2] ) ? rtrim( $matches[2], '0' ) : '';
		$amount   = $matches[1] . ( '' !== $fraction ? '.' . $fraction : '' );

		return array(
			'currency_code' => $currency,
			'amount'        => $amount,
		);
	}

	/**
	 * Normalize a comma-separated list of observed gateway IDs.
	 *
	 * @param mixed $value Raw method IDs.
	 * @return string|\WP_Error
	 */
	private static function normalize_payment_methods( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 500 ) {
			return new \WP_Error( 'wpecir_invalid_payment_methods', 'Enter a comma-separated list of payment method IDs.' );
		}

		$methods = array();
		foreach ( explode( ',', $value ) as $method ) {
			$method = strtolower( trim( $method ) );
			if ( '' === $method || ! preg_match( '/^[a-z0-9][a-z0-9_.:-]{0,99}$/', $method ) || preg_match( '/(?:password|secret|credential|api.?key|access.?token)/i', $method ) ) {
				return new \WP_Error( 'wpecir_invalid_payment_methods', 'Payment method IDs may contain letters, numbers, dots, underscores, colons, and hyphens.' );
			}
			$methods[] = $method;
		}

		$methods = array_values( array_unique( $methods ) );
		sort( $methods, SORT_STRING );
		return implode( ', ', $methods );
	}
}

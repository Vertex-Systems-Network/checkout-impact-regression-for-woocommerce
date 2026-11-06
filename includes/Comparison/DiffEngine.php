<?php
/**
 * Deterministic field-level comparison for normalized observations.
 *
 * @package WPEssential\CheckoutImpactRegression\Comparison
 */

namespace WPEssential\CheckoutImpactRegression\Comparison;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compares only fields captured with comparable semantics.
 */
final class DiffEngine {
	/**
	 * Compare two field maps when their context fingerprints match.
	 *
	 * @param array  $baseline Field => observation envelope.
	 * @param array  $current  Field => observation envelope.
	 * @param string $baseline_context Baseline context fingerprint.
	 * @param string $current_context Current context fingerprint.
	 * @return array|\WP_Error
	 */
	public static function compare( $baseline, $current, $baseline_context, $current_context ) {
		if ( ! is_array( $baseline ) || ! is_array( $current ) ) {
			return new \WP_Error( 'wpecir_invalid_diff_input', 'Baseline and current observations must be field maps.' );
		}
		$fields = array_unique( array_merge( array_keys( $baseline ), array_keys( $current ) ) );
		$diffs  = array();
		foreach ( $fields as $field ) {
			$field = sanitize_key( $field );
			if ( ! preg_match( '/^[a-z0-9_]+$/', $field ) ) {
				continue;
			}
			if ( ! hash_equals( (string) $baseline_context, (string) $current_context ) ) {
				$diffs[ $field ] = array( 'state' => 'not_comparable', 'reason' => 'context_mismatch' );
				continue;
			}
			$before = isset( $baseline[ $field ] ) ? Normalizer::observation( $baseline[ $field ] ) : new \WP_Error( 'wpecir_absent_baseline', 'Field absent from baseline.' );
			$after  = isset( $current[ $field ] ) ? Normalizer::observation( $current[ $field ] ) : new \WP_Error( 'wpecir_absent_current', 'Field absent from current observation.' );
			if ( is_wp_error( $before ) || is_wp_error( $after ) ) {
				$diffs[ $field ] = array( 'state' => 'not_comparable', 'reason' => 'incomplete_observation' );
				continue;
			}
			$before_state = $before['state'];
			$after_state  = $after['state'];
			if ( ! in_array( $before_state, array( 'observed', 'missing' ), true ) || ! in_array( $after_state, array( 'observed', 'missing' ), true ) ) {
				$diffs[ $field ] = array( 'state' => 'not_comparable', 'reason' => $after_state );
				continue;
			}
			if ( 'missing' === $before_state && 'observed' === $after_state ) {
				$diffs[ $field ] = array( 'state' => 'added', 'current' => $after['value'] );
				continue;
			}
			if ( 'observed' === $before_state && 'missing' === $after_state ) {
				$diffs[ $field ] = array( 'state' => 'missing', 'baseline' => $before['value'] );
				continue;
			}
			if ( 'missing' === $before_state && 'missing' === $after_state ) {
				$diffs[ $field ] = array( 'state' => 'unchanged' );
				continue;
			}
			$before_json = wp_json_encode( $before['value'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$after_json  = wp_json_encode( $after['value'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( ! is_string( $before_json ) || ! is_string( $after_json ) ) {
				$diffs[ $field ] = array( 'state' => 'not_comparable', 'reason' => 'invalid_value' );
				continue;
			}
			$diffs[ $field ] = $before_json === $after_json
				? array( 'state' => 'unchanged', 'baseline' => $before['value'], 'current' => $after['value'] )
				: array( 'state' => 'changed', 'baseline' => $before['value'], 'current' => $after['value'] );
		}

		ksort( $diffs, SORT_STRING );
		return $diffs;
	}
}

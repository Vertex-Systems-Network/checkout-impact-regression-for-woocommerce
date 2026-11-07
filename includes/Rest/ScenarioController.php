<?php
/**
 * Admin-only REST endpoints for synthetic scenario records.
 *
 * @package WPEssential\CheckoutImpactRegression\Rest
 */

namespace WPEssential\CheckoutImpactRegression\Rest;

use WPEssential\CheckoutImpactRegression\Storage\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes scenario catalog CRUD without any checkout execution route.
 */
final class ScenarioController {
	const REST_NAMESPACE = 'wpecir/v1';

	/**
	 * Register API routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/scenarios',
			array(
				array(
					'method'             => \WP_REST_Server::READABLE,
					'callback'           => array( __CLASS__, 'list_scenarios' ),
					'permission_callback'=> array( __CLASS__, 'permissions_check' ),
					'args'               => array(
						'page' => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'method'             => \WP_REST_Server::CREATABLE,
					'callback'           => array( __CLASS__, 'create_scenario' ),
					'permission_callback'=> array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scenarios/(?P<id>\\d+)',
			array(
				array(
					'method'              => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_scenario' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'method'              => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_scenario' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'method'              => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_scenario' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
			)
		);
	}

	/**
	 * Require an authenticated store administrator and a valid REST nonce.
	 *
	 * @return bool|\WP_Error
	 */
	public static function permissions_check() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to manage scenarios.', 'wpessential-checkout-impact-regression' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Return one bounded page of scenarios.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function list_scenarios( $request ) {
		$repository = new Repository();
		$scenarios  = $repository->list_scenarios( $request->get_param( 'page' ), 50 );
		return is_wp_error( $scenarios ) ? $scenarios : rest_ensure_response( $scenarios );
	}

	/**
	 * Save a scenario and its first immutable version.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function create_scenario( $request ) {
		$title       = $request->get_param( 'title' );
		$input       = $request->get_param( 'input' );
		$expectation = $request->get_param( 'expectation' );
		if ( ! is_array( $input ) || ! is_array( $expectation ) ) {
			return new \WP_Error( 'wpecir_invalid_scenario', __( 'Scenario inputs and expectations must be structured values.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_scenario( $title, $input, $expectation );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = ( new Repository() )->create_scenario( $title, $input, $expectation );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Validate the initial scenario-builder contract before persistence.
	 *
	 * @param mixed $title Scenario label.
	 * @param array $input Scenario inputs.
	 * @param array $expectation Expected values.
	 * @return true|\WP_Error
	 */
	private static function validate_scenario( $title, $input, $expectation ) {
		$country  = isset( $input['market']['country'] ) ? strtoupper( sanitize_text_field( $input['market']['country'] ) ) : '';
		$currency = isset( $input['currency'] ) ? strtoupper( sanitize_text_field( $input['currency'] ) ) : '';
		$items    = isset( $input['cart']['items'] ) ? $input['cart']['items'] : array();
		if ( ! is_string( $title ) || '' === trim( $title ) || ! preg_match( '/^[A-Z]{2}$/', $country ) || ! preg_match( '/^[A-Z]{3}$/', $currency ) || ! is_array( $items ) || 1 !== count( $items ) ) {
			return new \WP_Error( 'wpecir_invalid_scenario', __( 'Enter a name, two-letter country, three-letter currency, and one synthetic cart item.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}
		$item = reset( $items );
		if ( ! is_array( $item ) || empty( $item['product_id'] ) || absint( $item['product_id'] ) < 1 || empty( $item['quantity'] ) || absint( $item['quantity'] ) < 1 || absint( $item['quantity'] ) > 100 ) {
			return new \WP_Error( 'wpecir_invalid_cart_item', __( 'Use a valid test product ID and a quantity from 1 to 100.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}
		$allowed = array( 'shipping_method', 'shipping_cost', 'coupon_code', 'coupon_discount' );
		foreach ( $expectation as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				return new \WP_Error( 'wpecir_invalid_expectation', __( 'An expected field is not supported by this scenario builder version.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
			}
			if ( in_array( $key, array( 'shipping_cost', 'coupon_discount' ), true ) && ( ! is_numeric( $value ) || (float) $value < 0 ) ) {
				return new \WP_Error( 'wpecir_invalid_expectation_value', __( 'Expected monetary values must be non-negative numbers.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
			}
		}
		if ( isset( $expectation['coupon_discount'] ) && empty( $expectation['coupon_code'] ) ) {
			return new \WP_Error( 'wpecir_coupon_expectation', __( 'A coupon discount expectation requires a coupon code.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Return one scenario with its latest immutable version.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function get_scenario( $request ) {
		$result = ( new Repository() )->get_scenario( $request->get_param( 'id' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Append a new immutable version to an existing scenario.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function update_scenario( $request ) {
		$title       = $request->get_param( 'title' );
		$input       = $request->get_param( 'input' );
		$expectation = $request->get_param( 'expectation' );
		if ( ! is_array( $input ) || ! is_array( $expectation ) ) {
			return new \WP_Error( 'wpecir_invalid_scenario', __( 'Scenario inputs and expectations must be structured values.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		$validation = self::validate_scenario( $title, $input, $expectation );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = ( new Repository() )->add_scenario_version( $request->get_param( 'id' ), $input, $expectation, $title );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Delete a scenario and its dependent records.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_scenario( $request ) {
		$result = ( new Repository() )->delete_scenario( $request->get_param( 'id' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'deleted' => true ) );
	}
}

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
			'/scenarios/(?P<id>\d+)',
			array(
				'method'             => \WP_REST_Server::DELETABLE,
				'callback'           => array( __CLASS__, 'delete_scenario' ),
				'permission_callback'=> array( __CLASS__, 'permissions_check' ),
				'args'               => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
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

		$result = ( new Repository() )->create_scenario( $title, $input, $expectation );
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

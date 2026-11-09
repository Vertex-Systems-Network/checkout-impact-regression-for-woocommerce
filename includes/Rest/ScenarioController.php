<?php
/**
 * Admin-only REST endpoints for synthetic scenario records.
 *
 * @package WPEssential\CheckoutImpactRegression\Rest
 */

namespace WPEssential\CheckoutImpactRegression\Rest;

use WPEssential\CheckoutImpactRegression\Comparison\ManualComparison;
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
					'methods'             => \WP_REST_Server::READABLE,
					'callback'           => array( __CLASS__, 'list_scenarios' ),
					'permission_callback'=> array( __CLASS__, 'permissions_check' ),
					'args'               => array(
						'page' => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
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
					'methods'              => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_scenario' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'methods'              => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_scenario' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'methods'              => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_scenario' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scenarios/(?P<id>\\d+)/versions',
			array(
				array(
					'methods'              => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_scenario_versions' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                 => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scenarios/(?P<id>\\d+)/baselines',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_scenario_baselines' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'approve_manual_baseline' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'id'           => array( 'required' => true, 'sanitize_callback' => 'absint' ),
						'observations' => array( 'required' => true ),
					),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/change-events',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_change_events' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'per_page' => array( 'default' => 50, 'sanitize_callback' => 'absint' ) ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scenarios/(?P<id>\\d+)/comparisons',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_manual_comparisons' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array( 'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/comparisons',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_manual_comparison' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'scenario_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
						'before'      => array( 'required' => true ),
						'after'       => array( 'required' => true ),
					),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/comparisons/preview',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'preview_comparison' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'scenario_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
						'before'      => array( 'required' => true ),
						'after'       => array( 'required' => true ),
					),
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
		$market  = isset( $input['market'] ) && is_array( $input['market'] ) ? $input['market'] : array();
		$country = isset( $market['country'] ) && is_string( $market['country'] ) ? strtoupper( sanitize_text_field( $market['country'] ) ) : '';
		$currency= isset( $input['currency'] ) && is_string( $input['currency'] ) ? strtoupper( sanitize_text_field( $input['currency'] ) ) : '';
		$cart    = isset( $input['cart'] ) && is_array( $input['cart'] ) ? $input['cart'] : array();
		$items   = isset( $cart['items'] ) ? $cart['items'] : array();
		if ( ! is_string( $title ) || '' === trim( $title ) || ! preg_match( '/^[A-Z]{2}$/', $country ) || ! preg_match( '/^[A-Z]{3}$/', $currency ) || ! is_array( $items ) || 1 !== count( $items ) ) {
			return new \WP_Error( 'wpecir_invalid_scenario', __( 'Enter a name, two-letter country, three-letter currency, and one synthetic cart item.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		$item = reset( $items );
		if ( ! is_array( $item ) || ! self::is_positive_integer( isset( $item['product_id'] ) ? $item['product_id'] : null ) || ! self::is_positive_integer( isset( $item['quantity'] ) ? $item['quantity'] : null ) || absint( $item['quantity'] ) > 100 ) {
			return new \WP_Error( 'wpecir_invalid_cart_item', __( 'Use a valid test product ID and a quantity from 1 to 100.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		$allowed = array( 'shipping_method', 'shipping_cost', 'coupon_code', 'coupon_discount' );
		foreach ( $expectation as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				return new \WP_Error( 'wpecir_invalid_expectation', __( 'An expected field is not supported by this scenario builder version.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
			}
			if ( in_array( $key, array( 'shipping_method', 'coupon_code' ), true ) && ( ! is_string( $value ) || '' === trim( $value ) ) ) {
				return new \WP_Error( 'wpecir_invalid_expectation_value', __( 'Expected method and coupon values must be non-empty text.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
			}
			if ( in_array( $key, array( 'shipping_cost', 'coupon_discount' ), true ) && ! self::is_valid_expected_amount( $value ) ) {
				return new \WP_Error( 'wpecir_invalid_expectation_value', __( 'Expected monetary values must be exact non-negative decimals with at most 6 fractional digits.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
			}
		}
		if ( isset( $expectation['coupon_discount'] ) && empty( $expectation['coupon_code'] ) ) {
			return new \WP_Error( 'wpecir_coupon_expectation', __( 'A coupon discount expectation requires a coupon code.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Validate a positive integer without coercing arrays, floats, or booleans.
	 *
	 * @param mixed $value Candidate integer.
	 * @return bool
	 */
	private static function is_positive_integer( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0;
		}

		if ( ! is_string( $value ) || ! preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return false;
		}

		return false !== filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
	}

	/**
	 * Validate exact, non-negative decimal strings without float conversion.
	 *
	 * @param mixed $value Monetary input.
	 * @return bool
	 */
	private static function is_valid_expected_amount( $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return false;
		}

		return 1 === preg_match( '/^(0|[1-9][0-9]{0,15})(?:\.[0-9]{1,6})?$/', (string) $value );
	}

	/**
	 * Return immutable version history for one scenario.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function list_scenario_versions( $request ) {
		$versions = ( new Repository() )->list_scenario_versions( $request->get_param( 'id' ) );
		return is_wp_error( $versions ) ? $versions : rest_ensure_response( $versions );
	}


	/**
	 * Return approved baseline revisions for one scenario without mutation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function list_scenario_baselines( $request ) {
		$baselines = ( new Repository() )->list_baselines( $request->get_param( 'id' ) );
		return is_wp_error( $baselines ) ? $baselines : rest_ensure_response( $baselines );
	}


	/**
	 * Approve a complete, manually observed state as an immutable baseline.
	 *
	 * This endpoint never executes a checkout. The administrator must explicitly
	 * submit all supported fields as observed or confirmed missing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function approve_manual_baseline( $request ) {
		$repository = new Repository();
		$scenario   = $repository->get_scenario( $request->get_param( 'id' ) );
		if ( is_wp_error( $scenario ) ) {
			return $scenario;
		}

		$version      = isset( $scenario['latest_version'] ) ? $scenario['latest_version'] : array();
		$context      = isset( $version['context_fingerprint'] ) ? $version['context_fingerprint'] : '';
		$currency     = isset( $version['input']['currency'] ) ? $version['input']['currency'] : '';
		$observations = $request->get_param( 'observations' );
		if ( ! is_array( $observations ) || empty( $version['id'] ) || '' === $context || '' === $currency ) {
			return new \WP_Error( 'wpecir_invalid_baseline_observation', __( 'A saved scenario version and complete observations are required.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		$normalized = ManualComparison::preview( $observations, $observations, $context, $currency );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$fields = array( 'shipping.method', 'shipping.cost', 'coupon.code', 'coupon.discount' );
		foreach ( $fields as $field ) {
			if ( ! isset( $normalized['observations']['after'][ $field ] ) || ! in_array( $normalized['observations']['after'][ $field ]['state'], array( 'observed', 'missing' ), true ) ) {
				return new \WP_Error( 'wpecir_incomplete_baseline_observation', __( 'Record every supported field as observed or confirmed missing before approving this baseline.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
			}
		}

		$baseline = $repository->approve_baseline(
			$scenario['id'],
			$version['id'],
			$normalized['observations']['after'],
			$context
		);
		if ( is_wp_error( $baseline ) ) {
			return $baseline;
		}

		return rest_ensure_response(
			array(
				'scenario_id' => (int) $scenario['id'],
				'currency'    => strtoupper( $currency ),
				'baseline'    => $baseline,
			)
		);
	}
	/**
	 * Return a bounded, privacy-safe change-event history.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function list_change_events( $request ) {
		$events = ( new Repository() )->list_change_events( $request->get_param( 'per_page' ) );
		return is_wp_error( $events ) ? $events : rest_ensure_response( $events );
	}


	/**
	 * Compare merchant-recorded before/after values without touching checkout.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */

	public static function preview_comparison( $request ) {
		$data = self::prepare_manual_comparison( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return rest_ensure_response( self::manual_comparison_response( $data ) );
	}

	/**
	 * Persist a user-approved manual comparison and return its report identity.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function save_manual_comparison( $request ) {
		$data = self::prepare_manual_comparison( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$scenario = $data['scenario'];
		$result   = $data['result'];
		$version  = $scenario['latest_version'];
		$report   = ( new Repository() )->create_manual_comparison(
			$scenario['id'],
			$version['id'],
			$version['context_fingerprint'],
			$result['observations']['before'],
			$result['observations']['after'],
			$result['changes']
		);
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return rest_ensure_response(
			array_merge(
				self::manual_comparison_response( $data ),
				array( 'report' => $report )
			)
		);
	}

	/**
	 * Return recent, unexpired manual reports for a saved scenario.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function list_manual_comparisons( $request ) {
		$repository = new Repository();
		$scenario   = $repository->get_scenario( $request->get_param( 'id' ) );
		if ( is_wp_error( $scenario ) ) {
			return $scenario;
		}

		$reports = $repository->list_manual_comparisons( $scenario['id'], 10 );
		if ( is_wp_error( $reports ) ) {
			return $reports;
		}

		return rest_ensure_response(
			array(
				'scenario_id'    => (int) $scenario['id'],
				'scenario_title' => $scenario['title'],
				'reports'        => $reports,
			)
		);
	}

	/**
	 * Validate and compute a preview for a saved scenario without side effects.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	private static function prepare_manual_comparison( $request ) {
		$repository = new Repository();
		$scenario   = $repository->get_scenario( $request->get_param( 'scenario_id' ) );
		if ( is_wp_error( $scenario ) ) {
			return $scenario;
		}
		if ( empty( $scenario['latest_version']['context_fingerprint'] ) || empty( $scenario['latest_version']['input']['currency'] ) ) {
			return new \WP_Error( 'wpecir_comparison_context_missing', __( 'The selected scenario has no comparable saved version.', 'wpessential-checkout-impact-regression' ), array( 'status' => 409 ) );
		}

		$before = $request->get_param( 'before' );
		$after  = $request->get_param( 'after' );
		if ( ! is_array( $before ) || ! is_array( $after ) ) {
			return new \WP_Error( 'wpecir_invalid_manual_comparison', __( 'Provide structured before and after observations.', 'wpessential-checkout-impact-regression' ), array( 'status' => 400 ) );
		}

		$result = ManualComparison::preview(
			$before,
			$after,
			$scenario['latest_version']['context_fingerprint'],
			$scenario['latest_version']['input']['currency']
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'scenario' => $scenario,
			'result'   => $result,
		);
	}

	/**
	 * Build the stable API response shared by preview and saved reports.
	 *
	 * @param array $data Scenario and computed comparison.
	 * @return array
	 */
	private static function manual_comparison_response( $data ) {
		$scenario = $data['scenario'];
		$result   = $data['result'];

		return array(
			'scenario_id'      => (int) $scenario['id'],
			'scenario_title'   => $scenario['title'],
			'scenario_version' => (int) $scenario['latest_version']['version'],
			'currency'         => strtoupper( $scenario['latest_version']['input']['currency'] ),
			'mode'             => 'manual_preview',
			'changes'          => $result['changes'],
			'summary'          => $result['summary'],
		);
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

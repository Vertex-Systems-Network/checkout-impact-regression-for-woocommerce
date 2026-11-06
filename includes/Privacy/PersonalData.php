<?php
/**
 * WordPress privacy exporter and eraser integration.
 *
 * @package WPEssential\CheckoutImpactRegression\Privacy
 */

namespace WPEssential\CheckoutImpactRegression\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports minimal author/approver metadata and anonymizes plugin records.
 */
final class PersonalData {
	/**
	 * Register WordPress privacy hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Register the scenario metadata exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['wpecir-record-authorship'] = array(
			'exporter_friendly_name' => __( 'WPEssential Checkout Impact Regression', 'wpessential-checkout-impact-regression' ),
			'callback'               => array( __CLASS__, 'export_user_data' ),
		);

		return $exporters;
	}

	/**
	 * Register the record-author anonymizer.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['wpecir-record-authorship'] = array(
			'eraser_friendly_name' => __( 'WPEssential Checkout Impact Regression', 'wpessential-checkout-impact-regression' ),
			'callback'            => array( __CLASS__, 'erase_user_data' ),
		);

		return $erasers;
	}

	/**
	 * Export only record identifiers, versions, and timestamps.
	 *
	 * @param string $email_address User email resolved by WordPress.
	 * @param int    $page          One-based page number.
	 * @return array
	 */
	public static function export_user_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$per_page = 50;
		$offset   = max( 0, ( absint( $page ) - 1 ) * $per_page );
		$scenarios= $wpdb->prefix . 'wpecir_scenarios';
		$versions = $wpdb->prefix . 'wpecir_scenario_versions';
		$baselines= $wpdb->prefix . 'wpecir_baselines';

		$sql = $wpdb->prepare(
			"SELECT 'scenario' AS record_type, id AS record_id, 0 AS record_version, created_at AS record_date FROM {$scenarios} WHERE created_by = %d
			UNION ALL
			SELECT 'scenario_version' AS record_type, id AS record_id, version AS record_version, created_at AS record_date FROM {$versions} WHERE created_by = %d
			UNION ALL
			SELECT 'baseline_approval' AS record_type, id AS record_id, revision AS record_version, approved_at AS record_date FROM {$baselines} WHERE approved_by = %d
			ORDER BY record_date ASC LIMIT %d OFFSET %d",
			$user->ID,
			$user->ID,
			$user->ID,
			$per_page,
			$offset
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$data = array();

		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'checkout-impact-records',
				'group_label' => __( 'Checkout Impact record authorship', 'wpessential-checkout-impact-regression' ),
				'item_id'     => sanitize_key( $row['record_type'] ) . '-' . absint( $row['record_id'] ),
				'data'        => array(
					array( 'name' => __( 'Record type', 'wpessential-checkout-impact-regression' ), 'value' => sanitize_text_field( $row['record_type'] ) ),
					array( 'name' => __( 'Version or revision', 'wpessential-checkout-impact-regression' ), 'value' => absint( $row['record_version'] ) ),
					array( 'name' => __( 'Recorded at (UTC)', 'wpessential-checkout-impact-regression' ), 'value' => sanitize_text_field( $row['record_date'] ) ),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < $per_page,
		);
	}

	/**
	 * Anonymize user IDs while retaining the merchant's scenario history.
	 *
	 * @param string $email_address User email resolved by WordPress.
	 * @param int    $page          Page number; erasure is applied in one bounded pass.
	 * @return array
	 */
	public static function erase_user_data( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$scenarios = $wpdb->prefix . 'wpecir_scenarios';
		$versions  = $wpdb->prefix . 'wpecir_scenario_versions';
		$baselines = $wpdb->prefix . 'wpecir_baselines';
		$wpdb->update( $scenarios, array( 'created_by' => 0 ), array( 'created_by' => $user->ID ), array( '%d' ), array( '%d' ) );
		$wpdb->update( $versions, array( 'created_by' => 0 ), array( 'created_by' => $user->ID ), array( '%d' ), array( '%d' ) );
		$wpdb->update( $baselines, array( 'approved_by' => 0 ), array( 'approved_by' => $user->ID ), array( '%d' ), array( '%d' ) );

		return array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array( __( 'Author and approver identifiers were anonymized; scenario and baseline history was retained.', 'wpessential-checkout-impact-regression' ) ),
			'done'           => true,
		);
	}
}

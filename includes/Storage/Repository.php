<?php
/**
 * Access-controlled persistence for scenarios, baselines and change events.
 *
 * @package WPEssential\CheckoutImpactRegression\Storage
 */

namespace WPEssential\CheckoutImpactRegression\Storage;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this repository is the only access layer for the plugin's own custom tables; results are per-request and must not be served from a persistent cache after writes.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for plugin-owned records.
 *
 * All mutations are administrator-only. Payloads are minimized and checked
 * before storage; checkout execution is intentionally outside this class.
 */
final class Repository {
	/**
	 * Resolve the plugin's table name.
	 *
	 * @param string $suffix Known table suffix.
	 * @return string
	 */
	private function table( $suffix ) {
		global $wpdb;

		$allowed = array( 'scenarios', 'scenario_versions', 'baselines', 'change_events', 'runs', 'observations', 'findings' );
		if ( ! in_array( $suffix, $allowed, true ) ) {
			return '';
		}

		return $wpdb->prefix . 'wpecir_' . $suffix;
	}

	/**
	 * Delete rows whose column matches one of the given integer IDs.
	 *
	 * @param string $suffix Table suffix.
	 * @param string $column Column name.
	 * @param int[]  $ids    IDs.
	 * @return int|false
	 */
	private function delete_where_in( $suffix, $column, $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated from the ID count.
		return $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE %i IN ($placeholders)", array_merge( array( $this->table( $suffix ), $column ), $ids ) ) );
	}

	/**
	 * Whether the current user may manage plugin records.
	 *
	 * @return bool
	 */
	private function can_manage() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Reject non-array, oversized, or identity-bearing JSON payloads.
	 *
	 * @param mixed  $payload Data to validate.
	 * @param string $field   Field label for errors.
	 * @return string|\WP_Error
	 */
	private function encode_safe_payload( $payload, $field ) {
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'wpecir_invalid_payload', sprintf( 'The %s payload must be an object or list.', $field ) );
		}

		if ( $this->contains_sensitive_key( $payload ) || $this->contains_email_value( $payload ) ) {
			return new \WP_Error( 'wpecir_sensitive_payload', sprintf( 'The %s payload contains personal or secret data. Use synthetic scenario values.', $field ) );
		}

		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || strlen( $json ) > 65536 ) {
			return new \WP_Error( 'wpecir_payload_too_large', sprintf( 'The %s payload is invalid or exceeds 64 KB.', $field ) );
		}

		return $json;
	}

	/**
	 * Detect keys that should never enter scenario or result JSON.
	 *
	 * @param array $value Nested payload.
	 * @return bool
	 */
	private function contains_sensitive_key( $value ) {
		$blocked = '/(?:email|phone|mobile|username|first.?name|last.?name|address.?[12]|street|card|password|secret|token|api.?key|credential)/i';

		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && preg_match( $blocked, $key ) ) {
				return true;
			}

			if ( is_array( $item ) && $this->contains_sensitive_key( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect email addresses in nested scalar values.
	 *
	 * @param array $value Nested payload.
	 * @return bool
	 */
	private function contains_email_value( $value ) {
		foreach ( $value as $item ) {
			if ( is_array( $item ) && $this->contains_email_value( $item ) ) {
				return true;
			}

			if ( is_string( $item ) && is_email( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create a scenario and its immutable first version.
	 *
	 * @param string $title       Scenario label.
	 * @param array  $input       Synthetic scenario inputs.
	 * @param array  $expectation Expected assertions.
	 * @return array|\WP_Error
	 */
	public function create_scenario( $title, $input, $expectation ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to manage checkout scenarios.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$title = sanitize_text_field( $title );
		if ( '' === $title || strlen( $title ) > 191 || is_email( $title ) ) {
			return new \WP_Error( 'wpecir_invalid_title', __( 'Scenario title is required and must be 191 characters or fewer.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$input_json       = $this->encode_safe_payload( $input, 'scenario input' );
		$expectation_json = $this->encode_safe_payload( $expectation, 'scenario expectation' );
		if ( is_wp_error( $input_json ) ) {
			return $input_json;
		}
		if ( is_wp_error( $expectation_json ) ) {
			return $expectation_json;
		}

		$now  = current_time( 'mysql', true );
		$slug = substr( sanitize_title( $title ), 0, 140 ) . '-' . strtolower( wp_generate_password( 12, false, false ) );
		$ok   = $wpdb->insert(
			$this->table( 'scenarios' ),
			array(
				'slug'       => $slug,
				'title'      => $title,
				'status'     => 'active',
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'wpecir_scenario_create_failed', __( 'The scenario could not be saved.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$scenario_id = (int) $wpdb->insert_id;
		$version_id  = $this->insert_scenario_version( $scenario_id, $input_json, $expectation_json );
		if ( is_wp_error( $version_id ) ) {
			$wpdb->delete( $this->table( 'scenarios' ), array( 'id' => $scenario_id ), array( '%d' ) );
			return $version_id;
		}

		return $this->get_scenario( $scenario_id );
	}

	/**
	 * Add a new immutable version to an existing scenario.
	 *
	 * @param int   $scenario_id Scenario identifier.
	 * @param array $input       Synthetic scenario inputs.
	 * @param array $expectation Expected assertions.
	 * @param string $title      Optional replacement label.
	 * @return array|\WP_Error
	 */
	public function add_scenario_version( $scenario_id, $input, $expectation, $title = '' ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to manage checkout scenarios.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$scenario = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $this->table( 'scenarios' ), absint( $scenario_id ) ) );
		if ( ! $scenario ) {
			return new \WP_Error( 'wpecir_scenario_not_found', __( 'The requested scenario does not exist.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$input_json       = $this->encode_safe_payload( $input, 'scenario input' );
		$expectation_json = $this->encode_safe_payload( $expectation, 'scenario expectation' );
		if ( is_wp_error( $input_json ) ) {
			return $input_json;
		}
		if ( is_wp_error( $expectation_json ) ) {
			return $expectation_json;
		}

		$version_id = $this->insert_scenario_version( absint( $scenario_id ), $input_json, $expectation_json );
		if ( is_wp_error( $version_id ) ) {
			return $version_id;
		}

		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );
		$title  = sanitize_text_field( $title );
		if ( '' !== $title && strlen( $title ) <= 191 ) {
			$update['title'] = $title;
			$format[]        = '%s';
		}

		$wpdb->update( $this->table( 'scenarios' ), $update, array( 'id' => absint( $scenario_id ) ), $format, array( '%d' ) );

		return $this->get_scenario( absint( $scenario_id ) );
	}

	/**
	 * Insert a version record and return its row identifier.
	 *
	 * @param int    $scenario_id Scenario identifier.
	 * @param string $input_json  Encoded inputs.
	 * @param string $expect_json Encoded expectations.
	 * @return int|\WP_Error
	 */
	private function insert_scenario_version( $scenario_id, $input_json, $expect_json ) {
		global $wpdb;

		$versions = $this->table( 'scenario_versions' );
		$version  = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM %i WHERE scenario_id = %d', $versions, $scenario_id ) );
		$context  = hash( 'sha256', $input_json );
		$ok       = $wpdb->insert(
			$versions,
			array(
				'scenario_id'        => $scenario_id,
				'version'            => $version,
				'input_json'         => $input_json,
				'expectation_json'   => $expect_json,
				'context_fingerprint'=> $context,
				'created_by'         => get_current_user_id(),
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'wpecir_version_create_failed', __( 'The scenario version could not be saved.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieve a scenario and its latest version.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @return array|\WP_Error
	 */
	public function get_scenario( $scenario_id ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view checkout scenarios.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table( 'scenarios' ), absint( $scenario_id ) ), ARRAY_A );
		if ( ! $row ) {
			return new \WP_Error( 'wpecir_scenario_not_found', __( 'The requested scenario does not exist.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE scenario_id = %d ORDER BY version DESC LIMIT 1', $this->table( 'scenario_versions' ),
				absint( $scenario_id )
			),
			ARRAY_A
		);

		$row['latest_version'] = $version ? $this->decode_version( $version ) : null;
		return $row;
	}

	/**
	 * List scenarios with a bounded page size.
	 *
	 * @param int $page     One-based page number.
	 * @param int $per_page Page size, capped at 100.
	 * @return array|\WP_Error
	 */
	public function list_scenarios( $page = 1, $per_page = 25 ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view checkout scenarios.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
				$this->table( 'scenarios' ),
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * List immutable versions for a scenario.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @return array|\WP_Error
	 */
	public function list_scenario_versions( $scenario_id ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view checkout scenarios.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE scenario_id = %d ORDER BY version DESC', $this->table( 'scenario_versions' ), absint( $scenario_id ) ),
			ARRAY_A
		);

		return array_map( array( $this, 'decode_version' ), $rows );
	}

	/**
	 * Decode JSON fields while preserving raw storage metadata.
	 *
	 * @param array $row Version row.
	 * @return array
	 */
	private function decode_version( $row ) {
		$row['input']       = json_decode( $row['input_json'], true );
		$row['expectation'] = json_decode( $row['expectation_json'], true );
		unset( $row['input_json'], $row['expectation_json'] );

		return $row;
	}

	/**
	 * Create an explicitly approved immutable baseline.
	 *
	 * @param int    $scenario_id Scenario identifier.
	 * @param int    $version_id  Scenario version identifier.
	 * @param array  $observation Normalized observations.
	 * @param string $context     Comparison context fingerprint.
	 * @return array|\WP_Error
	 */
	public function approve_baseline( $scenario_id, $version_id, $observation, $context ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to approve a baseline.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, scenario_id FROM %i WHERE id = %d AND scenario_id = %d', $this->table( 'scenario_versions' ),
				absint( $version_id ),
				absint( $scenario_id )
			)
		);
		if ( ! $version ) {
			return new \WP_Error( 'wpecir_version_not_found', __( 'A baseline must reference a version of the selected scenario.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$observation_json = $this->encode_safe_payload( $observation, 'baseline observation' );
		if ( is_wp_error( $observation_json ) ) {
			return $observation_json;
		}

		$context = sanitize_text_field( $context );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $context ) ) {
			return new \WP_Error( 'wpecir_invalid_context', __( 'A SHA-256 context fingerprint is required.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$table    = $this->table( 'baselines' );
		$revision = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(revision) FROM %i WHERE scenario_id = %d', $table, absint( $scenario_id ) ) );
		$now      = current_time( 'mysql', true );
		$ok       = $wpdb->insert(
			$table,
			array(
				'scenario_id'         => absint( $scenario_id ),
				'scenario_version_id'=> absint( $version_id ),
				'revision'            => $revision,
				'state'               => 'approved',
				'context_fingerprint' => $context,
				'observation_json'    => $observation_json,
				'approved_by'         => get_current_user_id(),
				'approved_at'         => $now,
				'created_at'          => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'wpecir_baseline_create_failed', __( 'The approved baseline could not be saved.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		return array(
			'id'         => (int) $wpdb->insert_id,
			'scenario_id'=> absint( $scenario_id ),
			'version_id' => absint( $version_id ),
			'revision'   => $revision,
			'state'      => 'approved',
			'approved_at'=> $now,
		);
	}

	/**
	 * List baselines for a scenario without mutating their history.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @return array|\WP_Error
	 */
	public function list_baselines( $scenario_id ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view baselines.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE scenario_id = %d ORDER BY revision DESC', $this->table( 'baselines' ), absint( $scenario_id ) ),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['observation'] = json_decode( $row['observation_json'], true );
			unset( $row['observation_json'] );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Retrieve one approved baseline scoped to its scenario.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @param int $baseline_id Baseline identifier.
	 * @return array|\WP_Error
	 */
	public function get_baseline( $scenario_id, $baseline_id ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view baselines.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d AND scenario_id = %d AND state = %s', $this->table( 'baselines' ),
				absint( $baseline_id ),
				absint( $scenario_id ),
				'approved'
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new \WP_Error( 'wpecir_baseline_not_found', __( 'The approved baseline does not exist for this scenario.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$row['observation'] = json_decode( $row['observation_json'], true );
		unset( $row['observation_json'] );

		return $row;
	}


	/**
	 * Save a validated manual preview as a short-lived report.
	 *
	 * @param int    $scenario_id Scenario identifier.
	 * @param int    $version_id Scenario version identifier.
	 * @param string $context Context fingerprint.
	 * @param array  $before Normalized before observations.
	 * @param array  $after Normalized after observations.
	 * @param array  $changes Server-calculated comparison changes.
	 * @return array|\WP_Error
	 */
	public function create_manual_comparison( $scenario_id, $version_id, $context, $before, $after, $changes, $baseline_id = null ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to save comparison reports.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$scenario_id = absint( $scenario_id );
		$version_id  = absint( $version_id );
		$context     = sanitize_text_field( (string) $context );
		if ( $scenario_id < 1 || $version_id < 1 || ! preg_match( '/^[a-f0-9]{64}$/', $context ) ) {
			return new \WP_Error( 'wpecir_invalid_comparison_context', __( 'A valid saved scenario version is required.', 'checkout-impact-regression-for-woocommerce' ) );
		}
		if ( null !== $baseline_id ) {
			$baseline_id = absint( $baseline_id );
			$baseline    = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE id = %d AND scenario_id = %d AND scenario_version_id = %d AND context_fingerprint = %s AND state = %s', $this->table( 'baselines' ),
					$baseline_id,
					$scenario_id,
					$version_id,
					$context,
					'approved'
				)
			);
			if ( $baseline_id < 1 || (int) $baseline !== $baseline_id ) {
				return new \WP_Error( 'wpecir_baseline_context_mismatch', __( 'The approved baseline does not match the selected scenario version.', 'checkout-impact-regression-for-woocommerce' ) );
			}
		}

		$version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE id = %d AND scenario_id = %d AND context_fingerprint = %s', $this->table( 'scenario_versions' ),
				$version_id,
				$scenario_id,
				$context
			)
		);
		if ( ! $version ) {
			return new \WP_Error( 'wpecir_comparison_version_not_found', __( 'The report context does not match a saved version of this scenario.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$allowed_fields = array( 'shipping.method', 'shipping.cost', 'coupon.code', 'coupon.discount', 'payment.methods' );
		foreach ( array( $before, $after ) as $observations ) {
			if ( ! is_array( $observations ) || count( $observations ) > count( $allowed_fields ) ) {
				return new \WP_Error( 'wpecir_invalid_comparison_observations', __( 'Only the supported manual checkout fields may be saved.', 'checkout-impact-regression-for-woocommerce' ) );
			}
			foreach ( $observations as $field => $observation ) {
				if ( ! is_string( $field ) || ! in_array( $field, $allowed_fields, true ) || ! is_array( $observation ) || empty( $observation['state'] ) || ! in_array( $observation['state'], array( 'observed', 'missing', 'not_observed', 'unsupported', 'collection_error' ), true ) ) {
					return new \WP_Error( 'wpecir_invalid_comparison_observations', __( 'The manual observations contain an unsupported field or state.', 'checkout-impact-regression-for-woocommerce' ) );
				}
				if ( 'observed' === $observation['state'] && ! array_key_exists( 'value', $observation ) ) {
					return new \WP_Error( 'wpecir_invalid_comparison_observations', __( 'Every observed value must include its normalized value.', 'checkout-impact-regression-for-woocommerce' ) );
				}
				if ( 'observed' !== $observation['state'] && array_key_exists( 'value', $observation ) ) {
					return new \WP_Error( 'wpecir_invalid_comparison_observations', __( 'Unobserved fields cannot include a value.', 'checkout-impact-regression-for-woocommerce' ) );
				}
			}
		}
		if ( ! is_array( $changes ) || count( $changes ) > count( $allowed_fields ) ) {
			return new \WP_Error( 'wpecir_invalid_comparison_findings', __( 'The comparison output is invalid.', 'checkout-impact-regression-for-woocommerce' ) );
		}
		$allowed_changes = array( 'shipping_method', 'shipping_cost', 'coupon_code', 'coupon_discount', 'payment_methods' );
		foreach ( $changes as $field => $change ) {
			if ( ! is_string( $field ) || ! in_array( $field, $allowed_changes, true ) || ! is_array( $change ) || empty( $change['state'] ) || ! in_array( $change['state'], array( 'changed', 'missing', 'added', 'unchanged', 'not_comparable' ), true ) ) {
				return new \WP_Error( 'wpecir_invalid_comparison_findings', __( 'The comparison output contains an unsupported field or state.', 'checkout-impact-regression-for-woocommerce' ) );
			}
		}

		$before_json = $this->encode_safe_payload( $before, 'before observations' );
		$after_json  = $this->encode_safe_payload( $after, 'after observations' );
		$changes_json= $this->encode_safe_payload( $changes, 'comparison findings' );
		if ( is_wp_error( $before_json ) || is_wp_error( $after_json ) || is_wp_error( $changes_json ) ) {
			return new \WP_Error( 'wpecir_sensitive_comparison', __( 'Sensitive or invalid data cannot be saved in a comparison report.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$now       = current_time( 'mysql', true );
		$expires   = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) + ( \WPEssential\CheckoutImpactRegression\Settings::retention_days() * DAY_IN_SECONDS ) );
		$run_table = $this->table( 'runs' );
		$inserted  = $wpdb->insert(
			$run_table,
			array(
				'scenario_id'         => $scenario_id,
				'scenario_version_id' => $version_id,
				'baseline_id'         => $baseline_id,
				'change_event_id'     => null,
				'state'               => 'manual_preview',
				'context_fingerprint' => $context,
				'started_at'          => $now,
				'finished_at'         => $now,
				'expires_at'          => $expires,
				'created_by'          => get_current_user_id(),
				'created_at'          => $now,
			),
			array( '%d', '%d', null, null, '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $inserted || absint( $wpdb->insert_id ) < 1 ) {
			return new \WP_Error( 'wpecir_comparison_save_failed', __( 'The comparison report could not be saved.', 'checkout-impact-regression-for-woocommerce' ) );
		}
		$run_id = absint( $wpdb->insert_id );

		foreach ( array( 'before' => $before, 'after' => $after ) as $side => $observations ) {
			foreach ( $observations as $field => $observation ) {
				$observation_json = wp_json_encode( $observation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				$saved = $wpdb->insert(
					$this->table( 'observations' ),
					array(
						'run_id'           => $run_id,
						'field_key'        => $side . '_' . str_replace( '.', '_', $field ),
						'state'            => $observation['state'],
						'observation_json' => $observation_json,
						'created_at'       => $now,
					),
					array( '%d', '%s', '%s', '%s', '%s' )
				);
				if ( false === $saved ) {
					$this->delete_manual_comparison_rows( $run_id );
					return new \WP_Error( 'wpecir_comparison_observation_save_failed', __( 'The comparison report could not save all observations; partial rows were cleaned up.', 'checkout-impact-regression-for-woocommerce' ) );
				}
			}
		}

		foreach ( $changes as $field => $change ) {
			$expected_json = array_key_exists( 'baseline', $change ) ? wp_json_encode( $change['baseline'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null;
			$observed_json = array_key_exists( 'current', $change ) ? wp_json_encode( $change['current'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null;
			$evidence_json = wp_json_encode(
				array(
					'source' => 'manual_observation',
					'reason' => isset( $change['reason'] ) ? sanitize_key( $change['reason'] ) : '',
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			$saved = $wpdb->insert(
				$this->table( 'findings' ),
				array(
					'run_id'         => $run_id,
					'field_key'      => $field,
					'classification' => $change['state'],
					'severity'       => 'unrated',
					'expected_json'  => $expected_json,
					'observed_json'  => $observed_json,
					'evidence_json'  => $evidence_json,
					'created_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $saved ) {
				$this->delete_manual_comparison_rows( $run_id );
				return new \WP_Error( 'wpecir_comparison_finding_save_failed', __( 'The comparison report could not save all findings; partial rows were cleaned up.', 'checkout-impact-regression-for-woocommerce' ) );
			}
		}

		/**
		 * Fires after a manual comparison report is saved.
		 *
		 * @param int $run_id      Report (run) identifier.
		 * @param int $scenario_id Scenario identifier.
		 */
		do_action( 'wpecir_manual_report_saved', $run_id, $scenario_id );

		return array(
			'id'         => $run_id,
			'state'      => 'manual_preview',
			'created_at' => $now,
			'expires_at' => $expires,
		);
	}

	/**
	 * List recent, unexpired manual comparison reports for one scenario.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @param int $limit Maximum report count.
	 * @return array|\WP_Error
	 */
	public function list_manual_comparisons( $scenario_id, $limit = 10 ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view comparison reports.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$scenario_id = absint( $scenario_id );
		$limit       = min( 20, max( 1, absint( $limit ) ) );
		$runs        = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id,scenario_version_id,baseline_id,state,context_fingerprint,created_at,expires_at FROM %i WHERE scenario_id = %d AND state = %s AND (expires_at IS NULL OR expires_at >= %s) ORDER BY created_at DESC,id DESC LIMIT %d', $this->table( 'runs' ),
				$scenario_id,
				'manual_preview',
				current_time( 'mysql', true ),
				$limit
			),
			ARRAY_A
		);

		$reports = array();
		foreach ( $runs as $run ) {
			$run_id = absint( $run['id'] );
			$observations = $wpdb->get_results(
				$wpdb->prepare( 'SELECT field_key,state,observation_json FROM %i WHERE run_id = %d ORDER BY id ASC', $this->table( 'observations' ), $run_id ),
				ARRAY_A
			);
			$findings = $wpdb->get_results(
				$wpdb->prepare( 'SELECT field_key,classification,severity,expected_json,observed_json,evidence_json FROM %i WHERE run_id = %d ORDER BY id ASC', $this->table( 'findings' ), $run_id ),
				ARRAY_A
			);

			$before = array();
			$after  = array();
			foreach ( $observations as $observation ) {
				$parts = explode( '_', $observation['field_key'], 2 );
				if ( 2 !== count( $parts ) || ! in_array( $parts[0], array( 'before', 'after' ), true ) ) {
					continue;
				}
				$field   = str_replace( '_', '.', $parts[1] );
				$decoded = json_decode( $observation['observation_json'], true );
				if ( ! is_array( $decoded ) ) {
					continue;
				}
				if ( 'before' === $parts[0] ) {
					$before[ $field ] = $decoded;
				} else {
					$after[ $field ] = $decoded;
				}
			}

			$changes = array();
			foreach ( $findings as $finding ) {
				$field = sanitize_key( $finding['field_key'] );
				$changes[ $field ] = array( 'state' => $finding['classification'] );
				$expected = json_decode( (string) $finding['expected_json'], true );
				$observed = json_decode( (string) $finding['observed_json'], true );
				$evidence = json_decode( (string) $finding['evidence_json'], true );
				if ( null !== $expected ) {
					$changes[ $field ]['baseline'] = $expected;
				}
				if ( null !== $observed ) {
					$changes[ $field ]['current'] = $observed;
				}
				if ( is_array( $evidence ) && ! empty( $evidence['reason'] ) ) {
					$changes[ $field ]['reason'] = sanitize_key( $evidence['reason'] );
				}
			}

			$summary = array( 'changed' => 0, 'missing' => 0, 'added' => 0, 'unchanged' => 0, 'not_comparable' => 0 );
			foreach ( $changes as $change ) {
				$key = isset( $change['state'] ) ? $change['state'] : 'not_comparable';
				if ( isset( $summary[ $key ] ) ) {
					++$summary[ $key ];
				}
			}

			$reports[] = array(
				'id'                  => $run_id,
				'scenario_version_id' => absint( $run['scenario_version_id'] ),
				'baseline_id'         => isset( $run['baseline_id'] ) ? absint( $run['baseline_id'] ) : 0,
				'state'               => $run['state'],
				'context_fingerprint' => $run['context_fingerprint'],
				'created_at'          => $run['created_at'],
				'expires_at'          => $run['expires_at'],
				'before'              => $before,
				'after'               => $after,
				'changes'             => $changes,
				'summary'             => $summary,
			);
		}

		return $reports;
	}

	/**
	 * Best-effort cleanup of a failed partial manual report write.
	 *
	 * @param int $run_id Run identifier.
	 * @return void
	 */
	private function delete_manual_comparison_rows( $run_id ) {
		global $wpdb;

		$run_id = absint( $run_id );
		$wpdb->delete( $this->table( 'observations' ), array( 'run_id' => $run_id ), array( '%d' ) );
		$wpdb->delete( $this->table( 'findings' ), array( 'run_id' => $run_id ), array( '%d' ) );
		$wpdb->delete( $this->table( 'runs' ), array( 'id' => $run_id ), array( '%d' ) );
	}

	/**
	 * Delete a scenario and dependent history after an authorized request.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @return true|\WP_Error
	 */
	public function delete_scenario( $scenario_id ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to delete checkout scenarios.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$scenario_id = absint( $scenario_id );
		$run_ids     = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE scenario_id = %d', $this->table( 'runs' ), $scenario_id ) );
		if ( $run_ids ) {
			$this->delete_where_in( 'observations', 'run_id', $run_ids );
			$this->delete_where_in( 'findings', 'run_id', $run_ids );
		}
		$wpdb->delete( $this->table( 'runs' ), array( 'scenario_id' => $scenario_id ), array( '%d' ) );
		$wpdb->delete( $this->table( 'baselines' ), array( 'scenario_id' => $scenario_id ), array( '%d' ) );
		$wpdb->delete( $this->table( 'scenario_versions' ), array( 'scenario_id' => $scenario_id ), array( '%d' ) );
		$deleted = $wpdb->delete( $this->table( 'scenarios' ), array( 'id' => $scenario_id ), array( '%d' ) );

		if ( false === $deleted ) {
			return new \WP_Error( 'wpecir_scenario_delete_failed', __( 'The scenario could not be deleted completely.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Record a redacted, idempotent change event for correlation.
	 *
	 * @param string $source       Supported event source.
	 * @param string $object_type  Type of changed object.
	 * @param string $object_id    Non-secret object identifier.
	 * @param string $context_hash Context fingerprint.
	 * @param string $occurred_at  UTC MySQL datetime.
	 * @return int|\WP_Error
	 */
	public function record_change_event( $source, $object_type, $object_id, $context_hash, $occurred_at = '', $system_generated = false, $object_label = '' ) {
		global $wpdb;

		if ( ! $system_generated && ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to record change events.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$source      = sanitize_key( $source );
		$object_type = sanitize_key( $object_type );
		$object_id   = sanitize_text_field( $object_id );
		$context_hash= sanitize_text_field( $context_hash );
		$allowed     = array( 'plugin_update', 'woocommerce_setting', 'plugin_setting', 'manual' );
		if ( ! in_array( $source, $allowed, true ) || '' === $object_type || '' === $object_id || strlen( $object_id ) > 191 || ! preg_match( '/^[a-f0-9]{64}$/', $context_hash ) ) {
			return new \WP_Error( 'wpecir_invalid_event', __( 'The change event is incomplete or contains unsupported data.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$occurred_at = '' !== $occurred_at ? sanitize_text_field( $occurred_at ) : current_time( 'mysql', true );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $occurred_at ) ) {
			return new \WP_Error( 'wpecir_invalid_event_time', __( 'The event timestamp must be a UTC MySQL datetime.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$object_hash = hash( 'sha256', $object_type . ':' . $object_id );
		$event_key   = hash( 'sha256', implode( '|', array( $source, $object_hash, $context_hash, $occurred_at ) ) );
		$table       = $this->table( 'change_events' );
		$existing    = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE event_key = %s', $table, $event_key ) );
		if ( $existing ) {
			return (int) $existing;
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'event_key'          => $event_key,
				'source'             => $source,
				'object_type'        => $object_type,
				'object_fingerprint' => $object_hash,
				'object_label'       => substr( sanitize_text_field( (string) $object_label ), 0, 191 ),
				'context_fingerprint'=> $context_hash,
				'occurred_at'        => $occurred_at,
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'wpecir_event_create_failed', __( 'The change event could not be saved.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$event_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a new plugin-update or settings-change event is recorded.
		 *
		 * @param int    $event_id    Event identifier.
		 * @param string $source      Event source.
		 * @param string $object_type Changed object type.
		 */
		do_action( 'wpecir_change_event_recorded', $event_id, $source, $object_type );

		return $event_id;
	}


	/**
	 * List a bounded set of privacy-safe plugin and settings change events.
	 *
	 * @param int $limit Maximum records to return.
	 * @return array|\WP_Error
	 */
	public function list_change_events( $limit = 50 ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to view change events.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$limit = max( 1, min( 100, absint( $limit ) ) );
		$table = $this->table( 'change_events' );

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, source, object_type, object_fingerprint, object_label, context_fingerprint, occurred_at, created_at FROM %i ORDER BY occurred_at DESC, id DESC LIMIT %d', $table, $limit ),
			ARRAY_A
		);
	}

	/**
	 * Purge a bounded batch of expired run records and their child rows.
	 *
	 * @param int $limit Maximum records to purge.
	 * @return int|\WP_Error Number of removed runs.
	 */
	public function purge_expired_runs( $limit = 100, $system_generated = false ) {
		global $wpdb;

		if ( ! $system_generated && ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', __( 'You are not allowed to clean up run history.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		$limit = min( 500, max( 1, absint( $limit ) ) );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE expires_at IS NOT NULL AND expires_at < %s ORDER BY expires_at ASC LIMIT %d', $this->table( 'runs' ),
				current_time( 'mysql', true ),
				$limit
			)
		);
		if ( ! $ids ) {
			return 0;
		}

		$ids = array_map( 'absint', $ids );
		$this->delete_where_in( 'observations', 'run_id', $ids );
		$this->delete_where_in( 'findings', 'run_id', $ids );
		$this->delete_where_in( 'runs', 'id', $ids );

		return count( $ids );
	}
}

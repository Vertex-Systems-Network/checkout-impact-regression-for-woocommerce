<?php
/**
 * Access-controlled persistence for scenarios, baselines and change events.
 *
 * @package WPEssential\CheckoutImpactRegression\Storage
 */

namespace WPEssential\CheckoutImpactRegression\Storage;

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
	 * Check the store-management capability used by plugin mutations.
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
		$blocked = '/(?:email|phone|mobile|first.?name|last.?name|address.?[12]|street|card|password|secret|token|api.?key|credential)/i';

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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to manage checkout scenarios.' );
		}

		$title = sanitize_text_field( $title );
		if ( '' === $title || strlen( $title ) > 191 ) {
			return new \WP_Error( 'wpecir_invalid_title', 'Scenario title is required and must be 191 characters or fewer.' );
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
			return new \WP_Error( 'wpecir_scenario_create_failed', 'The scenario could not be saved.' );
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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to manage checkout scenarios.' );
		}

		$scenario = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . $this->table( 'scenarios' ) . ' WHERE id = %d', absint( $scenario_id ) ) );
		if ( ! $scenario ) {
			return new \WP_Error( 'wpecir_scenario_not_found', 'The requested scenario does not exist.' );
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
		$version  = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . $versions . ' WHERE scenario_id = %d', $scenario_id ) );
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
			return new \WP_Error( 'wpecir_version_create_failed', 'The scenario version could not be saved.' );
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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to view checkout scenarios.' );
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table( 'scenarios' ) . ' WHERE id = %d', absint( $scenario_id ) ), ARRAY_A );
		if ( ! $row ) {
			return new \WP_Error( 'wpecir_scenario_not_found', 'The requested scenario does not exist.' );
		}

		$version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table( 'scenario_versions' ) . ' WHERE scenario_id = %d ORDER BY version DESC LIMIT 1',
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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to view checkout scenarios.' );
		}

		$page     = max( 1, absint( $page ) );
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$sql      = $wpdb->prepare(
			'SELECT * FROM ' . $this->table( 'scenarios' ) . ' ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
			$per_page,
			$offset
		);

		return $wpdb->get_results( $sql, ARRAY_A );
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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to view checkout scenarios.' );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table( 'scenario_versions' ) . ' WHERE scenario_id = %d ORDER BY version DESC', absint( $scenario_id ) ),
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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to approve a baseline.' );
		}

		$version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, scenario_id FROM ' . $this->table( 'scenario_versions' ) . ' WHERE id = %d AND scenario_id = %d',
				absint( $version_id ),
				absint( $scenario_id )
			)
		);
		if ( ! $version ) {
			return new \WP_Error( 'wpecir_version_not_found', 'A baseline must reference a version of the selected scenario.' );
		}

		$observation_json = $this->encode_safe_payload( $observation, 'baseline observation' );
		if ( is_wp_error( $observation_json ) ) {
			return $observation_json;
		}

		$context = sanitize_text_field( $context );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $context ) ) {
			return new \WP_Error( 'wpecir_invalid_context', 'A SHA-256 context fingerprint is required.' );
		}

		$table    = $this->table( 'baselines' );
		$revision = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(revision) FROM ' . $table . ' WHERE scenario_id = %d', absint( $scenario_id ) ) );
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
			return new \WP_Error( 'wpecir_baseline_create_failed', 'The approved baseline could not be saved.' );
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
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to view baselines.' );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table( 'baselines' ) . ' WHERE scenario_id = %d ORDER BY revision DESC', absint( $scenario_id ) ),
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
	 * Delete a scenario and dependent history after an authorized request.
	 *
	 * @param int $scenario_id Scenario identifier.
	 * @return true|\WP_Error
	 */
	public function delete_scenario( $scenario_id ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to delete checkout scenarios.' );
		}

		$scenario_id = absint( $scenario_id );
		$run_ids     = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $this->table( 'runs' ) . ' WHERE scenario_id = %d', $scenario_id ) );
		if ( $run_ids ) {
			$ids = implode( ',', array_map( 'absint', $run_ids ) );
			$wpdb->query( 'DELETE FROM ' . $this->table( 'observations' ) . ' WHERE run_id IN (' . $ids . ')' );
			$wpdb->query( 'DELETE FROM ' . $this->table( 'findings' ) . ' WHERE run_id IN (' . $ids . ')' );
		}
		$wpdb->delete( $this->table( 'runs' ), array( 'scenario_id' => $scenario_id ), array( '%d' ) );
		$wpdb->delete( $this->table( 'baselines' ), array( 'scenario_id' => $scenario_id ), array( '%d' ) );
		$wpdb->delete( $this->table( 'scenario_versions' ), array( 'scenario_id' => $scenario_id ), array( '%d' ) );
		$deleted = $wpdb->delete( $this->table( 'scenarios' ), array( 'id' => $scenario_id ), array( '%d' ) );

		if ( false === $deleted ) {
			return new \WP_Error( 'wpecir_scenario_delete_failed', 'The scenario could not be deleted completely.' );
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
	public function record_change_event( $source, $object_type, $object_id, $context_hash, $occurred_at = '', $system_generated = false ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to record change events.' );
		}

		$source      = sanitize_key( $source );
		$object_type = sanitize_key( $object_type );
		$object_id   = sanitize_text_field( $object_id );
		$context_hash= sanitize_text_field( $context_hash );
		$allowed     = array( 'plugin_update', 'woocommerce_setting', 'plugin_setting', 'manual' );
		if ( ! in_array( $source, $allowed, true ) || '' === $object_type || '' === $object_id || strlen( $object_id ) > 191 || ! preg_match( '/^[a-f0-9]{64}$/', $context_hash ) ) {
			return new \WP_Error( 'wpecir_invalid_event', 'The change event is incomplete or contains unsupported data.' );
		}

		$occurred_at = '' !== $occurred_at ? sanitize_text_field( $occurred_at ) : current_time( 'mysql', true );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $occurred_at ) ) {
			return new \WP_Error( 'wpecir_invalid_event_time', 'The event timestamp must be a UTC MySQL datetime.' );
		}

		$object_hash = hash( 'sha256', $object_type . ':' . $object_id );
		$event_key   = hash( 'sha256', implode( '|', array( $source, $object_hash, $context_hash, $occurred_at ) ) );
		$table       = $this->table( 'change_events' );
		$existing    = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE event_key = %s', $event_key ) );
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
				'context_fingerprint'=> $context_hash,
				'occurred_at'        => $occurred_at,
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'wpecir_event_create_failed', 'The change event could not be saved.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Purge a bounded batch of expired run records and their child rows.
	 *
	 * @param int $limit Maximum records to purge.
	 * @return int|\WP_Error Number of removed runs.
	 */
	public function purge_expired_runs( $limit = 100 ) {
		global $wpdb;

		if ( ! $this->can_manage() ) {
			return new \WP_Error( 'wpecir_forbidden', 'You are not allowed to clean up run history.' );
		}

		$limit = min( 500, max( 1, absint( $limit ) ) );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->table( 'runs' ) . ' WHERE expires_at IS NOT NULL AND expires_at < %s ORDER BY expires_at ASC LIMIT %d',
				current_time( 'mysql', true ),
				$limit
			)
		);
		if ( ! $ids ) {
			return 0;
		}

		$ids = array_map( 'absint', $ids );
		$list= implode( ',', $ids );
		$wpdb->query( 'DELETE FROM ' . $this->table( 'observations' ) . ' WHERE run_id IN (' . $list . ')' );
		$wpdb->query( 'DELETE FROM ' . $this->table( 'findings' ) . ' WHERE run_id IN (' . $list . ')' );
		$wpdb->query( 'DELETE FROM ' . $this->table( 'runs' ) . ' WHERE id IN (' . $list . ')' );

		return count( $ids );
	}
}

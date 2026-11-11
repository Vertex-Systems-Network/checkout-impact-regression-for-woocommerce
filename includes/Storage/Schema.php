<?php
/**
 * Creates the plugin's per-site schema.
 *
 * @package WPEssential\CheckoutImpactRegression\Storage
 */

namespace WPEssential\CheckoutImpactRegression\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned custom-table schema. This stores no records until a supported
 * scenario workflow is implemented.
 */
final class Schema {
	/**
	 * Current storage schema version.
	 *
	 * @var string
	 */
	const VERSION = '2';

	/**
	 * Option storing the installed schema version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'wpecir_schema_version';

	/**
	 * Register the guarded upgrade check for admin requests.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * Run schema installation during plugin activation.
	 *
	 * @return true|\WP_Error
	 */
	public static function install() {
		return self::maybe_upgrade( true );
	}

	/**
	 * Upgrade the plugin's own schema when needed.
	 *
	 * @param bool $activation Whether this is the plugin activation path.
	 * @return true|\WP_Error
	 */
	public static function maybe_upgrade( $activation = false ) {
		$installed_version = get_option( self::VERSION_OPTION, '' );

		if ( self::VERSION === $installed_version ) {
			return true;
		}

		if ( ! $activation && ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'wpecir_schema_forbidden', __( 'Schema upgrade requires an authorized store administrator.', 'checkout-impact-regression-for-woocommerce' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$tables           = self::get_table_names( $wpdb->prefix );
		$queries          = self::get_schema_queries( $tables, $charset_collate );

		foreach ( $queries as $query ) {
			dbDelta( $query );
		}

		foreach ( $tables as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

			if ( $table !== $found ) {
				return new \WP_Error( 'wpecir_schema_incomplete', __( 'The plugin storage schema could not be verified.', 'checkout-impact-regression-for-woocommerce' ) );
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );

		return true;
	}

	/**
	 * Build site-scoped table names from the active WordPress prefix.
	 *
	 * @param string $prefix Site database prefix.
	 * @return array<string,string>
	 */
	private static function get_table_names( $prefix ) {
		$base = $prefix . 'wpecir_';

		return array(
			'scenarios'         => $base . 'scenarios',
			'scenario_versions' => $base . 'scenario_versions',
			'baselines'         => $base . 'baselines',
			'change_events'     => $base . 'change_events',
			'runs'              => $base . 'runs',
			'observations'      => $base . 'observations',
			'findings'          => $base . 'findings',
		);
	}

	/**
	 * Return dbDelta-compatible table definitions.
	 *
	 * @param array<string,string> $tables Table names.
	 * @param string               $charset_collate Database charset and collation.
	 * @return string[]
	 */
	private static function get_schema_queries( $tables, $charset_collate ) {
		return array(
			"CREATE TABLE {$tables['scenarios']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				slug varchar(191) NOT NULL,
				title varchar(191) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY status (status),
				KEY updated_at (updated_at)
			) {$charset_collate};",
			"CREATE TABLE {$tables['scenario_versions']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				scenario_id bigint(20) unsigned NOT NULL,
				version int(10) unsigned NOT NULL,
				input_json longtext NOT NULL,
				expectation_json longtext NOT NULL,
				context_fingerprint char(64) NOT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY scenario_version (scenario_id,version),
				KEY context_fingerprint (context_fingerprint)
			) {$charset_collate};",
			"CREATE TABLE {$tables['baselines']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				scenario_id bigint(20) unsigned NOT NULL,
				scenario_version_id bigint(20) unsigned NOT NULL,
				revision int(10) unsigned NOT NULL,
				state varchar(20) NOT NULL DEFAULT 'pending',
				context_fingerprint char(64) NOT NULL,
				observation_json longtext NOT NULL,
				approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
				approved_at datetime NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY scenario_revision (scenario_id,revision),
				KEY scenario_version_id (scenario_version_id),
				KEY state (state)
			) {$charset_collate};",
			"CREATE TABLE {$tables['change_events']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_key char(64) NOT NULL,
				source varchar(64) NOT NULL,
				object_type varchar(64) NOT NULL,
				object_fingerprint char(64) NOT NULL,
				object_label varchar(191) NOT NULL DEFAULT '',
				context_fingerprint char(64) NOT NULL,
				occurred_at datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key),
				KEY source_type (source,object_type),
				KEY occurred_at (occurred_at)
			) {$charset_collate};",
			"CREATE TABLE {$tables['runs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				scenario_id bigint(20) unsigned NOT NULL,
				scenario_version_id bigint(20) unsigned NOT NULL,
				baseline_id bigint(20) unsigned NULL,
				change_event_id bigint(20) unsigned NULL,
				state varchar(24) NOT NULL,
				context_fingerprint char(64) NOT NULL,
				started_at datetime NULL,
				finished_at datetime NULL,
				expires_at datetime NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY scenario_created (scenario_id,created_at),
				KEY state_created (state,created_at),
				KEY expires_at (expires_at)
			) {$charset_collate};",
			"CREATE TABLE {$tables['observations']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				run_id bigint(20) unsigned NOT NULL,
				field_key varchar(191) NOT NULL,
				state varchar(24) NOT NULL,
				observation_json longtext NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY run_id (run_id),
				KEY run_field (run_id,field_key)
			) {$charset_collate};",
			"CREATE TABLE {$tables['findings']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				run_id bigint(20) unsigned NOT NULL,
				field_key varchar(191) NOT NULL,
				classification varchar(32) NOT NULL,
				severity varchar(16) NOT NULL,
				expected_json longtext NULL,
				observed_json longtext NULL,
				evidence_json longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY run_id (run_id),
				KEY classification (classification),
				KEY severity (severity)
			) {$charset_collate};",
		);
	}
}

<?php
/**
 * Contract tests for guarded, versioned schema installation.
 *
 * Uses a dbDelta/wpdb double; no WordPress database tables are created.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

$GLOBALS['wpecir_schema_option'] = '';
$GLOBALS['wpecir_schema_capabilities'] = array();
$GLOBALS['wpecir_schema_queries'] = array();
$GLOBALS['wpecir_schema_missing_table'] = '';
$GLOBALS['wpecir_schema_option_updates'] = array();
$GLOBALS['wpecir_schema_hooks'] = array();

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['wpecir_schema_capabilities'], true ); }
function get_option( $option, $default = false ) {
	return 'wpecir_schema_version' === $option ? $GLOBALS['wpecir_schema_option'] : $default;
}
function update_option( $option, $value, $autoload = null ) {
	$GLOBALS['wpecir_schema_option'] = $value;
	$GLOBALS['wpecir_schema_option_updates'][] = array( $option, $value, $autoload );
	return true;
}
function add_action( $hook, $callback ) { $GLOBALS['wpecir_schema_hooks'][ $hook ][] = $callback; }

class WPECIR_Schema_Test_WPDB {
	public $prefix = 'wp_';
	public $checked_tables = array();

	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function esc_like( $text ) { return $text; }
	public function prepare( $query, $table ) { return str_replace( '%s', "'" . $table . "'", $query ); }
	public function get_var( $query ) {
		if ( ! preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $matches ) ) {
			return null;
		}
		$table = $matches[1];
		$this->checked_tables[] = $table;
		return $table === $GLOBALS['wpecir_schema_missing_table'] ? null : $table;
	}
}

$GLOBALS['wpdb'] = new WPECIR_Schema_Test_WPDB();

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Storage\Schema;

function schema_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

Schema::register_hooks();
schema_check( isset( $GLOBALS['wpecir_schema_hooks']['admin_init'] ), 'guarded schema upgrade hook is registered' );

$denied = Schema::maybe_upgrade();
schema_check( is_wp_error( $denied ) && 'wpecir_schema_forbidden' === $denied->get_error_code(), 'unauthorized admin request cannot run the schema upgrade' );
schema_check( array() === $GLOBALS['wpecir_schema_queries'], 'unauthorized schema check does not call dbDelta' );
schema_check( array() === $GLOBALS['wpecir_schema_option_updates'], 'unauthorized schema check does not update the schema version' );

$GLOBALS['wpecir_schema_missing_table'] = 'wp_wpecir_findings';
$incomplete = Schema::install();
schema_check( is_wp_error( $incomplete ) && 'wpecir_schema_incomplete' === $incomplete->get_error_code(), 'missing table returns an incomplete-schema error' );
schema_check( 7 === count( $GLOBALS['wpecir_schema_queries'] ), 'schema installer submits all seven table definitions to dbDelta' );
schema_check( 7 === count( $GLOBALS['wpdb']->checked_tables ), 'schema installer verifies all seven tables after dbDelta' );
schema_check( '' === $GLOBALS['wpecir_schema_option'], 'schema version is not saved when any table is missing' );

$expected_tables = array(
	'wp_wpecir_scenarios',
	'wp_wpecir_scenario_versions',
	'wp_wpecir_baselines',
	'wp_wpecir_change_events',
	'wp_wpecir_runs',
	'wp_wpecir_observations',
	'wp_wpecir_findings',
);
foreach ( $expected_tables as $table ) {
	$found = false;
	foreach ( $GLOBALS['wpecir_schema_queries'] as $query ) {
		if ( false !== strpos( $query, 'CREATE TABLE ' . $table . ' (' ) ) {
			$found = true;
			break;
		}
	}
	schema_check( $found, 'schema definition exists for ' . $table );
}

$GLOBALS['wpecir_schema_missing_table'] = '';
$GLOBALS['wpdb']->checked_tables = array();
$installed = Schema::install();
schema_check( true === $installed, 'activation install completes when all tables are verified' );
schema_check( '1' === $GLOBALS['wpecir_schema_option'], 'schema version is saved only after all tables verify' );
schema_check( 14 === count( $GLOBALS['wpecir_schema_queries'] ), 'successful installation runs dbDelta for all seven tables' );
schema_check( 7 === count( $GLOBALS['wpdb']->checked_tables ), 'successful installation verifies every table' );
schema_check( array( 'wpecir_schema_version', '1', false ) === $GLOBALS['wpecir_schema_option_updates'][0], 'schema version is stored without autoload' );

$queries_before_noop = count( $GLOBALS['wpecir_schema_queries'] );
schema_check( true === Schema::maybe_upgrade(), 'matching schema version is a no-op' );
schema_check( $queries_before_noop === count( $GLOBALS['wpecir_schema_queries'] ), 'matching schema version does not rerun dbDelta' );

echo "Schema installation contract checks passed (mock database only; no live tables changed).\n";

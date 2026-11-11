<?php
/**
 * Contract tests for report retention and uninstall preferences.
 *
 * In-memory option doubles only; no database.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
$GLOBALS['wpecir_options'] = array();
$GLOBALS['wpecir_option_writes'] = array();

function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['wpecir_options'] ) ? $GLOBALS['wpecir_options'][ $name ] : $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['wpecir_options'][ $name ] = $value; $GLOBALS['wpecir_option_writes'][] = array( $name, $value, $autoload ); return true; }
function absint( $value ) { return abs( (int) $value ); }
class WP_Error { public $code; public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function settings_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use WPEssential\CheckoutImpactRegression\Settings;

settings_check( 30 === Settings::retention_days(), 'default retention is 30 days' );
settings_check( false === Settings::erase_on_uninstall(), 'erase on uninstall is off by default' );

foreach ( array( 6, 366, '0', '7.5', 'abc', array( 30 ), null, -10 ) as $bad ) {
	settings_check( is_wp_error( Settings::update( $bad, false ) ), 'invalid retention is rejected: ' . var_export( $bad, true ) );
}
settings_check( array() === $GLOBALS['wpecir_option_writes'], 'rejected settings write nothing' );
settings_check( is_wp_error( Settings::update( 30, 'yes' ) ), 'non-boolean erase preference is rejected' );

$saved = Settings::update( '90', true );
settings_check( is_array( $saved ) && 90 === $saved['retention_days'] && true === $saved['erase_on_uninstall'], 'valid settings are saved and returned' );
settings_check( array( 'wpecir_retention_days', 90, false ) === $GLOBALS['wpecir_option_writes'][0], 'retention is stored without autoload' );
settings_check( array( 'wpecir_erase_data_on_uninstall', 'yes', false ) === $GLOBALS['wpecir_option_writes'][1], 'erase flag uses the uninstall.php option and no autoload' );

$GLOBALS['wpecir_options']['wpecir_retention_days'] = 9999;
settings_check( 365 === Settings::retention_days(), 'out-of-range stored value is clamped' );
settings_check( 7 === Settings::update( 7, false )['retention_days'] && 365 === Settings::update( 365, false )['retention_days'], 'boundary values are accepted' );

echo "Settings contract checks passed (in-memory options only).\n";

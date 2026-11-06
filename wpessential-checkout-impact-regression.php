<?php
/**
 * Plugin Name: WPEssential Checkout Impact Regression
 * Description: Development scaffold for future checkout scenario comparisons; automatic checkout requests are disabled.
 * Version: 0.1.0
 * Text Domain: wpessential-checkout-impact-regression
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPECIR_VERSION', '0.1.0' );
define( 'WPECIR_FILE', __FILE__ );
define( 'WPECIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPECIR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register a minimal PSR-4 loader for this plugin's namespace.
 *
 * Namespace prefix: WPEssential\\CheckoutImpactRegression\\
 * Base directory:   includes/
 *
 * No Composer-generated files or CLI are required.
 *
 * @param string $class Fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix   = 'WPEssential\\CheckoutImpactRegression\\';
		$base_dir = WPECIR_PATH . 'includes/';
		$length   = strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $length ) ) {
			return;
		}

		$relative_class = substr( $class, $length );
		$file           = $base_dir . str_replace( '\\\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$admin_page = new \\WPEssential\\CheckoutImpactRegression\\Admin\\AdminPage();
		$admin_page->register_hooks();
	},
	20
);

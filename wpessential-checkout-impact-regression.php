<?php
/**
 * Plugin Name: WPEssential Checkout Impact Regression
 * Description: Compare saved checkout scenarios after store changes. Runtime checkout observation is currently disabled pending proof.
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

require_once WPECIR_PATH . 'includes/class-wpecir-admin-page.php';

add_action(
	'plugins_loaded',
	static function () {
		$admin_page = new WPECIR_Admin_Page();
		$admin_page->register_hooks();
	},
	20
);

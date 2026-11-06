<?php
/**
 * Plugin Name: WPEssential Checkout Impact Regression
 * Description: Development scaffold for future checkout scenario comparisons; automatic checkout requests are disabled.
 * Version: 0.2.0
 * Text Domain: wpessential-checkout-impact-regression
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPECIR_VERSION', '0.2.0' );
define( 'WPECIR_FILE', __FILE__ );
define( 'WPECIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPECIR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the Composer-generated PSR-4 autoloader.
 *
 * The release package must include the generated vendor directory.
 */
$wpecir_autoload_file = WPECIR_PATH . 'vendor/autoload.php';

if ( ! is_readable( $wpecir_autoload_file ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$message = __( 'WPEssential Checkout Impact Regression could not load its Composer dependencies. Reinstall the complete plugin package.', 'wpessential-checkout-impact-regression' );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	);

	return;
}

require_once $wpecir_autoload_file;

register_activation_hook(
	WPECIR_FILE,
	array( \WPEssential\CheckoutImpactRegression\Storage\Schema::class, 'install' )
);

add_action(
	'plugins_loaded',
	static function () {
		\WPEssential\CheckoutImpactRegression\Storage\Schema::register_hooks();
		\WPEssential\CheckoutImpactRegression\Privacy\PersonalData::register_hooks();
		\WPEssential\CheckoutImpactRegression\Events\ChangeEventCollector::register_hooks();

		$admin_page = new \WPEssential\CheckoutImpactRegression\Admin\AdminPage();
		$admin_page->register_hooks();
	},
	20
);

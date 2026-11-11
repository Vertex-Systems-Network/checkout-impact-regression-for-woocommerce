<?php
/**
 * Plugin Name:          Checkout Impact Regression for WooCommerce
 * Plugin URI:           https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce
 * Description:          Save checkout scenarios, approve a known-good baseline and compare shipping, coupon and payment behavior before and after a plugin update or settings change.
 * Version:              1.0.0
 * Requires at least:    6.9
 * Requires PHP:         8.2
 * Requires Plugins:     woocommerce
 * Author:               WPEssential
 * Author URI:           https://wpessential.org
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          checkout-impact-regression-for-woocommerce
 * WC requires at least: 10.8
 * WC tested up to:      11.2
 *
 * @package WPEssential\CheckoutImpactRegression
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPECIR_VERSION', '1.0.0' );
define( 'WPECIR_FILE', __FILE__ );
define( 'WPECIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPECIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WPECIR_SLUG', 'checkout-impact-regression-for-woocommerce' );
define( 'WPECIR_SITE_URL', 'https://wpessential.org/plugins/checkout-impact-regression-for-woocommerce' );

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

			$message = __( 'Checkout Impact Regression for WooCommerce could not load its files. Reinstall the complete plugin package.', 'checkout-impact-regression-for-woocommerce' );
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
register_activation_hook(
	WPECIR_FILE,
	array( \WPEssential\CheckoutImpactRegression\Storage\RetentionScheduler::class, 'schedule' )
);
register_deactivation_hook(
	WPECIR_FILE,
	array( \WPEssential\CheckoutImpactRegression\Storage\RetentionScheduler::class, 'unschedule' )
);

/*
 * The plugin never reads or writes orders and adds no checkout UI, so it is
 * compatible with High-Performance Order Storage and the Cart/Checkout blocks.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPECIR_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPECIR_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		\WPEssential\CheckoutImpactRegression\Storage\Schema::register_hooks();
		\WPEssential\CheckoutImpactRegression\Storage\RetentionScheduler::register_hooks();
		\WPEssential\CheckoutImpactRegression\Privacy\PersonalData::register_hooks();
		\WPEssential\CheckoutImpactRegression\Events\ChangeEventCollector::register_hooks();
		add_action( 'rest_api_init', array( \WPEssential\CheckoutImpactRegression\Rest\ScenarioController::class, 'register_routes' ) );

		$admin_page = new \WPEssential\CheckoutImpactRegression\Admin\AdminPage();
		$admin_page->register_hooks();

		/**
		 * Fires after the free plugin has loaded, so add-ons can extend it.
		 *
		 * @param string $version Plugin version.
		 */
		do_action( 'wpecir_loaded', WPECIR_VERSION );
	},
	20
);

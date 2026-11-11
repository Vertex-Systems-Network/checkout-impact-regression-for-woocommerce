<?php
/**
 * Contract tests for missing-autoloader and WooCommerce-aware admin guards.
 *
 * Runs with synthetic WordPress functions only; no live options, database,
 * checkout, order, payment, shipping, or coupon calls are made.
 *
 * @package WPEssential\\CheckoutImpactRegression
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
$GLOBALS['wpecir_dependency_hooks'] = array();
$GLOBALS['wpecir_dependency_activation_hooks'] = array();
$GLOBALS['wpecir_dependency_menus'] = array();
$GLOBALS['wpecir_dependency_styles'] = array();
$GLOBALS['wpecir_dependency_scripts'] = array();
$GLOBALS['wpecir_dependency_localized'] = array();
$GLOBALS['wpecir_dependency_capabilities'] = array();

function plugin_dir_path( $file ) { return '/wpecir-test-missing/'; }
function plugin_dir_url( $file ) { return 'https://example.test/plugins/wpecir/'; }
function add_action( $hook, $callback ) { $GLOBALS['wpecir_dependency_hooks'][ $hook ][] = $callback; }
function __( $message, $domain = '' ) { return $message; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['wpecir_dependency_capabilities'], true ); }
function register_activation_hook( $file, $callback ) { $GLOBALS['wpecir_dependency_activation_hooks'][] = array( 'activation', $file, $callback ); }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['wpecir_dependency_activation_hooks'][] = array( 'deactivation', $file, $callback ); }
function add_management_page( ...$args ) { $GLOBALS['wpecir_dependency_menus'][] = array_merge( array( 'tools.php' ), $args ); }
function add_submenu_page( ...$args ) { $GLOBALS['wpecir_dependency_menus'][] = $args; }
function plugin_basename( $file ) { return 'checkout-impact-regression-for-woocommerce/' . basename( $file ); }
function wp_set_script_translations( ...$args ) { return true; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function esc_url( $url ) { return $url; }
function add_filter( $hook, $callback ) { $GLOBALS['wpecir_dependency_hooks'][ $hook ][] = $callback; }
function wp_enqueue_style( ...$args ) { $GLOBALS['wpecir_dependency_styles'][] = $args; }
function wp_enqueue_script( ...$args ) { $GLOBALS['wpecir_dependency_scripts'][] = $args; }
function wp_localize_script( $handle, $object_name, $data ) { $GLOBALS['wpecir_dependency_localized'][] = array( $handle, $object_name, $data ); }
function wp_create_nonce( $action ) { return 'synthetic-rest-nonce'; }
function wp_die( $message, $title = '', $args = array() ) {
	throw new RuntimeException( $message . '|' . $title . '|' . ( isset( $args['response'] ) ? $args['response'] : '' ) );
}

function dependency_check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

// Force the plugin bootstrap's autoloader path to be absent.
require dirname( __DIR__, 2 ) . '/checkout-impact-regression-for-woocommerce.php';
dependency_check( isset( $GLOBALS['wpecir_dependency_hooks']['admin_notices'] ), 'missing Composer autoloader registers a recovery notice' );
dependency_check( empty( $GLOBALS['wpecir_dependency_activation_hooks'] ), 'missing autoloader prevents activation/deactivation callbacks from being registered' );
dependency_check( empty( $GLOBALS['wpecir_dependency_hooks']['plugins_loaded'] ), 'missing autoloader prevents plugin classes from being invoked' );

$notice = $GLOBALS['wpecir_dependency_hooks']['admin_notices'][0];
ob_start();
$notice();
$output = ob_get_clean();
dependency_check( '' === $output, 'dependency notice is hidden from users without management capability' );
$GLOBALS['wpecir_dependency_capabilities'] = array( 'manage_options' );
ob_start();
$notice();
$output = ob_get_clean();
dependency_check( false !== strpos( $output, 'notice-error' ) && false !== strpos( $output, 'Reinstall the complete plugin package.' ), 'authorized administrator sees the missing-package recovery notice' );

// Load the real Composer PSR-4 loader and exercise the admin capability fallback.
require __DIR__ . '/stubs.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$admin = new \WPEssential\CheckoutImpactRegression\Admin\AdminPage();
$admin->register_hooks();
$admin->register_menu();
$menu = end( $GLOBALS['wpecir_dependency_menus'] );
dependency_check( 'tools.php' === $menu[0] && 'manage_options' === $menu[3], 'screen lives under Tools with the admin-only capability when WooCommerce is unavailable' );

ob_start();
$admin->render_page();
$page = ob_get_clean();
dependency_check( false !== strpos( $page, 'aria-live="polite"' ) && false !== strpos( $page, 'role="status"' ), 'admin screen retains accessible loading and status landmarks' );

$admin->enqueue_assets( 'toplevel_page_unrelated' );
dependency_check( empty( $GLOBALS['wpecir_dependency_styles'] ) && empty( $GLOBALS['wpecir_dependency_scripts'] ), 'plugin assets are not enqueued on unrelated admin pages' );
$admin->enqueue_assets( 'tools_page_' . \WPEssential\CheckoutImpactRegression\Admin\AdminPage::PAGE_SLUG );
dependency_check( 1 === count( $GLOBALS['wpecir_dependency_styles'] ) && 1 === count( $GLOBALS['wpecir_dependency_scripts'] ), 'plugin assets are enqueued on the plugin page' );
$localized = end( $GLOBALS['wpecir_dependency_localized'] );
dependency_check( false === $localized[2]['woocommerceActive'], 'admin bootstrap explicitly reports missing WooCommerce' );
dependency_check( 'synthetic-rest-nonce' === $localized[2]['restNonce'], 'admin bootstrap localizes the REST nonce' );

$GLOBALS['wpecir_dependency_capabilities'] = array();
try {
	$admin->render_page();
	dependency_check( false, 'unauthorized user must not render the plugin page' );
} catch ( RuntimeException $error ) {
	dependency_check( false !== strpos( $error->getMessage(), '|403' ), 'unauthorized render returns a 403 denial' );
}

if ( ! class_exists( 'WooCommerce', false ) ) {
	eval( 'class WooCommerce {}' );
}
$admin->register_menu();
$menu = end( $GLOBALS['wpecir_dependency_menus'] );
dependency_check( 'woocommerce' === $menu[0] && 'manage_woocommerce' === $menu[3], 'screen lives under the WooCommerce menu with the manager capability when WooCommerce is active' );
$links = $admin->row_meta( array(), 'checkout-impact-regression-for-woocommerce/checkout-impact-regression-for-woocommerce.php' );
dependency_check( 1 === count( $links ) && false !== strpos( $links[0], '#docs' ), 'plugin row links to the in-plugin documentation' );
dependency_check( array() === $admin->row_meta( array(), 'other/other.php' ), 'row meta is not added to other plugins' );
$GLOBALS['wpecir_dependency_capabilities'] = array( 'manage_woocommerce' );
ob_start();
$admin->render_page();
$wc_page = ob_get_clean();
dependency_check( false !== strpos( $wc_page, 'wpecir-admin-root' ), 'WooCommerce manager can render the admin screen' );

echo "PASS: dependency and WooCommerce admin guard contracts" . PHP_EOL;

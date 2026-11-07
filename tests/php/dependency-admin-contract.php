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
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['wpecir_dependency_capabilities'], true ); }
function register_activation_hook( $file, $callback ) { $GLOBALS['wpecir_dependency_activation_hooks'][] = array( 'activation', $file, $callback ); }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['wpecir_dependency_activation_hooks'][] = array( 'deactivation', $file, $callback ); }
function add_menu_page( ...$args ) { $GLOBALS['wpecir_dependency_menus'][] = $args; }
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
require dirname( __DIR__, 2 ) . '/wpessential-checkout-impact-regression.php';
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
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$admin = new \\WPEssential\\CheckoutImpactRegression\\Admin\\AdminPage();
$admin->register_hooks();
$admin->register_menu();
$menu = end( $GLOBALS['wpecir_dependency_menus'] );
dependency_check( 'manage_options' === $menu[2], 'admin-only capability is used when WooCommerce is unavailable' );

ob_start();
$admin->render_page();
$page = ob_get_clean();
dependency_check( false !== strpos( $page, 'aria-live="polite"' ) && false !== strpos( $page, 'role="status"' ), 'admin screen retains accessible loading and status landmarks' );

$admin->enqueue_assets( 'toplevel_page_unrelated' );
dependency_check( empty( $GLOBALS['wpecir_dependency_styles'] ) && empty( $GLOBALS['wpecir_dependency_scripts'] ), 'plugin assets are not enqueued on unrelated admin pages' );
$admin->enqueue_assets( 'toplevel_page_' . \\WPEssential\\CheckoutImpactRegression\\Admin\\AdminPage::PAGE_SLUG );
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
dependency_check( 'manage_woocommerce' === $menu[2], 'WooCommerce manager capability is used when WooCommerce is active' );
$GLOBALS['wpecir_dependency_capabilities'] = array( 'manage_woocommerce' );
ob_start();
$admin->render_page();
$wc_page = ob_get_clean();
dependency_check( false !== strpos( $wc_page, 'wpecir-admin-root' ), 'WooCommerce manager can render the admin screen' );

echo "PASS: dependency and WooCommerce admin guard contracts" . PHP_EOL;

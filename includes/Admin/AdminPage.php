<?php
/**
 * WooCommerce admin screen for Checkout Impact.
 *
 * @package WPEssential\CheckoutImpactRegression
 */

namespace WPEssential\CheckoutImpactRegression\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin screen, its assets and the Plugins-screen links.
 */
final class AdminPage {
	/**
	 * The WordPress admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wpessential-checkout-impact';

	/**
	 * Maximum number of products offered in the scenario product picker.
	 *
	 * @var int
	 */
	const PRODUCT_LIMIT = 200;

	/**
	 * Register WordPress admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPECIR_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Admin URL of the screen, optionally pointing to one tab.
	 *
	 * @param string $tab Optional tab key.
	 * @return string
	 */
	public static function url( $tab = '' ) {
		$parent = class_exists( 'WooCommerce' ) ? 'admin.php' : 'tools.php';
		$url    = admin_url( $parent . '?page=' . self::PAGE_SLUG );
		return '' === $tab ? $url : $url . '#' . rawurlencode( $tab );
	}

	/**
	 * Add the screen under WooCommerce (or Tools when WooCommerce is inactive).
	 *
	 * @return void
	 */
	public function register_menu() {
		$capability = $this->get_required_capability();
		$title      = __( 'Checkout Impact', 'checkout-impact-regression-for-woocommerce' );

		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page( 'woocommerce', $title, $title, $capability, self::PAGE_SLUG, array( $this, 'render_page' ) );
			return;
		}

		add_management_page( $title, $title, $capability, self::PAGE_SLUG, array( $this, 'render_page' ) );
	}

	/**
	 * "Open" and "Settings" links on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			return $links;
		}

		$own = array(
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open', 'checkout-impact-regression-for-woocommerce' ) . '</a>',
			'<a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'checkout-impact-regression-for-woocommerce' ) . '</a>',
		);

		return array_merge( $own, $links );
	}

	/**
	 * "Documentation" link in the plugin row meta (the plugin site link comes
	 * from the Plugin URI header).
	 *
	 * @param string[] $meta Existing meta links.
	 * @param string   $file Plugin basename.
	 * @return string[]
	 */
	public function row_meta( $meta, $file ) {
		if ( plugin_basename( WPECIR_FILE ) !== $file ) {
			return $meta;
		}

		$meta[] = '<a href="' . esc_url( self::url( 'docs' ) ) . '">' . esc_html__( 'Documentation', 'checkout-impact-regression-for-woocommerce' ) . '</a>';
		$meta[] = '<a href="' . esc_url( WPECIR_WIKI_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Wiki', 'checkout-impact-regression-for-woocommerce' ) . '</a>';

		return $meta;
	}

	/**
	 * Load assets only on this plugin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed = array( 'woocommerce_page_' . self::PAGE_SLUG, 'tools_page_' . self::PAGE_SLUG );
		if ( ! in_array( $hook_suffix, $allowed, true ) ) {
			return;
		}

		$css_path = WPECIR_PATH . 'assets/css/admin.css';
		$js_path  = WPECIR_PATH . 'assets/js/admin.js';

		wp_enqueue_style(
			'wpecir-admin',
			WPECIR_URL . 'assets/css/admin.css',
			array(),
			is_readable( $css_path ) ? (string) filemtime( $css_path ) : WPECIR_VERSION
		);

		wp_enqueue_script(
			'wpecir-admin',
			WPECIR_URL . 'assets/js/admin.js',
			array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-hooks' ),
			is_readable( $js_path ) ? (string) filemtime( $js_path ) : WPECIR_VERSION,
			true
		);
		wp_set_script_translations( 'wpecir-admin', 'checkout-impact-regression-for-woocommerce' );

		wp_localize_script(
			'wpecir-admin',
			'WPECIRAdmin',
			array(
				'version'           => WPECIR_VERSION,
				'woocommerceActive' => class_exists( 'WooCommerce' ),
				'restNonce'         => wp_create_nonce( 'wp_rest' ),
				'pluginUrl'         => WPECIR_SITE_URL,
				'wikiUrl'           => WPECIR_WIKI_URL,
				'proActive'         => (bool) apply_filters( 'wpecir_pro_active', false ),
				'store'             => $this->store_data(),
			)
		);
	}

	/**
	 * Read-only WooCommerce reference data for the pickers. Nothing here is
	 * stored; it only helps the user type valid IDs.
	 *
	 * @return array
	 */
	private function store_data() {
		$data = array(
			'baseCountry'     => '',
			'currency'        => '',
			'countries'       => array(),
			'currencies'      => array(),
			'products'        => array(),
			'shippingMethods' => array(),
			'paymentMethods'  => array(),
		);

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return $data;
		}

		$countries = WC()->countries;
		if ( $countries ) {
			$data['baseCountry'] = (string) $countries->get_base_country();
			foreach ( $countries->get_countries() as $code => $name ) {
				$data['countries'][] = array( 'value' => (string) $code, 'label' => html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' ) );
			}
		}

		$data['currency'] = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		if ( function_exists( 'get_woocommerce_currencies' ) ) {
			foreach ( get_woocommerce_currencies() as $code => $name ) {
				$data['currencies'][] = array( 'value' => (string) $code, 'label' => $code . ' — ' . html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' ) );
			}
		}

		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => self::PRODUCT_LIMIT,
					'orderby' => 'title',
					'order'   => 'ASC',
					'return'  => 'objects',
				)
			);
			foreach ( $products as $product ) {
				$sku                = $product->get_sku();
				$data['products'][] = array(
					'value' => (string) $product->get_id(),
					'label' => wp_strip_all_tags( $product->get_name() ) . ( $sku ? ' (' . $sku . ')' : '' ) . ' #' . $product->get_id(),
				);
			}
		}

		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$zones   = \WC_Shipping_Zones::get_zones();
			$zones[] = array( 'zone_name' => __( 'Rest of the world', 'checkout-impact-regression-for-woocommerce' ), 'shipping_methods' => ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods() );
			foreach ( $zones as $zone ) {
				foreach ( (array) $zone['shipping_methods'] as $method ) {
					$data['shippingMethods'][] = array(
						'value' => $method->id . ':' . $method->get_instance_id(),
						'label' => wp_strip_all_tags( $zone['zone_name'] . ' — ' . $method->get_title() ) . ( 'yes' === $method->enabled ? '' : ' (' . __( 'disabled', 'checkout-impact-regression-for-woocommerce' ) . ')' ),
					);
				}
			}
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		foreach ( $gateways as $gateway ) {
			$data['paymentMethods'][] = array(
				'value' => (string) $gateway->id,
				'label' => wp_strip_all_tags( $gateway->get_title() ? $gateway->get_title() : $gateway->id ) . ( 'yes' === $gateway->enabled ? '' : ' (' . __( 'disabled', 'checkout-impact-regression-for-woocommerce' ) . ')' ),
			);
		}

		return $data;
	}

	/**
	 * Resolve the capability used by both menu registration and rendering.
	 *
	 * @return string
	 */
	private function get_required_capability() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Render the React mount point.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to view this page.', 'checkout-impact-regression-for-woocommerce' ),
				esc_html__( 'Access denied', 'checkout-impact-regression-for-woocommerce' ),
				array( 'response' => 403 )
			);
		}

		echo '<div class="wrap wpecir-admin-wrap"><div id="wpecir-admin-root" aria-live="polite"><p role="status">' . esc_html__( 'Loading Checkout Impact…', 'checkout-impact-regression-for-woocommerce' ) . '</p><noscript>' . esc_html__( 'Checkout Impact needs JavaScript enabled in your browser.', 'checkout-impact-regression-for-woocommerce' ) . '</noscript></div></div>';
	}
}

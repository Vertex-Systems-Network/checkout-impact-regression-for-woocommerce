<?php
/**
 * Read-only wp-admin shell for Checkout Impact.
 *
 * @package WPEssentialCheckoutImpactRegression
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the first, non-executing admin screen.
 */
final class WPECIR_Admin_Page {
	/**
	 * The WordPress admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wpessential-checkout-impact';

	/**
	 * Register WordPress admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the plugin's single admin destination.
	 *
	 * @return void
	 */
	public function register_menu() {
		$capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';

		add_menu_page(
			__( 'Checkout Impact', 'wpessential-checkout-impact-regression' ),
			__( 'Checkout Impact', 'wpessential-checkout-impact-regression' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			56
		);
	}

	/**
	 * Load assets only on this plugin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpecir-admin',
			WPECIR_URL . 'assets/css/admin.css',
			array(),
			WPECIR_VERSION
		);

		wp_enqueue_script(
			'wpecir-admin',
			WPECIR_URL . 'assets/js/admin.js',
			array( 'wp-element', 'wp-i18n' ),
			WPECIR_VERSION,
			true
		);

		wp_localize_script(
			'wpecir-admin',
			'WPECIRAdmin',
			array(
				'woocommerceActive' => class_exists( 'WooCommerce' ),
			)
		);
	}

	/**
	 * Render the React mount point.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to view this page.', 'wpessential-checkout-impact-regression' ),
				esc_html__( 'Access denied', 'wpessential-checkout-impact-regression' ),
				array( 'response' => 403 )
			);
		}

		echo '<div class="wrap wpecir-admin-wrap"><div id="wpecir-admin-root" aria-live="polite"></div></div>';
	}
}

<?php
/**
 * Fallback no-op WordPress functions for contract tests. Each test defines
 * the doubles it asserts on before including this file.
 */
if ( ! function_exists( 'do_action' ) ) {
	function do_action( ...$args ) {}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		// Simulate an up-to-date schema so Schema::ensure_current() is a no-op.
		if ( 'wpecir_schema_version' === $name && class_exists( '\\WPEssential\\CheckoutImpactRegression\\Storage\\Schema', false ) ) {
			return \WPEssential\CheckoutImpactRegression\Storage\Schema::VERSION;
		}
		return $default;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

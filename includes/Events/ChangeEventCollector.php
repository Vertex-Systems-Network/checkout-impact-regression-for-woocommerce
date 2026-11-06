<?php
/**
 * Collects supported change events without storing option values or secrets.
 *
 * @package WPEssential\CheckoutImpactRegression\Events
 */

namespace WPEssential\CheckoutImpactRegression\Events;

use WPEssential\CheckoutImpactRegression\Storage\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records plugin updates and tracked WooCommerce setting changes for correlation.
 */
final class ChangeEventCollector {
	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'upgrader_process_complete', array( __CLASS__, 'capture_upgrade' ), 10, 2 );
		add_action( 'updated_option', array( __CLASS__, 'capture_updated_option' ), 10, 3 );
		add_action( 'added_option', array( __CLASS__, 'capture_added_option' ), 10, 2 );
		add_action( 'deleted_option', array( __CLASS__, 'capture_deleted_option' ), 10, 1 );
	}

	/**
	 * Capture completed plugin updates, not downloads or installs of other types.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $options Upgrade context.
	 * @return void
	 */
	public static function capture_upgrade( $upgrader, $options ) {
		if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		$plugins = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugins = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) ) {
			$plugins = array( $options['plugin'] );
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$repository = new Repository();
		foreach ( $plugins as $plugin_file ) {
			$plugin_file = plugin_basename( $plugin_file );
			$path        = WP_PLUGIN_DIR . '/' . $plugin_file;
			$version     = is_readable( $path ) ? get_plugin_data( $path, false, false ) : array();
			$version     = isset( $version['Version'] ) ? sanitize_text_field( $version['Version'] ) : 'unknown';
			$object_id   = $plugin_file . '@' . $version;
			$context     = hash( 'sha256', 'plugin-update|' . $plugin_file . '|' . $version );

			$repository->record_change_event( 'plugin_update', 'plugin', $object_id, $context, '', true );
		}
	}

	/**
	 * Capture a supported settings update as a safe value fingerprint.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public static function capture_updated_option( $option, $old_value, $new_value ) {
		self::capture_option_value( $option, $new_value, 'woocommerce_setting' );
	}

	/**
	 * Capture a newly added tracked option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public static function capture_added_option( $option, $value ) {
		self::capture_option_value( $option, $value, 'woocommerce_setting' );
	}

	/**
	 * Record deletion without retaining the removed option value.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function capture_deleted_option( $option ) {
		$option = sanitize_key( $option );
		if ( ! self::is_tracked_option( $option ) ) {
			return;
		}

		$context = hash( 'sha256', 'deleted|' . $option );
		$repo    = new Repository();
		$repo->record_change_event( 'woocommerce_setting', 'setting', $option, $context, '', true );
	}

	/**
	 * Validate option scope and fingerprint only a redacted projection.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @param string $source Event source.
	 * @return void
	 */
	private static function capture_option_value( $option, $value, $source ) {
		$option = sanitize_key( $option );
		if ( ! self::is_tracked_option( $option ) || ! is_array( $value ) ) {
			return;
		}

		$projection = self::redact_value( $value );
		if ( ! is_array( $projection ) || empty( $projection ) ) {
			return;
		}

		$json = wp_json_encode( $projection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || strlen( $json ) > 65536 ) {
			return;
		}

		$context = hash( 'sha256', $json );
		$repo    = new Repository();
		$repo->record_change_event( $source, 'setting', $option, $context, '', true );
	}

	/**
	 * Check built-in and extension-registered option names.
	 *
	 * @param string $option Sanitized option name.
	 * @return bool
	 */
	private static function is_tracked_option( $option ) {
		if ( preg_match( '/^woocommerce_[a-z0-9_]+_settings$/', $option ) ) {
			return true;
		}

		$extra = apply_filters( 'wpecir_tracked_option_names', array() );
		return is_array( $extra ) && in_array( $option, array_map( 'sanitize_key', $extra ), true );
	}

	/**
	 * Remove sensitive fields and unsafe scalar values before hashing.
	 *
	 * @param mixed $value Settings value.
	 * @return mixed
	 */
	private static function redact_value( $value ) {
		$blocked = '/(?:secret|password|token|api.?key|consumer.?key|credential|email|phone|mobile|address|username)/i';
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && preg_match( $blocked, $key ) ) {
					continue;
				}
				$clean[ sanitize_key( $key ) ] = self::redact_value( $item );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		if ( ! is_string( $value ) || is_email( $value ) ) {
			return null;
		}

		$value = sanitize_text_field( $value );
		return substr( $value, 0, 500 );
	}
}

<?php
/**
 * Bootstrap for MP Replace Receipt Gift Card.
 *
 * Scope:
 * - This plugin ONLY modifies payload/fields for the FIRST receipt (when it is being built by gateways).
 * - It does NOT send any receipts by itself and does NOT implement "second receipt" logic.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_Replace_Receipt_Gift_Card_Plugin {
	/** @var bool */
	private static $booted = false;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		// WooCommerce is required to run runtime logic.
		if ( ! self::is_woocommerce_active() ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice_woocommerce_missing' ) );
			}
			MP_RRGC_Logger::log( 'ERROR', 0, 'init_skip_woocommerce_missing' );
			return;
		}

		// Master switch (defaults to disabled until admin UI is implemented in later steps).
		if ( ! MP_RRGC_Settings::is_enabled() ) {
			if ( is_admin() && class_exists( 'MP_RRGC_Admin' ) ) {
				MP_RRGC_Admin::init();
			}
			MP_RRGC_Logger::log( 'DEBUG', 0, 'init_skip_plugin_disabled' );
			return;
		}

		if ( is_admin() && class_exists( 'MP_RRGC_Admin' ) ) {
			MP_RRGC_Admin::init();
		}

		MP_RRGC_Logger::log( 'INFO', 0, 'init_runtime_hooks' );
		// Single facade for registering all runtime hooks.
		MP_RRGC_Orchestrator::init_hooks();
	}

	private static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Order' ) && class_exists( 'WC_Product' );
	}

	public static function render_admin_notice_woocommerce_missing(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'MP Replace Receipt Gift Card: WooCommerce is not active. The plugin will not run until WooCommerce is installed and activated.',
			'mp-replace-receipt-gift-card'
		);
		echo '</p></div>';
	}
}


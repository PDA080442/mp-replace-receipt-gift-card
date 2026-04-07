<?php
/**
 * Plugin Name: MP Replace Receipt Gift Card
 * Description: Replaces first receipt fields for gift card purchases (YooKassa + Robokassa) in WooCommerce.
 * Version: 0.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: MP
 * Text Domain: mp-replace-receipt-gift-card
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent fatal errors when another copy of this plugin (with same classes) is loaded.
if ( class_exists( 'MP_Replace_Receipt_Gift_Card_Plugin', false ) ) {
	return;
}

define( 'MP_REPLACE_RECEIPT_GIFT_CARD_VERSION', '0.0.1' );
define( 'MP_RRGC_PLUGIN_FILE', __FILE__ );
define( 'MP_RRGC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 70400 ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'MP Replace Receipt Gift Card requires PHP 7.4 or higher.', 'mp-replace-receipt-gift-card' );
			echo '</p></div>';
		}
	);
	return;
}

add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			'mp-replace-receipt-gift-card',
			false,
			dirname( plugin_basename( MP_RRGC_PLUGIN_FILE ) ) . '/languages'
		);
	},
	20
);

add_action(
	'before_woocommerce_init',
	static function () {
		try {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					MP_RRGC_PLUGIN_FILE,
					true
				);
			}
		} catch ( Throwable $e ) {
			update_option( 'mp_rrgc_bootstrap_error', 'HPOS declaration failed: ' . $e->getMessage(), false );
		}
	}
);

if ( ! function_exists( 'mp_rrgc_load_classes' ) ) {
	/**
	 * Lazy-load classes to avoid activation-time fatals in noisy environments.
	 */
	function mp_rrgc_load_classes(): void {
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-rrgc-settings.php';
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-rrgc-logger.php';
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-rrgc-gift-detector.php';
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-rrgc-yk-replacer.php';
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-rrgc-rb-replacer.php';
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-rrgc-orchestrator.php';
		require_once MP_RRGC_PLUGIN_DIR . 'includes/class-mp-replace-receipt-gift-card-plugin.php';
		require_once MP_RRGC_PLUGIN_DIR . 'admin/class-mp-rrgc-admin.php';
	}
}

add_action(
	'admin_notices',
	static function () {
		$error = (string) get_option( 'mp_rrgc_bootstrap_error', '' );
		if ( '' === $error || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'MP Replace Receipt Gift Card bootstrap error:', 'mp-replace-receipt-gift-card' ) . ' ';
		echo esc_html( $error );
		echo '</p></div>';
	},
	1
);

add_action(
	'plugins_loaded',
	static function () {
		try {
			mp_rrgc_load_classes();
			MP_Replace_Receipt_Gift_Card_Plugin::init();
			delete_option( 'mp_rrgc_bootstrap_error' );
		} catch ( Throwable $e ) {
			update_option( 'mp_rrgc_bootstrap_error', $e->getMessage(), false );
		}
	},
	20
);


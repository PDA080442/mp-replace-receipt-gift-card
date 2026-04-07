<?php
/**
 * Admin page scaffold.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_Admin {
	private const PAGE_SLUG = 'mp-replace-receipt-gift-card';

	/** @var bool */
	private static $inited = false;

	public static function init(): void {
		if ( self::$inited ) {
			return;
		}

		self::$inited = true;

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Replace Gift Card Receipt', 'mp-replace-receipt-gift-card' ),
			__( 'Replace Gift Receipt', 'mp-replace-receipt-gift-card' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function on_admin_init(): void {
		// Step 10: settings registration will be implemented in step 11.
	}

	public static function enqueue_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( (string) $screen->id, 'woocommerce_page_' . self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'mp-rrgc-admin',
			plugins_url( 'assets/admin/css/mp-rrgc-admin.css', MP_RRGC_PLUGIN_FILE ),
			array(),
			MP_REPLACE_RECEIPT_GIFT_CARD_VERSION
		);
		wp_enqueue_script(
			'mp-rrgc-admin',
			plugins_url( 'assets/admin/js/mp-rrgc-admin.js', MP_RRGC_PLUGIN_FILE ),
			array( 'jquery' ),
			MP_REPLACE_RECEIPT_GIFT_CARD_VERSION,
			true
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Access denied.', 'mp-replace-receipt-gift-card' ) );
		}

		$active_tab = self::get_active_tab();
		$tabs       = self::get_tabs();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'MP Replace Receipt Gift Card', 'mp-replace-receipt-gift-card' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab_key => $tab_label ) {
			$url   = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . rawurlencode( $tab_key ) );
			$class = 'nav-tab' . ( $tab_key === $active_tab ? ' nav-tab-active' : '' );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab_label ) . '</a>';
		}
		echo '</h2>';

		settings_errors();

		echo '<div class="mp-rrgc-section">';
		switch ( $active_tab ) {
			case 'yookassa':
				self::render_tab_yookassa();
				break;
			case 'robokassa':
				self::render_tab_robokassa();
				break;
			case 'diagnostics':
				self::render_tab_diagnostics();
				break;
			case 'common':
			default:
				self::render_tab_common();
				break;
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * @return array<string,string>
	 */
	private static function get_tabs(): array {
		return array(
			'common'      => __( 'Common', 'mp-replace-receipt-gift-card' ),
			'yookassa'    => __( 'YooKassa', 'mp-replace-receipt-gift-card' ),
			'robokassa'   => __( 'Robokassa', 'mp-replace-receipt-gift-card' ),
			'diagnostics' => __( 'Diagnostics', 'mp-replace-receipt-gift-card' ),
		);
	}

	private static function get_active_tab(): string {
		$tabs = self::get_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'common';

		if ( ! isset( $tabs[ $tab ] ) ) {
			return 'common';
		}

		return $tab;
	}

	private static function render_tab_common(): void {
		echo '<h2>' . esc_html__( 'Common Settings', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'This section will contain global plugin options and gift-card detection settings.', 'mp-replace-receipt-gift-card' ) . '</p>';
	}

	private static function render_tab_yookassa(): void {
		echo '<h2>' . esc_html__( 'YooKassa', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'This section will contain first receipt override settings for YooKassa.', 'mp-replace-receipt-gift-card' ) . '</p>';
	}

	private static function render_tab_robokassa(): void {
		echo '<h2>' . esc_html__( 'Robokassa', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'This section will contain first receipt override settings for Robokassa.', 'mp-replace-receipt-gift-card' ) . '</p>';
	}

	private static function render_tab_diagnostics(): void {
		echo '<h2>' . esc_html__( 'Diagnostics', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'Diagnostics and inspectors will be added in later steps.', 'mp-replace-receipt-gift-card' ) . '</p>';
	}
}


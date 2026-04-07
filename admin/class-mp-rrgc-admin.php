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
	private const GROUP_COMMON = 'mp_rrgc_common_group';
	private const GROUP_YK     = 'mp_rrgc_yk_group';
	private const GROUP_RB     = 'mp_rrgc_rb_group';

	/** @var bool */
	private static $inited = false;
	/** @var bool */
	private static $settings_registered = false;

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
		self::register_settings();
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
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_COMMON );
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Save settings', 'mp-replace-receipt-gift-card' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_tab_yookassa(): void {
		echo '<h2>' . esc_html__( 'YooKassa', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'This section will contain first receipt override settings for YooKassa.', 'mp-replace-receipt-gift-card' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_YK );
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Save settings', 'mp-replace-receipt-gift-card' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_tab_robokassa(): void {
		echo '<h2>' . esc_html__( 'Robokassa', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'This section will contain first receipt override settings for Robokassa.', 'mp-replace-receipt-gift-card' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_RB );
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Save settings', 'mp-replace-receipt-gift-card' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_tab_diagnostics(): void {
		echo '<h2>' . esc_html__( 'Diagnostics', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'Diagnostics and inspectors will be added in later steps.', 'mp-replace-receipt-gift-card' ) . '</p>';
	}

	private static function register_settings(): void {
		if ( self::$settings_registered ) {
			return;
		}
		self::$settings_registered = true;

		// Common options.
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_ENABLED, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_DEBUG, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_DETECTION_MODE, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_detection_mode' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_GIFT_PRODUCT_IDS, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_int_list_csv' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_GIFT_CATEGORY_IDS, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_int_list_csv' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_GIFT_META_KEY, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_GIFT_META_VALUE, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_GIFT_PRODUCT_TYPE, array( 'sanitize_callback' => 'sanitize_key' ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_ONLY_GIFT_ONLY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_ALLOW_MIXED_CART, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_ALLOWED_GATEWAYS, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_gateways' ) ) );

		// YooKassa options.
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_ENABLED, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_PAYMENT_MODE, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_yk_payment_mode' ) ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_PAYMENT_SUBJECT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_yk_payment_subject' ) ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_DESCRIPTION_TEMPLATE, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_template' ) ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_VAT_CODE_OVERRIDE, array( 'sanitize_callback' => 'sanitize_key' ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_APPLY_TO_SHIPPING, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_ONLY_GIFT_LINES, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_YK, MP_RRGC_Settings::OPTION_YK_FORCE_OVERRIDE, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );

		// Robokassa options.
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_ENABLED, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_PAYMENT_METHOD, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_rb_payment_method' ) ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_PAYMENT_OBJECT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_rb_payment_object' ) ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_NAME_TEMPLATE, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_template' ) ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_TAX_OVERRIDE, array( 'sanitize_callback' => 'sanitize_key' ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_APPLY_TO_SHIPPING, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_ONLY_GIFT_LINES, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( self::GROUP_RB, MP_RRGC_Settings::OPTION_RB_FORCE_OVERRIDE, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_bool( $value ): string {
		return ! empty( $value ) ? '1' : '0';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_int_list_csv( $value ): string {
		$items = array();
		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_string( $value ) ) {
			$items = preg_split( '/\s*,\s*/', $value ) ?: array();
		} elseif ( is_scalar( $value ) ) {
			$items = array( (string) $value );
		}

		$items = array_map( 'absint', $items );
		$items = array_values( array_unique( array_filter( $items ) ) );

		return implode( ',', $items );
	}

	/**
	 * @param mixed $value
	 * @return string[]
	 */
	public static function sanitize_gateways( $value ): array {
		$list = array();
		if ( is_array( $value ) ) {
			$list = $value;
		} elseif ( is_string( $value ) ) {
			$list = preg_split( '/\s*,\s*/', $value ) ?: array();
		}

		$list = array_map(
			static function ( $v ) {
				return sanitize_key( (string) $v );
			},
			$list
		);

		return array_values( array_unique( array_filter( $list ) ) );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_detection_mode( $value ): string {
		$value = sanitize_key( (string) $value );
		if ( ! in_array( $value, MP_RRGC_Settings::allowed_detection_modes(), true ) ) {
			add_settings_error(
				self::GROUP_COMMON,
				'mp_rrgc_invalid_detection_mode',
				esc_html__( 'Invalid detection mode. Fallback to product_ids.', 'mp-replace-receipt-gift-card' )
			);
			return 'product_ids';
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_yk_payment_mode( $value ): string {
		$value = sanitize_key( (string) $value );
		if ( ! in_array( $value, MP_RRGC_Settings::allowed_payment_modes_yk(), true ) ) {
			add_settings_error(
				self::GROUP_YK,
				'mp_rrgc_invalid_yk_mode',
				esc_html__( 'Invalid YooKassa payment mode. Fallback to advance.', 'mp-replace-receipt-gift-card' )
			);
			return 'advance';
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_yk_payment_subject( $value ): string {
		$value = sanitize_key( (string) $value );
		if ( ! in_array( $value, MP_RRGC_Settings::allowed_payment_subjects_yk(), true ) ) {
			add_settings_error(
				self::GROUP_YK,
				'mp_rrgc_invalid_yk_subject',
				esc_html__( 'Invalid YooKassa payment subject. Fallback to payment.', 'mp-replace-receipt-gift-card' )
			);
			return 'payment';
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_rb_payment_method( $value ): string {
		$value = sanitize_key( (string) $value );
		if ( ! in_array( $value, MP_RRGC_Settings::allowed_payment_methods_rb(), true ) ) {
			add_settings_error(
				self::GROUP_RB,
				'mp_rrgc_invalid_rb_method',
				esc_html__( 'Invalid Robokassa payment method. Fallback to advance.', 'mp-replace-receipt-gift-card' )
			);
			return 'advance';
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_rb_payment_object( $value ): string {
		$value = sanitize_key( (string) $value );
		if ( ! in_array( $value, MP_RRGC_Settings::allowed_payment_objects_rb(), true ) ) {
			add_settings_error(
				self::GROUP_RB,
				'mp_rrgc_invalid_rb_object',
				esc_html__( 'Invalid Robokassa payment object. Fallback to payment.', 'mp-replace-receipt-gift-card' )
			);
			return 'payment';
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_template( $value ): string {
		$value = sanitize_text_field( (string) $value );
		if ( strlen( $value ) > 512 ) {
			$value = substr( $value, 0, 512 );
		}
		return $value;
	}
}


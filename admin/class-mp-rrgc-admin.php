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
	private const AJAX_NONCE_ACTION = 'mp_rrgc_admin_ajax';
	private const DIAGNOSTICS_MAX_ITEMS = 50;

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
		add_action( 'wp_ajax_mp_rrgc_inspect_product', array( __CLASS__, 'ajax_inspect_product' ) );
		add_action( 'wp_ajax_mp_rrgc_inspect_order_yk', array( __CLASS__, 'ajax_inspect_order_yk' ) );
		add_action( 'wp_ajax_mp_rrgc_inspect_order_rb', array( __CLASS__, 'ajax_inspect_order_rb' ) );
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
		wp_localize_script(
			'mp-rrgc-admin',
			'mpRrgcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE_ACTION ),
			)
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
		self::render_compatibility_notices();

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
		$enabled             = MP_RRGC_Settings::is_enabled();
		$debug               = MP_RRGC_Settings::is_debug_enabled();
		$detection_mode      = MP_RRGC_Settings::get_detection_mode();
		$gift_product_ids    = implode( ',', MP_RRGC_Settings::get_gift_product_ids() );
		$gift_category_ids   = MP_RRGC_Settings::get_gift_category_ids();
		$gift_meta_key       = MP_RRGC_Settings::get_gift_meta_key();
		$gift_meta_value     = MP_RRGC_Settings::get_gift_meta_value();
		$gift_product_type   = MP_RRGC_Settings::get_gift_product_type();
		$only_gift_only      = MP_RRGC_Settings::only_if_order_is_gift_only();
		$allow_mixed_cart    = MP_RRGC_Settings::allow_mixed_cart();
		$selected_gateways   = MP_RRGC_Settings::get_allowed_gateways();
		$hook_priority       = MP_RRGC_Settings::get_hook_priority();
		$categories          = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$available_gateways  = self::get_available_gateway_options();

		echo '<h2>' . esc_html__( 'Common Settings', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_COMMON );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Plugin enabled', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_ENABLED ) . '" value="1" ' . checked( $enabled, true, false ) . '> ' . esc_html__( 'Enable plugin runtime', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Debug log', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_DEBUG ) . '" value="1" ' . checked( $debug, true, false ) . '> ' . esc_html__( 'Enable DEBUG level logging', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-detection-mode">' . esc_html__( 'Gift-card detection mode', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td>';
		echo '<select id="mp-rrgc-detection-mode" name="' . esc_attr( MP_RRGC_Settings::OPTION_DETECTION_MODE ) . '">';
		foreach ( MP_RRGC_Settings::allowed_detection_modes() as $mode ) {
			echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $detection_mode, $mode, false ) . '>' . esc_html( $mode ) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr class="mp-rrgc-detection mp-rrgc-detection-product_ids">';
		echo '<th scope="row"><label for="mp-rrgc-gift-product-ids">' . esc_html__( 'Gift product IDs', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-gift-product-ids" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_GIFT_PRODUCT_IDS ) . '" value="' . esc_attr( $gift_product_ids ) . '" placeholder="12,34,56">';
		echo '<p class="description">' . esc_html__( 'Comma-separated WooCommerce product IDs.', 'mp-replace-receipt-gift-card' ) . '</p></td>';
		echo '</tr>';

		echo '<tr class="mp-rrgc-detection mp-rrgc-detection-category">';
		echo '<th scope="row">' . esc_html__( 'Gift categories', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><select multiple size="6" style="min-width:280px;" name="' . esc_attr( MP_RRGC_Settings::OPTION_GIFT_CATEGORY_IDS ) . '[]">';
		if ( ! is_wp_error( $categories ) && is_array( $categories ) ) {
			foreach ( $categories as $cat ) {
				if ( ! $cat instanceof WP_Term ) {
					continue;
				}
				echo '<option value="' . esc_attr( (string) $cat->term_id ) . '" ' . selected( in_array( (int) $cat->term_id, $gift_category_ids, true ), true, false ) . '>';
				echo esc_html( $cat->name . ' (#' . $cat->term_id . ')' );
				echo '</option>';
			}
		}
		echo '</select></td>';
		echo '</tr>';

		echo '<tr class="mp-rrgc-detection mp-rrgc-detection-meta">';
		echo '<th scope="row"><label for="mp-rrgc-meta-key">' . esc_html__( 'Gift meta key', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-meta-key" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_GIFT_META_KEY ) . '" value="' . esc_attr( $gift_meta_key ) . '"></td>';
		echo '</tr>';

		echo '<tr class="mp-rrgc-detection mp-rrgc-detection-meta">';
		echo '<th scope="row"><label for="mp-rrgc-meta-value">' . esc_html__( 'Gift meta value (optional)', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-meta-value" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_GIFT_META_VALUE ) . '" value="' . esc_attr( $gift_meta_value ) . '"></td>';
		echo '</tr>';

		echo '<tr class="mp-rrgc-detection mp-rrgc-detection-product_type">';
		echo '<th scope="row"><label for="mp-rrgc-product-type">' . esc_html__( 'Gift product type', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-product-type" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_GIFT_PRODUCT_TYPE ) . '" value="' . esc_attr( $gift_product_type ) . '" placeholder="gift_card"></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Gift-only orders only', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_ONLY_GIFT_ONLY ) . '" value="1" ' . checked( $only_gift_only, true, false ) . '> ' . esc_html__( 'Process only orders containing gift-card items and nothing else', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Allow mixed cart', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_ALLOW_MIXED_CART ) . '" value="1" ' . checked( $allow_mixed_cart, true, false ) . '> ' . esc_html__( 'Allow replacements when order has both gift and regular items', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Allowed gateways', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><select multiple size="6" style="min-width:280px;" name="' . esc_attr( MP_RRGC_Settings::OPTION_ALLOWED_GATEWAYS ) . '[]">';
		foreach ( $available_gateways as $gateway_id => $gateway_label ) {
			echo '<option value="' . esc_attr( $gateway_id ) . '" ' . selected( in_array( $gateway_id, $selected_gateways, true ), true, false ) . '>';
			echo esc_html( $gateway_label . ' (' . $gateway_id . ')' );
			echo '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Leave empty to allow any gateway.', 'mp-replace-receipt-gift-card' ) . '</p></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-hook-priority">' . esc_html__( 'Hook priority', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-hook-priority" class="small-text" type="number" min="1" max="9999" name="' . esc_attr( MP_RRGC_Settings::OPTION_HOOK_PRIORITY ) . '" value="' . esc_attr( (string) $hook_priority ) . '">';
		echo '<p class="description">' . esc_html__( 'Used for YooKassa/Robokassa receipt filters. Increase if another plugin overrides values after this plugin.', 'mp-replace-receipt-gift-card' ) . '</p></td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Save settings', 'mp-replace-receipt-gift-card' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_tab_yookassa(): void {
		$enabled           = MP_RRGC_Settings::is_yk_enabled();
		$payment_mode      = MP_RRGC_Settings::get_yk_payment_mode();
		$payment_subject   = MP_RRGC_Settings::get_yk_payment_subject();
		$template          = MP_RRGC_Settings::get_yk_description_template();
		$vat_override      = MP_RRGC_Settings::get_yk_vat_code_override();
		$apply_to_shipping = MP_RRGC_Settings::yk_apply_to_shipping();
		$only_gift_lines   = MP_RRGC_Settings::yk_only_gift_lines();
		$force_override    = MP_RRGC_Settings::yk_force_override();
		$errors            = MP_RRGC_Settings::validate_yk_rules();

		echo '<h2>' . esc_html__( 'YooKassa', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_YK );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'YooKassa enabled', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_ENABLED ) . '" value="1" ' . checked( $enabled, true, false ) . '> ' . esc_html__( 'Enable YooKassa replacements', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-yk-payment-mode">' . esc_html__( 'payment_mode', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><select id="mp-rrgc-yk-payment-mode" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_PAYMENT_MODE ) . '">';
		foreach ( MP_RRGC_Settings::allowed_payment_modes_yk() as $mode ) {
			echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $payment_mode, $mode, false ) . '>' . esc_html( $mode ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-yk-payment-subject">' . esc_html__( 'payment_subject', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><select id="mp-rrgc-yk-payment-subject" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_PAYMENT_SUBJECT ) . '">';
		foreach ( MP_RRGC_Settings::allowed_payment_subjects_yk() as $subject ) {
			echo '<option value="' . esc_attr( $subject ) . '" ' . selected( $payment_subject, $subject, false ) . '>' . esc_html( $subject ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-yk-description-template">' . esc_html__( 'Description template', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-yk-description-template" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_DESCRIPTION_TEMPLATE ) . '" value="' . esc_attr( $template ) . '" placeholder="%order_number% Gift card">';
		echo '<p class="description">' . esc_html__( 'Supports placeholders: %order_id%, %order_number%, %line_no%.', 'mp-replace-receipt-gift-card' ) . '</p></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-yk-vat-override">' . esc_html__( 'VAT override (optional)', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-yk-vat-override" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_VAT_CODE_OVERRIDE ) . '" value="' . esc_attr( $vat_override ) . '" placeholder="1"></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Apply to shipping', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_APPLY_TO_SHIPPING ) . '" value="1" ' . checked( $apply_to_shipping, true, false ) . '> ' . esc_html__( 'Replace shipping line as well', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Only gift lines', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_ONLY_GIFT_LINES ) . '" value="1" ' . checked( $only_gift_lines, true, false ) . '> ' . esc_html__( 'Apply replacement only to gift-card lines', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Force override', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_YK_FORCE_OVERRIDE ) . '" value="1" ' . checked( $force_override, true, false ) . '> ' . esc_html__( 'Override existing values even if they are already set', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Save settings', 'mp-replace-receipt-gift-card' ) . '</button></p>';
		echo '</form>';

		echo '<hr />';
		echo '<h3>' . esc_html__( 'Preflight', 'mp-replace-receipt-gift-card' ) . '</h3>';
		if ( empty( $errors ) ) {
			echo '<p><span class="mp-rrgc-badge mp-rrgc-badge--pass">' . esc_html__( 'PASS', 'mp-replace-receipt-gift-card' ) . '</span> - ' . esc_html__( 'YooKassa settings look valid.', 'mp-replace-receipt-gift-card' ) . '</p>';
		} else {
			echo '<p><span class="mp-rrgc-badge mp-rrgc-badge--warn">' . esc_html__( 'WARN', 'mp-replace-receipt-gift-card' ) . '</span> - ' . esc_html__( 'Please check configuration issues:', 'mp-replace-receipt-gift-card' ) . '</p>';
			echo '<ul>';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<h3>' . esc_html__( 'Order inspector (preview)', 'mp-replace-receipt-gift-card' ) . '</h3>';
		echo '<p>' . esc_html__( 'Inspector UI is reserved for later steps (AJAX/local payload preview).', 'mp-replace-receipt-gift-card' ) . '</p>';
	}

	private static function render_tab_robokassa(): void {
		$enabled           = MP_RRGC_Settings::is_rb_enabled();
		$payment_method    = MP_RRGC_Settings::get_rb_payment_method();
		$payment_object    = MP_RRGC_Settings::get_rb_payment_object();
		$name_template     = MP_RRGC_Settings::get_rb_name_template();
		$tax_override      = MP_RRGC_Settings::get_rb_tax_override();
		$apply_to_shipping = MP_RRGC_Settings::rb_apply_to_shipping();
		$only_gift_lines   = MP_RRGC_Settings::rb_only_gift_lines();
		$force_override    = MP_RRGC_Settings::rb_force_override();
		$errors            = MP_RRGC_Settings::validate_rb_rules();

		echo '<h2>' . esc_html__( 'Robokassa', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP_RB );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Robokassa enabled', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_ENABLED ) . '" value="1" ' . checked( $enabled, true, false ) . '> ' . esc_html__( 'Enable Robokassa replacements', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-rb-payment-method">' . esc_html__( 'payment_method', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><select id="mp-rrgc-rb-payment-method" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_PAYMENT_METHOD ) . '">';
		foreach ( MP_RRGC_Settings::allowed_payment_methods_rb() as $method ) {
			echo '<option value="' . esc_attr( $method ) . '" ' . selected( $payment_method, $method, false ) . '>' . esc_html( $method ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-rb-payment-object">' . esc_html__( 'payment_object', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><select id="mp-rrgc-rb-payment-object" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_PAYMENT_OBJECT ) . '">';
		foreach ( MP_RRGC_Settings::allowed_payment_objects_rb() as $object ) {
			echo '<option value="' . esc_attr( $object ) . '" ' . selected( $payment_object, $object, false ) . '>' . esc_html( $object ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-rb-name-template">' . esc_html__( 'Name template', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-rb-name-template" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_NAME_TEMPLATE ) . '" value="' . esc_attr( $name_template ) . '" placeholder="%order_number% Gift card">';
		echo '<p class="description">' . esc_html__( 'Supports placeholders: %order_id%, %order_number%, %line_no%.', 'mp-replace-receipt-gift-card' ) . '</p></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="mp-rrgc-rb-tax-override">' . esc_html__( 'Tax override (optional)', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-rb-tax-override" class="regular-text" type="text" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_TAX_OVERRIDE ) . '" value="' . esc_attr( $tax_override ) . '" placeholder="vat20"></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Apply to shipping', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_APPLY_TO_SHIPPING ) . '" value="1" ' . checked( $apply_to_shipping, true, false ) . '> ' . esc_html__( 'Replace shipping line as well', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Only gift lines', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_ONLY_GIFT_LINES ) . '" value="1" ' . checked( $only_gift_lines, true, false ) . '> ' . esc_html__( 'Apply replacement only to gift-card lines', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Force override', 'mp-replace-receipt-gift-card' ) . '</th>';
		echo '<td><label><input type="checkbox" name="' . esc_attr( MP_RRGC_Settings::OPTION_RB_FORCE_OVERRIDE ) . '" value="1" ' . checked( $force_override, true, false ) . '> ' . esc_html__( 'Override existing values even if they are already set', 'mp-replace-receipt-gift-card' ) . '</label></td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Save settings', 'mp-replace-receipt-gift-card' ) . '</button></p>';
		echo '</form>';

		echo '<hr />';
		echo '<h3>' . esc_html__( 'Preflight', 'mp-replace-receipt-gift-card' ) . '</h3>';
		if ( empty( $errors ) ) {
			echo '<p><span class="mp-rrgc-badge mp-rrgc-badge--pass">' . esc_html__( 'PASS', 'mp-replace-receipt-gift-card' ) . '</span> - ' . esc_html__( 'Robokassa settings look valid.', 'mp-replace-receipt-gift-card' ) . '</p>';
		} else {
			echo '<p><span class="mp-rrgc-badge mp-rrgc-badge--warn">' . esc_html__( 'WARN', 'mp-replace-receipt-gift-card' ) . '</span> - ' . esc_html__( 'Please check configuration issues:', 'mp-replace-receipt-gift-card' ) . '</p>';
			echo '<ul>';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<h3>' . esc_html__( 'Order inspector (preview)', 'mp-replace-receipt-gift-card' ) . '</h3>';
		echo '<p>' . esc_html__( 'Inspector UI is reserved for later steps (AJAX/local payload preview).', 'mp-replace-receipt-gift-card' ) . '</p>';
	}

	private static function render_tab_diagnostics(): void {
		echo '<h2>' . esc_html__( 'Diagnostics', 'mp-replace-receipt-gift-card' ) . '</h2>';
		echo '<p>' . esc_html__( 'Local diagnostics only. No API calls are sent.', 'mp-replace-receipt-gift-card' ) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="mp-rrgc-inspect-product-id">' . esc_html__( 'Inspect product by ID', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-inspect-product-id" type="number" min="1" class="regular-text" placeholder="123"> ';
		echo '<button id="mp-rrgc-inspect-product-btn" type="button" class="button">' . esc_html__( 'Inspect product', 'mp-replace-receipt-gift-card' ) . '</button></td></tr>';

		echo '<tr><th scope="row"><label for="mp-rrgc-inspect-order-yk-id">' . esc_html__( 'Inspect order for YooKassa', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-inspect-order-yk-id" type="number" min="1" class="regular-text" placeholder="1001"> ';
		echo '<button id="mp-rrgc-inspect-order-yk-btn" type="button" class="button">' . esc_html__( 'Inspect YK order', 'mp-replace-receipt-gift-card' ) . '</button></td></tr>';

		echo '<tr><th scope="row"><label for="mp-rrgc-inspect-order-rb-id">' . esc_html__( 'Inspect order for Robokassa', 'mp-replace-receipt-gift-card' ) . '</label></th>';
		echo '<td><input id="mp-rrgc-inspect-order-rb-id" type="number" min="1" class="regular-text" placeholder="1001"> ';
		echo '<button id="mp-rrgc-inspect-order-rb-btn" type="button" class="button">' . esc_html__( 'Inspect RB order', 'mp-replace-receipt-gift-card' ) . '</button></td></tr>';
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Result', 'mp-replace-receipt-gift-card' ) . '</h3>';
		echo '<pre id="mp-rrgc-diagnostics-output"></pre>';
	}

	public static function ajax_inspect_product(): void {
		self::assert_ajax_permissions();
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;

		if ( $product_id < 1 ) {
			wp_send_json_error( array( 'error' => 'invalid_product_id' ), 400 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			wp_send_json_success(
				array(
					'product_found' => false,
					'is_gift'       => false,
					'reasons'       => array( 'product_not_found' ),
				)
			);
		}

		$is_gift = MP_RRGC_Gift_Detector::is_gift_product( $product );
		$reasons = MP_RRGC_Gift_Detector::get_detection_reasons( $product );

		wp_send_json_success(
			array(
				'product_found' => true,
				'product_id'    => $product_id,
				'is_gift'       => (bool) $is_gift,
				'reasons'       => array_values( array_map( 'strval', (array) $reasons ) ),
			)
		);
	}

	public static function ajax_inspect_order_yk(): void {
		self::assert_ajax_permissions();
		self::ajax_inspect_order_common( 'yookassa' );
	}

	public static function ajax_inspect_order_rb(): void {
		self::assert_ajax_permissions();
		self::ajax_inspect_order_common( 'robokassa' );
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
		register_setting( self::GROUP_COMMON, MP_RRGC_Settings::OPTION_HOOK_PRIORITY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_hook_priority' ) ) );

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

	/**
	 * @param mixed $value
	 * @return int
	 */
	public static function sanitize_hook_priority( $value ): int {
		$priority = absint( $value );
		if ( $priority < 1 || $priority > 9999 ) {
			add_settings_error(
				self::GROUP_COMMON,
				'mp_rrgc_invalid_hook_priority',
				esc_html__( 'Invalid hook priority. Fallback to 999.', 'mp-replace-receipt-gift-card' )
			);
			return 999;
		}

		return $priority;
	}

	/**
	 * @return array<string,string>
	 */
	private static function get_available_gateway_options(): array {
		$result = array();

		if ( function_exists( 'WC' ) && WC() && WC()->payment_gateways() ) {
			$gateways = WC()->payment_gateways()->payment_gateways();
			if ( is_array( $gateways ) ) {
				foreach ( $gateways as $gateway ) {
					if ( ! is_object( $gateway ) || empty( $gateway->id ) ) {
						continue;
					}
					$id    = sanitize_key( (string) $gateway->id );
					$title = isset( $gateway->method_title ) ? (string) $gateway->method_title : $id;
					$result[ $id ] = $title;
				}
			}
		}

		// Helpful aliases used by this plugin even if gateways are not loaded in this request.
		$result['yookassa']         = isset( $result['yookassa'] ) ? $result['yookassa'] : 'YooKassa';
		$result['robokassa']        = isset( $result['robokassa'] ) ? $result['robokassa'] : 'Robokassa';
		$result['robokassa_payment']= isset( $result['robokassa_payment'] ) ? $result['robokassa_payment'] : 'Robokassa Payment';

		ksort( $result );
		return $result;
	}

	private static function render_compatibility_notices(): void {
		$active = self::get_active_plugin_basenames();
		$conflicts = array();

		foreach ( $active as $basename ) {
			if ( false !== strpos( $basename, 'mp-yookassa-receipt2' ) ) {
				$conflicts[] = 'mp-yookassa-receipt2';
			}
			if ( false !== strpos( $basename, 'mp_robokassa_receipt2' ) ) {
				$conflicts[] = 'mp_robokassa_receipt2';
			}
			if ( false !== strpos( $basename, 'mp-marked-products-receipt' ) ) {
				$conflicts[] = 'mp-marked-products-receipt';
			}
		}

		$conflicts = array_values( array_unique( $conflicts ) );
		if ( empty( $conflicts ) ) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p>';
		echo esc_html__( 'Compatibility notice: other receipt-related plugins are active and may modify the same payload fields.', 'mp-replace-receipt-gift-card' );
		echo ' ';
		echo esc_html( implode( ', ', $conflicts ) );
		echo '. ';
		echo esc_html__( 'If needed, adjust "Hook priority" in Common settings.', 'mp-replace-receipt-gift-card' );
		echo '</p></div>';
	}

	/**
	 * @return string[]
	 */
	private static function get_active_plugin_basenames(): array {
		$active = get_option( 'active_plugins', array() );
		if ( ! is_array( $active ) ) {
			$active = array();
		}

		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $network ) ) {
				$active = array_merge( $active, array_keys( $network ) );
			}
		}

		$active = array_map(
			static function ( $v ) {
				return (string) $v;
			},
			$active
		);

		return array_values( array_unique( $active ) );
	}

	private static function assert_ajax_permissions(): void {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			wp_send_json_error( array( 'error' => 'method_not_allowed' ), 405 );
		}
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => 'forbidden' ), 403 );
		}
		nocache_headers();
	}

	private static function ajax_inspect_order_common( string $provider ): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( $order_id < 1 ) {
			wp_send_json_error( array( 'error' => 'invalid_order_id' ), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_success(
				array(
					'order_found' => false,
					'order_id'    => $order_id,
				)
			);
		}

		$split = MP_RRGC_Gift_Detector::split_order_items( $order );
		$preview_items = array();
		$line_items = $order->get_items( 'line_item' );
		$gift_map = array_fill_keys( array_keys( $split['gift'] ), true );

		foreach ( $line_items as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$is_gift = isset( $gift_map[ (int) $item_id ] );
			$product = $item->get_product();
			$preview_items[] = array(
				'item_id'          => (int) $item_id,
				'product_id'       => $product instanceof WC_Product ? (int) $product->get_id() : 0,
				'qty'              => (float) $item->get_quantity(),
				'total'            => (float) $item->get_total(),
				'is_gift'          => $is_gift,
				'will_be_replaced' => $provider === 'yookassa'
					? ( MP_RRGC_Settings::yk_only_gift_lines() ? $is_gift : true )
					: ( MP_RRGC_Settings::rb_only_gift_lines() ? $is_gift : true ),
			);
		}

		$was_truncated = false;
		if ( count( $preview_items ) > self::DIAGNOSTICS_MAX_ITEMS ) {
			$preview_items = array_slice( $preview_items, 0, self::DIAGNOSTICS_MAX_ITEMS );
			$was_truncated = true;
		}

		$response = array(
			'order_found'   => true,
			'order_id'      => $order_id,
			'provider'      => $provider,
			'detection'     => array(
				'mode'          => MP_RRGC_Settings::get_detection_mode(),
				'gift_count'    => count( $split['gift'] ),
				'regular_count' => count( $split['regular'] ),
			),
			'should_process'=> MP_RRGC_Orchestrator::should_process_order(
				$order,
				$provider,
				$provider === 'yookassa' ? array( 'yookassa' ) : array( 'robokassa' )
			),
			'preview'       => array(
				'items'          => $preview_items,
				'truncated'      => $was_truncated,
				'max_items'      => self::DIAGNOSTICS_MAX_ITEMS,
			),
		);

		if ( 'yookassa' === $provider ) {
			$response['replacement'] = array(
				'payment_mode'      => MP_RRGC_Settings::get_yk_payment_mode(),
				'payment_subject'   => MP_RRGC_Settings::get_yk_payment_subject(),
				'description_tmpl'  => MP_RRGC_Settings::get_yk_description_template(),
				'apply_shipping'    => MP_RRGC_Settings::yk_apply_to_shipping(),
				'only_gift_lines'   => MP_RRGC_Settings::yk_only_gift_lines(),
				'force_override'    => MP_RRGC_Settings::yk_force_override(),
			);
		} else {
			$response['replacement'] = array(
				'payment_method'    => MP_RRGC_Settings::get_rb_payment_method(),
				'payment_object'    => MP_RRGC_Settings::get_rb_payment_object(),
				'name_tmpl'         => MP_RRGC_Settings::get_rb_name_template(),
				'tax_override'      => MP_RRGC_Settings::get_rb_tax_override(),
				'apply_shipping'    => MP_RRGC_Settings::rb_apply_to_shipping(),
				'only_gift_lines'   => MP_RRGC_Settings::rb_only_gift_lines(),
				'force_override'    => MP_RRGC_Settings::rb_force_override(),
			);
		}

		wp_send_json_success( $response );
	}
}


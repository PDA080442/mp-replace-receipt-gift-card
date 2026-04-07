<?php
/**
 * Settings & options facade.
 *
 * Step 2 implements only option keys, defaults, and normalization helpers.
 * Admin UI and sanitizers will be implemented in later steps.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_Settings {
	public const OPTION_ENABLED = 'mp_rrgc_common_enabled';
	public const OPTION_DEBUG   = 'mp_rrgc_debug';

	public const OPTION_YK_ENABLED = 'mp_rrgc_yk_enabled';
	public const OPTION_RB_ENABLED = 'mp_rrgc_rb_enabled';

	// YooKassa override options (gift card).
	public const OPTION_YK_PAYMENT_MODE          = 'mp_rrgc_yk_payment_mode';
	public const OPTION_YK_PAYMENT_SUBJECT       = 'mp_rrgc_yk_payment_subject';
	public const OPTION_YK_DESCRIPTION_TEMPLATE  = 'mp_rrgc_yk_description_template';
	public const OPTION_YK_VAT_CODE_OVERRIDE     = 'mp_rrgc_yk_vat_code_override';
	public const OPTION_YK_APPLY_TO_SHIPPING     = 'mp_rrgc_yk_apply_to_shipping';
	public const OPTION_YK_ONLY_GIFT_LINES       = 'mp_rrgc_yk_only_gift_lines';
	public const OPTION_YK_FORCE_OVERRIDE        = 'mp_rrgc_yk_force_override';

	// Robokassa override options (gift card).
	public const OPTION_RB_PAYMENT_METHOD     = 'mp_rrgc_rb_payment_method';
	public const OPTION_RB_PAYMENT_OBJECT     = 'mp_rrgc_rb_payment_object';
	public const OPTION_RB_NAME_TEMPLATE      = 'mp_rrgc_rb_name_template';
	public const OPTION_RB_TAX_OVERRIDE       = 'mp_rrgc_rb_tax_override';
	public const OPTION_RB_APPLY_TO_SHIPPING  = 'mp_rrgc_rb_apply_to_shipping';
	public const OPTION_RB_ONLY_GIFT_LINES    = 'mp_rrgc_rb_only_gift_lines';
	public const OPTION_RB_FORCE_OVERRIDE     = 'mp_rrgc_rb_force_override';

	public const OPTION_DETECTION_MODE     = 'mp_rrgc_detection_mode';
	public const OPTION_GIFT_PRODUCT_IDS   = 'mp_rrgc_gift_product_ids';
	public const OPTION_GIFT_CATEGORY_IDS  = 'mp_rrgc_gift_category_ids';
	public const OPTION_GIFT_META_KEY      = 'mp_rrgc_gift_meta_key';
	public const OPTION_GIFT_META_VALUE    = 'mp_rrgc_gift_meta_value';
	public const OPTION_GIFT_PRODUCT_TYPE  = 'mp_rrgc_gift_product_type';
	public const OPTION_ONLY_GIFT_ONLY     = 'mp_rrgc_only_if_order_is_gift_only';
	public const OPTION_ALLOW_MIXED_CART   = 'mp_rrgc_allow_mixed_cart';
	public const OPTION_ALLOWED_GATEWAYS   = 'mp_rrgc_gateways';

	/**
	 * @return string[]
	 */
	public static function allowed_detection_modes(): array {
		return array(
			'product_ids',
			'category',
			'meta',
			'product_type',
			'filter_only',
		);
	}

	public static function is_enabled(): bool {
		return self::truthy_option( self::OPTION_ENABLED, false );
	}

	public static function is_debug_enabled(): bool {
		return self::truthy_option( self::OPTION_DEBUG, false );
	}

	public static function is_yk_enabled(): bool {
		return self::truthy_option( self::OPTION_YK_ENABLED, false );
	}

	public static function is_rb_enabled(): bool {
		return self::truthy_option( self::OPTION_RB_ENABLED, false );
	}

	/**
	 * @return string[]
	 */
	public static function allowed_payment_modes_yk(): array {
		// Matches YooKassa Receipt API common set (and is aligned with existing receipt2 plugins).
		return array(
			'advance',
			'credit',
			'credit_payment',
			'full_payment',
			'full_prepayment',
			'partial_payment',
			'partial_prepayment',
		);
	}

	/**
	 * @return string[]
	 */
	public static function allowed_payment_subjects_yk(): array {
		// Matches YooKassa Receipt API common set (and is aligned with existing receipt2 plugins).
		return array(
			'agent_commission',
			'another',
			'commodity',
			'composite',
			'excise',
			'gambling_bet',
			'gambling_prize',
			'intellectual_activity',
			'job',
			'lottery',
			'lottery_prize',
			'payment',
			'service',
		);
	}

	public static function get_yk_payment_mode(): string {
		$value = (string) get_option( self::OPTION_YK_PAYMENT_MODE, 'advance' );
		$value = sanitize_key( $value );

		if ( ! in_array( $value, self::allowed_payment_modes_yk(), true ) ) {
			return 'advance';
		}

		return $value;
	}

	public static function get_yk_payment_subject(): string {
		$value = (string) get_option( self::OPTION_YK_PAYMENT_SUBJECT, 'payment' );
		$value = sanitize_key( $value );

		if ( ! in_array( $value, self::allowed_payment_subjects_yk(), true ) ) {
			return 'payment';
		}

		return $value;
	}

	public static function get_yk_description_template(): string {
		$template = (string) get_option( self::OPTION_YK_DESCRIPTION_TEMPLATE, '' );
		$template = sanitize_text_field( $template );

		// Hard cap to keep payload reasonable; exact limit will be enforced by gateway later.
		if ( strlen( $template ) > 512 ) {
			$template = substr( $template, 0, 512 );
		}

		return (string) $template;
	}

	public static function get_yk_vat_code_override(): string {
		$vat = (string) get_option( self::OPTION_YK_VAT_CODE_OVERRIDE, '' );
		$vat = sanitize_key( $vat );
		return (string) $vat;
	}

	public static function yk_apply_to_shipping(): bool {
		return self::truthy_option( self::OPTION_YK_APPLY_TO_SHIPPING, false );
	}

	public static function yk_only_gift_lines(): bool {
		return self::truthy_option( self::OPTION_YK_ONLY_GIFT_LINES, true );
	}

	public static function yk_force_override(): bool {
		return self::truthy_option( self::OPTION_YK_FORCE_OVERRIDE, false );
	}

	/**
	 * Validate YooKassa-related config without any network calls.
	 *
	 * @return string[] list of error messages (not escaped).
	 */
	public static function validate_yk_rules(): array {
		$errors = array();

		$mode = self::get_yk_payment_mode();
		if ( '' === $mode ) {
			$errors[] = 'YooKassa: payment_mode is empty.';
		}

		$subject = self::get_yk_payment_subject();
		if ( '' === $subject ) {
			$errors[] = 'YooKassa: payment_subject is empty.';
		}

		// If user explicitly set invalid values, getters already fallback to defaults.
		// Still, for diagnostics we can warn if stored values are non-empty but invalid.
		$raw_mode = sanitize_key( (string) get_option( self::OPTION_YK_PAYMENT_MODE, '' ) );
		if ( '' !== $raw_mode && ! in_array( $raw_mode, self::allowed_payment_modes_yk(), true ) ) {
			$errors[] = 'YooKassa: stored payment_mode value is not allowed.';
		}

		$raw_subject = sanitize_key( (string) get_option( self::OPTION_YK_PAYMENT_SUBJECT, '' ) );
		if ( '' !== $raw_subject && ! in_array( $raw_subject, self::allowed_payment_subjects_yk(), true ) ) {
			$errors[] = 'YooKassa: stored payment_subject value is not allowed.';
		}

		return $errors;
	}

	/**
	 * @return string[]
	 */
	public static function allowed_payment_methods_rb(): array {
		// Mirrors the same fiscal set used in existing receipt2 integrations.
		return array(
			'advance',
			'credit',
			'credit_payment',
			'full_payment',
			'full_prepayment',
			'partial_payment',
			'partial_prepayment',
		);
	}

	/**
	 * @return string[]
	 */
	public static function allowed_payment_objects_rb(): array {
		// Mirrors common fiscal object values from current integrations.
		return array(
			'agent_commission',
			'another',
			'commodity',
			'composite',
			'excise',
			'gambling_bet',
			'gambling_prize',
			'intellectual_activity',
			'job',
			'lottery',
			'lottery_prize',
			'payment',
			'service',
		);
	}

	public static function get_rb_payment_method(): string {
		$value = (string) get_option( self::OPTION_RB_PAYMENT_METHOD, 'advance' );
		$value = sanitize_key( $value );

		if ( ! in_array( $value, self::allowed_payment_methods_rb(), true ) ) {
			return 'advance';
		}

		return $value;
	}

	public static function get_rb_payment_object(): string {
		$value = (string) get_option( self::OPTION_RB_PAYMENT_OBJECT, 'payment' );
		$value = sanitize_key( $value );

		if ( ! in_array( $value, self::allowed_payment_objects_rb(), true ) ) {
			return 'payment';
		}

		return $value;
	}

	public static function get_rb_name_template(): string {
		$template = (string) get_option( self::OPTION_RB_NAME_TEMPLATE, '' );
		$template = sanitize_text_field( $template );

		if ( strlen( $template ) > 512 ) {
			$template = substr( $template, 0, 512 );
		}

		return (string) $template;
	}

	public static function get_rb_tax_override(): string {
		$tax = (string) get_option( self::OPTION_RB_TAX_OVERRIDE, '' );
		$tax = sanitize_key( $tax );
		return (string) $tax;
	}

	public static function rb_apply_to_shipping(): bool {
		return self::truthy_option( self::OPTION_RB_APPLY_TO_SHIPPING, false );
	}

	public static function rb_only_gift_lines(): bool {
		return self::truthy_option( self::OPTION_RB_ONLY_GIFT_LINES, true );
	}

	public static function rb_force_override(): bool {
		return self::truthy_option( self::OPTION_RB_FORCE_OVERRIDE, false );
	}

	/**
	 * Validate Robokassa-related config without network calls.
	 *
	 * @return string[] list of error messages (not escaped).
	 */
	public static function validate_rb_rules(): array {
		$errors = array();

		$method = self::get_rb_payment_method();
		if ( '' === $method ) {
			$errors[] = 'Robokassa: payment_method is empty.';
		}

		$object = self::get_rb_payment_object();
		if ( '' === $object ) {
			$errors[] = 'Robokassa: payment_object is empty.';
		}

		$raw_method = sanitize_key( (string) get_option( self::OPTION_RB_PAYMENT_METHOD, '' ) );
		if ( '' !== $raw_method && ! in_array( $raw_method, self::allowed_payment_methods_rb(), true ) ) {
			$errors[] = 'Robokassa: stored payment_method value is not allowed.';
		}

		$raw_object = sanitize_key( (string) get_option( self::OPTION_RB_PAYMENT_OBJECT, '' ) );
		if ( '' !== $raw_object && ! in_array( $raw_object, self::allowed_payment_objects_rb(), true ) ) {
			$errors[] = 'Robokassa: stored payment_object value is not allowed.';
		}

		return $errors;
	}

	public static function get_detection_mode(): string {
		$mode = (string) get_option( self::OPTION_DETECTION_MODE, 'product_ids' );
		$mode = sanitize_key( $mode );

		if ( ! in_array( $mode, self::allowed_detection_modes(), true ) ) {
			return 'product_ids';
		}

		return $mode;
	}

	/**
	 * @return int[]
	 */
	public static function get_gift_product_ids(): array {
		$value = get_option( self::OPTION_GIFT_PRODUCT_IDS, '' );
		return self::normalize_int_list( $value );
	}

	/**
	 * @return int[]
	 */
	public static function get_gift_category_ids(): array {
		$value = get_option( self::OPTION_GIFT_CATEGORY_IDS, '' );
		return self::normalize_int_list( $value );
	}

	public static function get_gift_meta_key(): string {
		$key = (string) get_option( self::OPTION_GIFT_META_KEY, '' );
		$key = sanitize_text_field( $key );
		return (string) $key;
	}

	public static function get_gift_meta_value(): string {
		$value = (string) get_option( self::OPTION_GIFT_META_VALUE, '' );
		$value = sanitize_text_field( $value );
		return (string) $value;
	}

	public static function get_gift_product_type(): string {
		$type = (string) get_option( self::OPTION_GIFT_PRODUCT_TYPE, '' );
		$type = sanitize_key( $type );
		return (string) $type;
	}

	public static function only_if_order_is_gift_only(): bool {
		return self::truthy_option( self::OPTION_ONLY_GIFT_ONLY, false );
	}

	public static function allow_mixed_cart(): bool {
		return self::truthy_option( self::OPTION_ALLOW_MIXED_CART, true );
	}

	/**
	 * Allowed gateways list. Empty => allow any gateway.
	 *
	 * @return string[]
	 */
	public static function get_allowed_gateways(): array {
		$value = get_option( self::OPTION_ALLOWED_GATEWAYS, array() );

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

		$list = array_filter(
			$list,
			static function ( $v ) {
				return '' !== $v;
			}
		);

		return array_values( array_unique( $list ) );
	}

	/**
	 * @param mixed $value
	 * @return int[]
	 */
	private static function normalize_int_list( $value ): array {
		$items = array();

		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_string( $value ) ) {
			$items = preg_split( '/\s*,\s*/', $value ) ?: array();
		} elseif ( is_int( $value ) ) {
			$items = array( $value );
		}

		$items = array_map(
			static function ( $v ) {
				return absint( $v );
			},
			$items
		);

		$items = array_filter(
			$items,
			static function ( $v ) {
				return $v > 0;
			}
		);

		return array_values( array_unique( $items ) );
	}

	private static function truthy_option( string $key, bool $default ): bool {
		$raw = get_option( $key, $default ? '1' : '0' );

		if ( is_bool( $raw ) ) {
			return $raw;
		}

		$raw = strtolower( (string) $raw );

		return in_array( $raw, array( '1', 'yes', 'true', 'on' ), true );
	}
}


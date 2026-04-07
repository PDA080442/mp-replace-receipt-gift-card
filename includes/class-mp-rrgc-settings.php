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


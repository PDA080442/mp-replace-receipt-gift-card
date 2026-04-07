<?php
/**
 * Gift card product detector.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_Gift_Detector {
	/** @var array<string,bool> */
	private static $product_check_cache = array();

	/** @var array<string,array> */
	private static $reason_cache = array();

	/**
	 * Detect whether product is considered a gift card by current settings.
	 */
	public static function is_gift_product( WC_Product $product ): bool {
		$product_id = $product->get_id();
		if ( $product_id <= 0 ) {
			return false;
		}

		$mode = MP_RRGC_Settings::get_detection_mode();
		$key  = $mode . ':' . (string) $product_id;

		if ( array_key_exists( $key, self::$product_check_cache ) ) {
			return self::$product_check_cache[ $key ];
		}

		$result  = false;
		$reasons = array();

		switch ( $mode ) {
			case 'product_ids':
				$result = self::detect_by_product_ids( $product, $reasons );
				break;
			case 'category':
				$result = self::detect_by_category( $product, $reasons );
				break;
			case 'meta':
				$result = self::detect_by_meta( $product, $reasons );
				break;
			case 'product_type':
				$result = self::detect_by_product_type( $product, $reasons );
				break;
			case 'filter_only':
				$reasons[] = 'filter_only_mode_default_false';
				$result    = false;
				break;
			default:
				$reasons[] = 'unsupported_detection_mode:' . $mode;
				$result    = false;
				break;
		}

		/**
		 * Allow external override of gift-product detection.
		 *
		 * @param bool       $result  Current detection result.
		 * @param WC_Product $product Product object.
		 */
		$result = (bool) apply_filters( 'mp_rrgc_is_gift_product', $result, $product );

		/**
		 * Allow external normalization of detection reasons.
		 *
		 * @param string[]   $reasons Current reasons.
		 * @param WC_Product $product Product object.
		 * @param bool       $result  Final detection result.
		 */
		$reasons = (array) apply_filters( 'mp_rrgc_gift_detection_reasons', $reasons, $product, $result );

		self::$product_check_cache[ $key ] = $result;
		self::$reason_cache[ $key ]        = $reasons;

		return $result;
	}

	/**
	 * Returns product detection reasons from cache.
	 *
	 * @return string[]
	 */
	public static function get_detection_reasons( WC_Product $product ): array {
		$key = MP_RRGC_Settings::get_detection_mode() . ':' . (string) $product->get_id();
		if ( ! isset( self::$reason_cache[ $key ] ) ) {
			self::is_gift_product( $product );
		}

		return isset( self::$reason_cache[ $key ] ) ? self::$reason_cache[ $key ] : array();
	}

	/**
	 * @return bool true if order contains at least one gift-card line item.
	 */
	public static function order_has_gift_items( WC_Order $order ): bool {
		$items = $order->get_items( 'line_item' );
		foreach ( $items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( self::is_gift_product( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split order line items by gift/non-gift criteria.
	 *
	 * @return array{gift:array,regular:array}
	 */
	public static function split_order_items( WC_Order $order ): array {
		$gift    = array();
		$regular = array();

		$items = $order->get_items( 'line_item' );
		foreach ( $items as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				$regular[ (int) $item_id ] = $item;
				continue;
			}

			if ( self::is_gift_product( $product ) ) {
				$gift[ (int) $item_id ] = $item;
			} else {
				$regular[ (int) $item_id ] = $item;
			}
		}

		return array(
			'gift'    => $gift,
			'regular' => $regular,
		);
	}

	/**
	 * @param string[] $reasons
	 */
	private static function detect_by_product_ids( WC_Product $product, array &$reasons ): bool {
		$allowed_ids = MP_RRGC_Settings::get_gift_product_ids();
		if ( empty( $allowed_ids ) ) {
			$reasons[] = 'no_product_ids_configured';
			return false;
		}

		$product_ids_to_match = self::get_product_and_parent_ids( $product );

		$matched = (bool) array_intersect( $product_ids_to_match, $allowed_ids );
		if ( $matched ) {
			$reasons[] = 'matched_product_ids';
		}

		return $matched;
	}

	/**
	 * @param string[] $reasons
	 */
	private static function detect_by_category( WC_Product $product, array &$reasons ): bool {
		$category_ids = MP_RRGC_Settings::get_gift_category_ids();
		if ( empty( $category_ids ) ) {
			$reasons[] = 'no_category_ids_configured';
			return false;
		}

		$product_ids = self::get_product_and_parent_ids( $product );

		foreach ( $product_ids as $product_id ) {
			$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$terms = array_map( 'absint', $terms );
			$terms = array_filter( $terms );

			if ( ! empty( array_intersect( $category_ids, $terms ) ) ) {
				$reasons[] = 'matched_category';
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $reasons
	 */
	private static function detect_by_meta( WC_Product $product, array &$reasons ): bool {
		$meta_key = MP_RRGC_Settings::get_gift_meta_key();
		if ( '' === $meta_key ) {
			$reasons[] = 'empty_meta_key';
			return false;
		}

		$expected_value = MP_RRGC_Settings::get_gift_meta_value();
		$product_ids    = self::get_product_and_parent_ids( $product );

		foreach ( $product_ids as $product_id ) {
			$meta_value = get_post_meta( $product_id, $meta_key, true );
			if ( is_array( $meta_value ) ) {
				$meta_value = wp_json_encode( $meta_value );
			}

			$meta_value = is_scalar( $meta_value ) ? trim( (string) $meta_value ) : '';
			if ( '' === $meta_value ) {
				continue;
			}

			if ( '' === $expected_value || $expected_value === $meta_value ) {
				$reasons[] = '' === $expected_value ? 'meta_key_non_empty' : 'meta_key_value_match';
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $reasons
	 */
	private static function detect_by_product_type( WC_Product $product, array &$reasons ): bool {
		$configured_type = MP_RRGC_Settings::get_gift_product_type();
		if ( '' === $configured_type ) {
			$reasons[] = 'empty_product_type';
			return false;
		}

		$product_type = sanitize_key( $product->get_type() );
		if ( $product_type === $configured_type ) {
			$reasons[] = 'matched_product_type';
			return true;
		}

		$parent_id = $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent instanceof WC_Product ) {
				$parent_type = sanitize_key( $parent->get_type() );
				if ( $parent_type === $configured_type ) {
					$reasons[] = 'matched_parent_product_type';
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return int[]
	 */
	private static function get_product_and_parent_ids( WC_Product $product ): array {
		$ids = array( $product->get_id() );

		$parent_id = $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$ids[] = $parent_id;
		}

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}
}


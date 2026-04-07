<?php
/**
 * Orchestrator facade.
 *
 * In step 0 this class only exists as a single entry point for registering hooks.
 * Real detection and payload replacement will be implemented in later steps.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_Orchestrator {
	/** @var bool */
	private static $hooks_inited = false;

	public static function init_hooks(): void {
		if ( self::$hooks_inited ) {
			return;
		}

		self::$hooks_inited = true;

		if ( MP_RRGC_Settings::is_yk_enabled() && class_exists( 'MP_RRGC_YK_Replacer' ) ) {
			MP_RRGC_YK_Replacer::register_hooks();
		}
		if ( MP_RRGC_Settings::is_rb_enabled() && class_exists( 'MP_RRGC_RB_Replacer' ) ) {
			MP_RRGC_RB_Replacer::register_hooks();
		}
	}

	/**
	 * Central decision point for whether an order should be processed.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $gateway_name Logical gateway name ('yookassa'|'robokassa' etc.).
	 * @param string[] $gateway_aliases Optional list of aliases to match mp_rrgc_gateways option.
	 */
	public static function should_process_order( WC_Order $order, string $gateway_name, array $gateway_aliases = array() ): bool {
		$allowed_gateways = MP_RRGC_Settings::get_allowed_gateways();
		$gateway_names    = self::normalize_gateway_names( $gateway_name, $gateway_aliases );

		if ( ! self::is_gateway_allowed( $allowed_gateways, $gateway_names ) ) {
			$decision = false;
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'            => 'gateway_not_allowed',
				'allowed_gateways'  => $allowed_gateways,
				'gateway_candidates'=> $gateway_names,
			) );
		}

		$split = MP_RRGC_Gift_Detector::split_order_items( $order );
		$gift_count    = count( $split['gift'] );
		$regular_count = count( $split['regular'] );

		if ( $gift_count < 1 ) {
			$decision = false;
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'         => 'no_gift_items',
				'gift_count'     => $gift_count,
				'regular_count'  => $regular_count,
			) );
		}

		if ( MP_RRGC_Settings::only_if_order_is_gift_only() && $regular_count > 0 ) {
			$decision = false;
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'         => 'gift_only_required',
				'gift_count'     => $gift_count,
				'regular_count'  => $regular_count,
			) );
		}

		if ( ! MP_RRGC_Settings::allow_mixed_cart() && $regular_count > 0 ) {
			$decision = false;
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'         => 'mixed_cart_disabled',
				'gift_count'     => $gift_count,
				'regular_count'  => $regular_count,
			) );
		}

		$decision = true;
		return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
			'reason'         => 'ok',
			'gift_count'     => $gift_count,
			'regular_count'  => $regular_count,
		) );
	}

	/**
	 * @param string[] $allowed
	 * @param string[] $candidates
	 */
	private static function is_gateway_allowed( array $allowed, array $candidates ): bool {
		if ( empty( $allowed ) ) {
			return true;
		}

		return ! empty( array_intersect( $allowed, $candidates ) );
	}

	/**
	 * @param string   $gateway_name
	 * @param string[] $aliases
	 * @return string[]
	 */
	private static function normalize_gateway_names( string $gateway_name, array $aliases ): array {
		$names = array_merge( array( $gateway_name ), $aliases );
		$names = array_map(
			static function ( $value ) {
				return sanitize_key( (string) $value );
			},
			$names
		);
		$names = array_filter(
			$names,
			static function ( $value ) {
				return '' !== $value;
			}
		);

		return array_values( array_unique( $names ) );
	}
}


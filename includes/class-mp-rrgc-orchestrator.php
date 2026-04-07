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
		MP_RRGC_Logger::log( 'INFO', 0, 'orchestrator_init_hooks' );

		if ( MP_RRGC_Settings::is_yk_enabled() && class_exists( 'MP_RRGC_YK_Replacer' ) ) {
			MP_RRGC_YK_Replacer::register_hooks();
			MP_RRGC_Logger::log( 'DEBUG', 0, 'orchestrator_register_yk_hooks' );
		}
		if ( MP_RRGC_Settings::is_rb_enabled() && class_exists( 'MP_RRGC_RB_Replacer' ) ) {
			MP_RRGC_RB_Replacer::register_hooks();
			MP_RRGC_Logger::log( 'DEBUG', 0, 'orchestrator_register_rb_hooks' );
		}
	}

	/**
	 * Central decision point for whether an order should be processed.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $gateway_name Logical gateway name ('yookassa'|'robokassa' etc.).
	 * @param string[] $gateway_aliases Reserved for backward compatibility (currently unused).
	 */
	public static function should_process_order( WC_Order $order, string $gateway_name, array $gateway_aliases = array() ): bool {
		$split = MP_RRGC_Gift_Detector::split_order_items( $order );
		$gift_count    = count( $split['gift'] );
		$regular_count = count( $split['regular'] );

		if ( $gift_count < 1 ) {
			$decision = false;
			MP_RRGC_Logger::log( 'DEBUG', (int) $order->get_id(), 'should_process_skip_no_gift', array() );
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'         => 'no_gift_items',
				'gift_count'     => $gift_count,
				'regular_count'  => $regular_count,
			) );
		}

		if ( MP_RRGC_Settings::only_if_order_is_gift_only() && $regular_count > 0 ) {
			$decision = false;
			MP_RRGC_Logger::log( 'DEBUG', (int) $order->get_id(), 'should_process_skip_requires_gift_only', array(
				'regular_count' => $regular_count,
			) );
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'         => 'gift_only_required',
				'gift_count'     => $gift_count,
				'regular_count'  => $regular_count,
			) );
		}

		if ( ! MP_RRGC_Settings::allow_mixed_cart() && $regular_count > 0 ) {
			$decision = false;
			MP_RRGC_Logger::log( 'DEBUG', (int) $order->get_id(), 'should_process_skip_mixed_disabled', array(
				'regular_count' => $regular_count,
			) );
			return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
				'reason'         => 'mixed_cart_disabled',
				'gift_count'     => $gift_count,
				'regular_count'  => $regular_count,
			) );
		}

		$decision = true;
		MP_RRGC_Logger::log( 'DEBUG', (int) $order->get_id(), 'should_process_ok', array(
			'gateway'       => $gateway_name,
			'gift_count'    => $gift_count,
			'regular_count' => $regular_count,
		) );
		return (bool) apply_filters( 'mp_rrgc_should_process_order', $decision, $order, $gateway_name, array(
			'reason'         => 'ok',
			'gift_count'     => $gift_count,
			'regular_count'  => $regular_count,
		) );
	}
}


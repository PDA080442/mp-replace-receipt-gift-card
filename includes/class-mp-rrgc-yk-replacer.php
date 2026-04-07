<?php
/**
 * YooKassa payload replacer.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_YK_Replacer {
	/** @var array<string,bool> */
	private static $seen_requests = array();

	public static function register_hooks(): void {
		$priority = MP_RRGC_Settings::get_hook_priority();
		add_filter(
			'woocommerce_yookassa_create_payment_request',
			array( __CLASS__, 'maybe_replace_receipt_data' ),
			$priority,
			1
		);
	}

	/**
	 * @param mixed $payment_request
	 * @return mixed
	 */
	public static function maybe_replace_receipt_data( $payment_request ) {
		$request_hash = is_object( $payment_request ) ? spl_object_hash( $payment_request ) : '';
		if ( '' !== $request_hash && isset( self::$seen_requests[ $request_hash ] ) ) {
			return $payment_request;
		}

		if ( ! MP_RRGC_Settings::is_enabled() || ! MP_RRGC_Settings::is_yk_enabled() ) {
			return $payment_request;
		}

		if ( ! is_object( $payment_request ) || ! method_exists( $payment_request, 'getReceipt' ) ) {
			return $payment_request;
		}

		$receipt = $payment_request->getReceipt();
		if ( ! is_object( $receipt ) || ! method_exists( $receipt, 'getItems' ) ) {
			return $payment_request;
		}

		$order = self::resolve_order_from_context();
		if ( ! $order instanceof WC_Order ) {
			MP_RRGC_Logger::log( 'DEBUG', 0, 'yk_skip_order_not_resolved' );
			return $payment_request;
		}

		if ( ! MP_RRGC_Orchestrator::should_process_order(
			$order,
			'yookassa',
			array( 'yookassa', 'yookassa_widget', 'yookassa_b2b_sberbank', 'yookassa_epl' )
		) ) {
			return $payment_request;
		}

		$split = MP_RRGC_Gift_Detector::split_order_items( $order );
		if ( empty( $split['gift'] ) ) {
			MP_RRGC_Logger::log( 'DEBUG', (int) $order->get_id(), 'yk_skip_no_gift_lines' );
			return $payment_request;
		}

		$items = $receipt->getItems();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return $payment_request;
		}

		$line_items        = $order->get_items( 'line_item' );
		$line_item_ids     = array_keys( $line_items );
		$gift_item_ids_map = array_fill_keys( array_keys( $split['gift'] ), true );

		$mode              = MP_RRGC_Settings::get_yk_payment_mode();
		$subject           = MP_RRGC_Settings::get_yk_payment_subject();
		$template          = MP_RRGC_Settings::get_yk_description_template();
		$only_gift_lines   = MP_RRGC_Settings::yk_only_gift_lines();
		$force_override    = MP_RRGC_Settings::yk_force_override();
		$apply_to_shipping = MP_RRGC_Settings::yk_apply_to_shipping();

		$changed = 0;

		try {
			foreach ( $items as $index => $receipt_item ) {
			if ( ! is_object( $receipt_item ) ) {
				continue;
			}

			$is_shipping = method_exists( $receipt_item, 'isShipping' ) ? (bool) $receipt_item->isShipping() : false;

			if ( $is_shipping ) {
				if ( ! $apply_to_shipping ) {
					continue;
				}
				$should_change = true;
			} else {
				$item_id = isset( $line_item_ids[ $index ] ) ? (int) $line_item_ids[ $index ] : 0;
				$is_gift = $item_id > 0 && isset( $gift_item_ids_map[ $item_id ] );

				if ( $only_gift_lines && ! $is_gift ) {
					continue;
				}
				$should_change = true;
			}

			if ( ! $should_change ) {
				continue;
			}

			$can_set_mode = method_exists( $receipt_item, 'setPaymentMode' ) && method_exists( $receipt_item, 'getPaymentMode' );
			if ( $can_set_mode ) {
				$current_mode = (string) $receipt_item->getPaymentMode();
				if ( $force_override || '' === $current_mode ) {
					$receipt_item->setPaymentMode( $mode );
				}
			}

			$can_set_subject = method_exists( $receipt_item, 'setPaymentSubject' ) && method_exists( $receipt_item, 'getPaymentSubject' );
			if ( $can_set_subject ) {
				$current_subject = (string) $receipt_item->getPaymentSubject();
				if ( $force_override || '' === $current_subject ) {
					$receipt_item->setPaymentSubject( $subject );
				}
			}

			if ( '' !== $template && method_exists( $receipt_item, 'setDescription' ) ) {
				$receipt_item->setDescription( self::apply_template( $template, $order, $index + 1 ) );
			}

				$changed++;
			}
		} catch ( Throwable $e ) {
			MP_RRGC_Logger::log( 'ERROR', (int) $order->get_id(), 'replace_failed_fallback_original', array(
				'provider' => 'yookassa',
				'message'  => $e->getMessage(),
			) );
			return $payment_request;
		}

		/**
		 * Allows full override of modified YooKassa request payload object.
		 *
		 * @param object   $payment_request Request object.
		 * @param WC_Order $order           Order object.
		 * @param array    $context         Context/debug data.
		 */
		$payment_request = apply_filters(
			'mp_rrgc_yk_replaced_payload',
			$payment_request,
			$order,
			array(
				'changed_items'      => $changed,
				'only_gift_lines'    => $only_gift_lines,
				'apply_to_shipping'  => $apply_to_shipping,
				'force_override'     => $force_override,
				'detection_mode'     => MP_RRGC_Settings::get_detection_mode(),
			)
		);

		MP_RRGC_Logger::log( 'INFO', (int) $order->get_id(), 'yk_payload_replaced', array(
			'changed_items'     => $changed,
			'only_gift_lines'   => $only_gift_lines,
			'apply_to_shipping' => $apply_to_shipping,
			'force_override'    => $force_override,
		) );
		if ( '' !== $request_hash ) {
			self::$seen_requests[ $request_hash ] = true;
		}

		return $payment_request;
	}

	private static function resolve_order_from_context() {
		$try_ids = array();

		if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
			$awaiting_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
			if ( $awaiting_id > 0 ) {
				$try_ids[] = $awaiting_id;
			}
		}

		if ( isset( $_GET['order-pay'] ) ) {
			$try_ids[] = absint( wp_unslash( $_GET['order-pay'] ) );
		}
		if ( isset( $_GET['order_id'] ) ) {
			$try_ids[] = absint( wp_unslash( $_GET['order_id'] ) );
		}
		if ( isset( $_POST['order_id'] ) ) {
			$try_ids[] = absint( wp_unslash( $_POST['order_id'] ) );
		}

		$try_ids = array_values( array_unique( array_filter( $try_ids ) ) );
		foreach ( $try_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		return null;
	}

	private static function apply_template( string $template, WC_Order $order, int $line_no ): string {
		$replace = array(
			'%order_id%'     => (string) $order->get_id(),
			'%order_number%' => (string) $order->get_order_number(),
			'%line_no%'      => (string) $line_no,
		);

		$result = strtr( $template, $replace );
		$result = sanitize_text_field( $result );

		if ( strlen( $result ) > 128 ) {
			$result = substr( $result, 0, 128 );
		}

		return $result;
	}
}

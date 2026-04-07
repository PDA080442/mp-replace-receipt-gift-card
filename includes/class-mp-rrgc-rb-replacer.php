<?php
/**
 * Robokassa payload replacer.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_RB_Replacer {
	public static function register_hooks(): void {
		$priority = MP_RRGC_Settings::get_hook_priority();
		add_filter(
			'wc_robokassa_receipt',
			array( __CLASS__, 'maybe_replace_receipt_data' ),
			$priority,
			1
		);
	}

	/**
	 * @param mixed $receipt
	 * @return mixed
	 */
	public static function maybe_replace_receipt_data( $receipt ) {
		if ( ! MP_RRGC_Settings::is_enabled() || ! MP_RRGC_Settings::is_rb_enabled() ) {
			return $receipt;
		}

		if ( ! is_array( $receipt ) || ! isset( $receipt['items'] ) || ! is_array( $receipt['items'] ) ) {
			return $receipt;
		}

		$order = self::resolve_order_from_context();
		if ( ! $order instanceof WC_Order ) {
			MP_RRGC_Logger::log( 'DEBUG', 0, 'rb_skip_order_not_resolved' );
			return $receipt;
		}

		if ( ! MP_RRGC_Orchestrator::should_process_order(
			$order,
			'robokassa',
			array( 'robokassa', 'robokassa_payment', 'wc_robokassa' )
		) ) {
			return $receipt;
		}

		$split = MP_RRGC_Gift_Detector::split_order_items( $order );
		if ( empty( $split['gift'] ) ) {
			MP_RRGC_Logger::log( 'DEBUG', (int) $order->get_id(), 'rb_skip_no_gift_lines' );
			return $receipt;
		}

		$line_items        = $order->get_items( 'line_item' );
		$line_item_ids     = array_keys( $line_items );
		$gift_item_ids_map = array_fill_keys( array_keys( $split['gift'] ), true );

		$payment_method    = MP_RRGC_Settings::get_rb_payment_method();
		$payment_object    = MP_RRGC_Settings::get_rb_payment_object();
		$name_template     = MP_RRGC_Settings::get_rb_name_template();
		$only_gift_lines   = MP_RRGC_Settings::rb_only_gift_lines();
		$force_override    = MP_RRGC_Settings::rb_force_override();
		$apply_to_shipping = MP_RRGC_Settings::rb_apply_to_shipping();
		$tax_override      = MP_RRGC_Settings::get_rb_tax_override();

		$changed = 0;

		foreach ( $receipt['items'] as $index => &$item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$is_shipping = self::is_shipping_item( $item );
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

			$current_method = isset( $item['payment_method'] ) ? (string) $item['payment_method'] : '';
			$current_object = isset( $item['payment_object'] ) ? (string) $item['payment_object'] : '';

			if ( $force_override || '' === $current_method ) {
				$item['payment_method'] = $payment_method;
			}
			if ( $force_override || '' === $current_object ) {
				$item['payment_object'] = $payment_object;
			}

			if ( '' !== $name_template ) {
				$item['name'] = self::apply_template( $name_template, $order, $index + 1 );
			}

			if ( '' !== $tax_override ) {
				$item['tax'] = $tax_override;
			}

			$changed++;
		}
		unset( $item );

		/**
		 * Allows full override of modified Robokassa receipt payload.
		 *
		 * @param array    $receipt Receipt array.
		 * @param WC_Order $order   Order object.
		 * @param array    $context Context/debug data.
		 */
		$receipt = apply_filters(
			'mp_rrgc_rb_replaced_payload',
			$receipt,
			$order,
			array(
				'changed_items'      => $changed,
				'only_gift_lines'    => $only_gift_lines,
				'apply_to_shipping'  => $apply_to_shipping,
				'force_override'     => $force_override,
				'detection_mode'     => MP_RRGC_Settings::get_detection_mode(),
			)
		);

		MP_RRGC_Logger::log( 'INFO', (int) $order->get_id(), 'rb_payload_replaced', array(
			'changed_items'     => $changed,
			'only_gift_lines'   => $only_gift_lines,
			'apply_to_shipping' => $apply_to_shipping,
			'force_override'    => $force_override,
		) );

		return $receipt;
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
		if ( isset( $_POST['InvId'] ) ) {
			$try_ids[] = absint( wp_unslash( $_POST['InvId'] ) );
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

	private static function is_shipping_item( array $item ): bool {
		$name = isset( $item['name'] ) ? mb_strtolower( sanitize_text_field( (string) $item['name'] ) ) : '';
		$qty  = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0.0;

		if ( false !== strpos( $name, 'доставка' ) ) {
			return true;
		}

		return 1.0 === $qty && ! isset( $item['payment_object'] ) && ! isset( $item['payment_method'] );
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


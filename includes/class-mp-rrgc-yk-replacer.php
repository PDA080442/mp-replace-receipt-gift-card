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
	public static function register_hooks(): void {
		add_filter(
			'woocommerce_yookassa_create_payment_request',
			array( __CLASS__, 'maybe_replace_receipt_data' ),
			999,
			1
		);
	}

	/**
	 * @param mixed $payment_request
	 * @return mixed
	 */
	public static function maybe_replace_receipt_data( $payment_request ) {
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

<?php
/**
 * YooKassa first-receipt replacement integration.
 *
 * @package MP_Replace_Receipt_Gift_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MP_RRGC_YK_Replacer {
	private const CREATE_PAYMENT_HOOK = 'woocommerce_yookassa_create_payment_request';

	/** @var bool */
	private static $registered = false;

	public static function register_hooks(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		add_filter( self::CREATE_PAYMENT_HOOK, array( __CLASS__, 'filter_create_payment_request' ), 999, 1 );
	}

	/**
	 * @param mixed $payment_request
	 * @return mixed
	 */
	public static function filter_create_payment_request( $payment_request ) {
		if ( ! MP_RRGC_Settings::is_enabled() || ! MP_RRGC_Settings::is_yk_enabled() ) {
			return $payment_request;
		}

		if ( ! is_object( $payment_request ) || ! method_exists( $payment_request, 'getReceipt' ) ) {
			return $payment_request;
		}

		$order = self::resolve_order_from_context();
		if ( ! $order instanceof WC_Order ) {
			return $payment_request;
		}

		if ( ! self::is_yookassa_order( $order ) ) {
			return $payment_request;
		}

		$split = MP_RRGC_Gift_Detector::split_order_items( $order );
		if ( empty( $split['gift'] ) ) {
			return $payment_request;
		}

		$has_regular = ! empty( $split['regular'] );
		if ( MP_RRGC_Settings::only_if_order_is_gift_only() && $has_regular ) {
			return $payment_request;
		}
		if ( ! MP_RRGC_Settings::allow_mixed_cart() && $has_regular ) {
			return $payment_request;
		}

		$receipt = $payment_request->getReceipt();
		if ( ! is_object( $receipt ) || ! method_exists( $receipt, 'getItems' ) || ! method_exists( $receipt, 'setItems' ) ) {
			return $payment_request;
		}

		$receipt_items = $receipt->getItems();
		if ( ! is_array( $receipt_items ) || empty( $receipt_items ) ) {
			return $payment_request;
		}

		$gift_item_names = self::collect_gift_item_names( $split['gift'] );

		$payload = self::extract_payload_from_receipt_items( $receipt_items );
		$updated = self::maybe_replace_receipt_data( $payload, $order, $gift_item_names );
		$updated = apply_filters( 'mp_rrgc_yk_replaced_payload', $updated, $payload, $order );

		self::apply_payload_to_receipt_items( $receipt_items, $updated );

		try {
			$receipt->setItems( $receipt_items );
		} catch ( Throwable $e ) {
			// Keep original request untouched on any SDK mutation errors.
			return $payment_request;
		}

		return $payment_request;
	}

	/**
	 * @param array<int,array<string,mixed>> $payload
	 * @param array<string,bool>             $gift_item_names_map
	 * @return array<int,array<string,mixed>>
	 */
	public static function maybe_replace_receipt_data( array $payload, WC_Order $order, array $gift_item_names_map = array() ): array {
		unset( $order ); // Order is kept for future rule expansion; currently not used directly.

		$force_override    = MP_RRGC_Settings::yk_force_override();
		$only_gift_lines   = MP_RRGC_Settings::yk_only_gift_lines();
		$apply_to_shipping = MP_RRGC_Settings::yk_apply_to_shipping();
		$new_mode          = MP_RRGC_Settings::get_yk_payment_mode();
		$new_subject       = MP_RRGC_Settings::get_yk_payment_subject();
		$template          = MP_RRGC_Settings::get_yk_description_template();

		foreach ( $payload as $idx => $item ) {
			$is_shipping = ! empty( $item['is_shipping'] );
			$description = isset( $item['description'] ) ? (string) $item['description'] : '';

			if ( $is_shipping && ! $apply_to_shipping ) {
				continue;
			}

			if ( $only_gift_lines && ! $is_shipping ) {
				$key = mb_strtolower( trim( $description ) );
				if ( '' === $key || ! isset( $gift_item_names_map[ $key ] ) ) {
					continue;
				}
			}

			$mode_is_empty    = empty( $item['payment_mode'] );
			$subject_is_empty = empty( $item['payment_subject'] );

			if ( $force_override || $mode_is_empty ) {
				$payload[ $idx ]['payment_mode'] = $new_mode;
			}
			if ( $force_override || $subject_is_empty ) {
				$payload[ $idx ]['payment_subject'] = $new_subject;
			}

			if ( '' !== $template ) {
				$payload[ $idx ]['description'] = self::render_description_template( $template, $description );
			}
		}

		return $payload;
	}

	/**
	 * @param array<int,mixed> $receipt_items
	 * @return array<int,array<string,mixed>>
	 */
	private static function extract_payload_from_receipt_items( array $receipt_items ): array {
		$payload = array();

		foreach ( $receipt_items as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$payload[] = array(
				'description'     => method_exists( $item, 'getDescription' ) ? (string) $item->getDescription() : '',
				'payment_mode'    => method_exists( $item, 'getPaymentMode' ) ? (string) $item->getPaymentMode() : '',
				'payment_subject' => method_exists( $item, 'getPaymentSubject' ) ? (string) $item->getPaymentSubject() : '',
				'is_shipping'     => method_exists( $item, 'isShipping' ) ? (bool) $item->isShipping() : false,
			);
		}

		return $payload;
	}

	/**
	 * @param array<int,mixed>              $receipt_items
	 * @param array<int,array<string,mixed>> $payload
	 */
	private static function apply_payload_to_receipt_items( array $receipt_items, array $payload ): void {
		foreach ( $receipt_items as $idx => $item ) {
			if ( ! is_object( $item ) || ! isset( $payload[ $idx ] ) ) {
				continue;
			}

			$row = $payload[ $idx ];

			try {
				if ( isset( $row['payment_mode'] ) && method_exists( $item, 'setPaymentMode' ) ) {
					$item->setPaymentMode( (string) $row['payment_mode'] );
				}

				if ( isset( $row['payment_subject'] ) && method_exists( $item, 'setPaymentSubject' ) ) {
					$item->setPaymentSubject( (string) $row['payment_subject'] );
				}

				if ( isset( $row['description'] ) && method_exists( $item, 'setDescription' ) ) {
					$item->setDescription( (string) $row['description'] );
				}
			} catch ( Throwable $e ) {
				// Skip only broken row and continue.
				continue;
			}
		}
	}

	private static function resolve_order_from_context(): ?WC_Order {
		$order = self::resolve_order_from_backtrace();
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		if ( function_exists( 'WC' ) && WC() && WC()->session ) {
			$order_id = (int) WC()->session->get( 'order_awaiting_payment' );
			if ( $order_id > 0 ) {
				$maybe_order = wc_get_order( $order_id );
				if ( $maybe_order instanceof WC_Order ) {
					return $maybe_order;
				}
			}
		}

		return null;
	}

	private static function resolve_order_from_backtrace(): ?WC_Order {
		$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 15 );
		foreach ( $trace as $frame ) {
			if ( empty( $frame['args'] ) || ! is_array( $frame['args'] ) ) {
				continue;
			}

			foreach ( $frame['args'] as $arg ) {
				if ( $arg instanceof WC_Order ) {
					return $arg;
				}
			}
		}

		return null;
	}

	private static function is_yookassa_order( WC_Order $order ): bool {
		$payment_method = (string) $order->get_payment_method();
		return false !== strpos( $payment_method, 'yookassa' );
	}

	/**
	 * @param array<int,WC_Order_Item_Product> $gift_items
	 * @return array<string,bool>
	 */
	private static function collect_gift_item_names( array $gift_items ): array {
		$map = array();
		foreach ( $gift_items as $item ) {
			$name = trim( (string) $item->get_name() );
			if ( '' === $name ) {
				continue;
			}
			$map[ mb_strtolower( $name ) ] = true;
		}
		return $map;
	}

	private static function render_description_template( string $template, string $original ): string {
		$result = str_replace( '%original_name%', $original, $template );
		$result = trim( $result );
		if ( '' === $result ) {
			return $original;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $result, 0, 128 );
		}

		return substr( $result, 0, 128 );
	}
}


<?php
/**
 * Order alerts.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks WooCommerce order events: new order, payment, status changes, failures and refunds (spec §7).
 */
final class OrderEvents {

	public const NEW_ORDER_HOOK = 'ordervox_new_order';

	/**
	 * Seconds to wait after checkout so line items and meta are saved before the alert is built.
	 */
	private const NEW_ORDER_DELAY = 10;

	/**
	 * Order statuses that map to a status alert. Refunds and failures have their own handlers.
	 */
	private const STATUS_ALERTS = array(
		'processing' => 'status_processing',
		'completed'  => 'status_completed',
		'cancelled'  => 'status_cancelled',
	);

	/**
	 * Statuses an order has before the customer finishes checkout.
	 */
	private const DRAFT_STATUSES = array( 'checkout-draft', 'draft', 'auto-draft', 'trash' );

	/**
	 * Sender.
	 *
	 * @var EventSender
	 */
	private EventSender $sender;

	/**
	 * Constructor.
	 *
	 * @param EventSender $sender Sender.
	 */
	public function __construct( EventSender $sender ) {
		$this->sender = $sender;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		// Classic checkout, block checkout, and orders created in wp-admin or over the REST API.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'schedule_new_order' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'schedule_new_order' ) );
		add_action( 'woocommerce_new_order', array( $this, 'schedule_new_order' ) );
		add_action( self::NEW_ORDER_HOOK, array( $this, 'new_order' ) );

		add_action( 'woocommerce_payment_complete', array( $this, 'payment_complete' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'payment_failed' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'refunded' ), 10, 2 );
	}

	/**
	 * Schedules the new-order alert a few seconds out, once per order.
	 *
	 * @param int|\WC_Order $order Order id or object, depending on the hook.
	 */
	public function schedule_new_order( $order ): void {
		$order_id = $order instanceof \WC_Order ? $order->get_id() : (int) $order;
		if ( $order_id <= 0 || ! $this->sender->is_enabled( 'order_new' ) ) {
			return;
		}
		$args = array( 'order_id' => $order_id );
		if ( ! as_has_scheduled_action( self::NEW_ORDER_HOOK, $args, 'ordervox' ) ) {
			as_schedule_single_action( time() + self::NEW_ORDER_DELAY, self::NEW_ORDER_HOOK, $args, 'ordervox' );
		}
	}

	/**
	 * Sends the new-order alert, skipping orders still in checkout.
	 *
	 * @param int $order_id Order id.
	 */
	public function new_order( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund || in_array( $order->get_status(), self::DRAFT_STATUSES, true ) ) {
			return;
		}
		$this->sender->send( 'order_new', new Context( $order ), 'order', $order->get_id() );
	}

	/**
	 * Payment received.
	 *
	 * @param int $order_id Order id.
	 */
	public function payment_complete( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->sender->send( 'payment_received', new Context( $order ), 'order', $order->get_id() );
		}
	}

	/**
	 * Processing, completed and cancelled.
	 *
	 * @param int    $order_id Order id.
	 * @param string $from     Previous status.
	 * @param string $to       New status.
	 */
	public function status_changed( $order_id, $from, $to ): void {
		$alert = self::STATUS_ALERTS[ (string) $to ] ?? null;
		if ( null === $alert ) {
			return;
		}
		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->sender->send( $alert, new Context( $order ), 'order', $order->get_id(), (string) $to );
		}
	}

	/**
	 * Failed payment.
	 *
	 * @param int $order_id Order id.
	 */
	public function payment_failed( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->sender->send( 'payment_failed', new Context( $order ), 'order', $order->get_id() );
		}
	}

	/**
	 * Full and partial refunds. Fires for every refund, including the one WooCommerce creates when an
	 * order is set to Refunded, so the status change itself is not handled.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id.
	 */
	public function refunded( $order_id, $refund_id ): void {
		$order  = wc_get_order( (int) $order_id );
		$refund = wc_get_order( (int) $refund_id );
		if ( $order instanceof \WC_Order && $refund instanceof \WC_Order_Refund ) {
			$this->sender->send( 'status_refunded', new Context( $order, null, null, $refund ), 'order', $order->get_id(), 'refund' . $refund->get_id() );
		}
	}
}

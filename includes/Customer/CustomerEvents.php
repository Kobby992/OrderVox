<?php
/**
 * Customer order messages.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Customer;

use Webbcode\Ordervox\Events\Context;
use Webbcode\Ordervox\Events\EventSender;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the customer an order confirmation and a completion message. EventSender only addresses
 * customers who opted in.
 */
final class CustomerEvents {

	/**
	 * Statuses that mean the store has received the order.
	 */
	private const CONFIRMED_STATUSES = array( 'processing', 'on-hold' );

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
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 20, 3 );
	}

	/**
	 * Confirmation on the first Processing or On hold; completion on Completed.
	 *
	 * @param int    $order_id Order id.
	 * @param string $from     Previous status.
	 * @param string $to       New status.
	 */
	public function status_changed( $order_id, $from, $to ): void {
		$to = (string) $to;
		if ( in_array( $to, self::CONFIRMED_STATUSES, true ) ) {
			$alert  = 'customer_order_confirmed';
			$dedupe = 'confirmed';
		} elseif ( 'completed' === $to ) {
			$alert  = 'customer_order_completed';
			$dedupe = 'completed';
		} else {
			return;
		}

		$order = wc_get_order( (int) $order_id );
		if ( $order instanceof \WC_Order && ! $order instanceof \WC_Order_Refund ) {
			// The dedupe text means on-hold → processing only confirms once.
			$this->sender->send( $alert, new Context( $order ), 'order', $order->get_id(), $dedupe );
		}
	}
}

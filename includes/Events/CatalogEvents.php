<?php
/**
 * Stock and review alerts.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks low-stock, out-of-stock and new product review events.
 */
final class CatalogEvents {

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
		add_action( 'woocommerce_low_stock', array( $this, 'stock' ) );
		add_action( 'woocommerce_no_stock', array( $this, 'stock' ) );
		add_action( 'comment_post', array( $this, 'review' ), 10, 2 );
	}

	/**
	 * Low or no stock. At most one alert per product per day and stock status.
	 *
	 * @param \WC_Product $product Product.
	 */
	public function stock( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$this->sender->send(
			'stock_low',
			new Context( null, $product ),
			'product',
			$product->get_id(),
			$product->get_stock_status() . ':' . wp_date( 'Y-m-d' )
		);
	}

	/**
	 * New product review, unless it was marked as spam.
	 *
	 * @param int        $comment_id Comment id.
	 * @param int|string $approved   1, 0 or 'spam'.
	 */
	public function review( $comment_id, $approved ): void {
		if ( 'spam' === $approved || 'trash' === $approved ) {
			return;
		}
		$comment = get_comment( (int) $comment_id );
		if ( ! $comment instanceof \WP_Comment || 'review' !== $comment->comment_type || 'product' !== get_post_type( (int) $comment->comment_post_ID ) ) {
			return;
		}
		$this->sender->send( 'review_new', new Context( null, null, $comment ), 'review', (int) $comment->comment_ID );
	}
}

<?php
/**
 * Data an event message is built from.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the objects an event concerns. Placeholders read from whichever are present.
 */
final class Context {

	/**
	 * Constructor.
	 *
	 * @param \WC_Order|null         $order   Order.
	 * @param \WC_Product|null       $product Product.
	 * @param \WP_Comment|null       $review  Product review.
	 * @param \WC_Order_Refund|null  $refund  Refund.
	 * @param array<string, mixed>   $summary Daily summary figures.
	 */
	public function __construct(
		public readonly ?\WC_Order $order = null,
		public readonly ?\WC_Product $product = null,
		public readonly ?\WP_Comment $review = null,
		public readonly ?\WC_Order_Refund $refund = null,
		public readonly array $summary = array()
	) {}
}

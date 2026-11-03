<?php
/**
 * Template placeholders.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Placeholders;

use Webbcode\Ordervox\Events\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Defines every `{placeholder}`, its group, a sample value, and how to resolve it (spec §8.2).
 */
final class Registry {

	/**
	 * Definitions keyed by placeholder name: group, label, sample, resolve.
	 *
	 * @var array<string, array{group: string, label: string, sample: string, resolve: callable(Context): string}>|null
	 */
	private ?array $definitions = null;

	/**
	 * All definitions.
	 *
	 * @return array<string, array{group: string, label: string, sample: string, resolve: callable(Context): string}>
	 */
	public function all(): array {
		if ( null === $this->definitions ) {
			/**
			 * Filters the placeholder definitions. Each needs group, label, sample and a resolve callback
			 * that receives a Context and returns a string.
			 *
			 * @param array<string, array<string, mixed>> $definitions Definitions.
			 */
			$this->definitions = (array) apply_filters( 'ordervox_placeholders', $this->definitions() );
		}
		return $this->definitions;
	}

	/**
	 * Definitions in the given groups.
	 *
	 * @param string[] $groups Group names.
	 * @return array<string, array{group: string, label: string, sample: string, resolve: callable(Context): string}>
	 */
	public function for_groups( array $groups ): array {
		return array_filter( $this->all(), static fn( array $definition ) => in_array( $definition['group'], $groups, true ) );
	}

	/**
	 * Resolves one placeholder. Unknown names and missing data give an empty string.
	 *
	 * @param string  $name    Placeholder name without braces.
	 * @param Context $context Event data.
	 */
	public function resolve( string $name, Context $context ): string {
		$definition = $this->all()[ $name ] ?? null;
		if ( null === $definition ) {
			return '';
		}
		return trim( (string) call_user_func( $definition['resolve'], $context ) );
	}

	/**
	 * Sample value used for Meta's template examples and previews without data.
	 *
	 * @param string $name Placeholder name.
	 */
	public function sample( string $name ): string {
		return (string) ( $this->all()[ $name ]['sample'] ?? $name );
	}

	/**
	 * Built-in definitions.
	 *
	 * @return array<string, array{group: string, label: string, sample: string, resolve: callable(Context): string}>
	 */
	private function definitions(): array {
		$order    = static fn( callable $fn ) => static fn( Context $c ) => $c->order ? (string) $fn( $c->order ) : '';
		$money    = static fn( callable $fn ) => static fn( Context $c ) => $c->order ? self::money( (float) $fn( $c->order ), $c->order->get_currency() ) : '';
		$product  = static fn( callable $fn ) => static fn( Context $c ) => $c->product ? (string) $fn( $c->product ) : '';
		$review   = static fn( callable $fn ) => static fn( Context $c ) => $c->review ? (string) $fn( $c->review ) : '';
		$summary  = static fn( string $key ) => static fn( Context $c ) => (string) ( $c->summary[ $key ] ?? '' );
		$defs     = array();
		$add      = static function ( string $group, string $name, string $label, string $sample, callable $resolve ) use ( &$defs ) {
			$defs[ $name ] = array(
				'group'   => $group,
				'label'   => $label,
				'sample'  => $sample,
				'resolve' => $resolve,
			);
		};

		// Order.
		$add( 'order', 'order_id', __( 'Order ID', 'ordervox' ), '1001', $order( static fn( \WC_Order $o ) => $o->get_id() ) );
		$add( 'order', 'order_number', __( 'Order number', 'ordervox' ), '1001', $order( static fn( \WC_Order $o ) => $o->get_order_number() ) );
		$add( 'order', 'order_status', __( 'Order status', 'ordervox' ), __( 'Processing', 'ordervox' ), $order( static fn( \WC_Order $o ) => wc_get_order_status_name( $o->get_status() ) ) );
		$add( 'order', 'order_date', __( 'Order date', 'ordervox' ), wp_date( (string) get_option( 'date_format' ) ), $order( static fn( \WC_Order $o ) => $o->get_date_created() ? wc_format_datetime( $o->get_date_created() ) : '' ) );
		$add( 'order', 'order_total', __( 'Order total', 'ordervox' ), '$55.00', $money( static fn( \WC_Order $o ) => $o->get_total() ) );
		$add( 'order', 'order_subtotal', __( 'Subtotal', 'ordervox' ), '$50.00', $money( static fn( \WC_Order $o ) => $o->get_subtotal() ) );
		$add( 'order', 'order_tax', __( 'Tax', 'ordervox' ), '$0.00', $money( static fn( \WC_Order $o ) => $o->get_total_tax() ) );
		$add( 'order', 'order_shipping', __( 'Shipping cost', 'ordervox' ), '$5.00', $money( static fn( \WC_Order $o ) => $o->get_shipping_total() ) );
		$add( 'order', 'order_discount', __( 'Discount', 'ordervox' ), '$0.00', $money( static fn( \WC_Order $o ) => $o->get_discount_total() ) );
		$add( 'order', 'coupon_codes', __( 'Coupon codes', 'ordervox' ), 'WELCOME10', $order( static fn( \WC_Order $o ) => implode( ', ', $o->get_coupon_codes() ) ) );
		$add( 'order', 'payment_method', __( 'Payment method', 'ordervox' ), __( 'Credit card', 'ordervox' ), $order( static fn( \WC_Order $o ) => $o->get_payment_method_title() ) );
		$add( 'order', 'transaction_id', __( 'Transaction ID', 'ordervox' ), 'ch_3PQ1', $order( static fn( \WC_Order $o ) => $o->get_transaction_id() ) );
		$add( 'order', 'shipping_method', __( 'Shipping method', 'ordervox' ), __( 'Flat rate', 'ordervox' ), $order( static fn( \WC_Order $o ) => $o->get_shipping_method() ) );
		$add( 'order', 'customer_note', __( 'Customer note', 'ordervox' ), __( 'Please call on arrival', 'ordervox' ), $order( static fn( \WC_Order $o ) => $o->get_customer_note() ) );
		$add( 'order', 'order_items', __( 'Items', 'ordervox' ), '2× Blue T-shirt (M), 1× Cap', $order( array( self::class, 'order_items' ) ) );
		$add( 'order', 'order_items_count', __( 'Item count', 'ordervox' ), '3', $order( static fn( \WC_Order $o ) => $o->get_item_count() ) );
		$add( 'order', 'order_admin_url', __( 'Order admin link', 'ordervox' ), admin_url( 'admin.php?page=wc-orders&action=edit&id=1001' ), $order( static fn( \WC_Order $o ) => $o->get_edit_order_url() ) );
		$add( 'order', 'order_view_url', __( 'Customer order link', 'ordervox' ), home_url( '/my-account/view-order/1001/' ), $order( static fn( \WC_Order $o ) => $o->get_view_order_url() ) );
		$add( 'order', 'order_pay_url', __( 'Payment link', 'ordervox' ), home_url( '/checkout/order-pay/1001/' ), $order( static fn( \WC_Order $o ) => $o->get_checkout_payment_url() ) );

		// Refund.
		$add( 'refund', 'refund_amount', __( 'Refund amount', 'ordervox' ), '$20.00', static fn( Context $c ) => $c->refund ? self::money( (float) $c->refund->get_amount(), $c->refund->get_currency() ) : '' );
		$add( 'refund', 'refund_reason', __( 'Refund reason', 'ordervox' ), __( 'Item damaged', 'ordervox' ), static fn( Context $c ) => $c->refund ? $c->refund->get_reason() : '' );

		// Customer.
		$add( 'customer', 'customer_first_name', __( 'Customer first name', 'ordervox' ), 'Ada', $order( static fn( \WC_Order $o ) => $o->get_billing_first_name() ) );
		$add( 'customer', 'customer_last_name', __( 'Customer last name', 'ordervox' ), 'Lovelace', $order( static fn( \WC_Order $o ) => $o->get_billing_last_name() ) );
		$add( 'customer', 'customer_full_name', __( 'Customer name', 'ordervox' ), 'Ada Lovelace', $order( static fn( \WC_Order $o ) => $o->get_formatted_billing_full_name() ) );
		$add( 'customer', 'customer_email', __( 'Customer email', 'ordervox' ), 'ada@example.com', $order( static fn( \WC_Order $o ) => $o->get_billing_email() ) );
		$add( 'customer', 'customer_phone', __( 'Customer phone', 'ordervox' ), '+14155550123', $order( static fn( \WC_Order $o ) => $o->get_billing_phone() ) );
		$add( 'customer', 'billing_address', __( 'Billing address', 'ordervox' ), '12 Main St, Springfield', $order( static fn( \WC_Order $o ) => self::address( $o->get_formatted_billing_address() ) ) );
		$add( 'customer', 'shipping_address', __( 'Shipping address', 'ordervox' ), '12 Main St, Springfield', $order( static fn( \WC_Order $o ) => self::address( $o->get_formatted_shipping_address() ) ) );
		$add( 'customer', 'billing_country', __( 'Billing country', 'ordervox' ), 'US', $order( static fn( \WC_Order $o ) => $o->get_billing_country() ) );
		$add( 'customer', 'customer_order_count', __( 'Customer’s order count', 'ordervox' ), '4', $order( array( self::class, 'customer_order_count' ) ) );
		$add( 'customer', 'customer_total_spent', __( 'Customer’s total spent', 'ordervox' ), '$420.00', $order( array( self::class, 'customer_total_spent' ) ) );

		// Product and stock.
		$add( 'product', 'product_name', __( 'Product name', 'ordervox' ), __( 'Blue T-shirt', 'ordervox' ), $product( static fn( \WC_Product $p ) => $p->get_formatted_name() ) );
		$add( 'product', 'product_sku', __( 'SKU', 'ordervox' ), 'TS-BLU-M', $product( static fn( \WC_Product $p ) => $p->get_sku() ) );
		$add( 'product', 'stock_quantity', __( 'Stock quantity', 'ordervox' ), '2', $product( static fn( \WC_Product $p ) => (string) (int) $p->get_stock_quantity() ) );
		$add( 'product', 'stock_status', __( 'Stock status', 'ordervox' ), __( 'running low', 'ordervox' ), $product( static fn( \WC_Product $p ) => 'outofstock' === $p->get_stock_status() ? __( 'out of stock', 'ordervox' ) : __( 'running low', 'ordervox' ) ) );
		$add( 'product', 'product_edit_url', __( 'Product admin link', 'ordervox' ), admin_url( 'post.php?post=42&action=edit' ), $product( static fn( \WC_Product $p ) => (string) get_edit_post_link( $p->get_parent_id() ? $p->get_parent_id() : $p->get_id(), 'raw' ) ) );

		// Review.
		$add( 'review', 'review_rating', __( 'Rating', 'ordervox' ), '5', $review( static fn( \WP_Comment $r ) => (string) (int) get_comment_meta( (int) $r->comment_ID, 'rating', true ) ) );
		$add( 'review', 'review_stars', __( 'Rating as stars', 'ordervox' ), '★★★★★', $review( static fn( \WP_Comment $r ) => str_repeat( '★', max( 0, min( 5, (int) get_comment_meta( (int) $r->comment_ID, 'rating', true ) ) ) ) ) );
		$add( 'review', 'review_author', __( 'Reviewer', 'ordervox' ), 'Ada', $review( static fn( \WP_Comment $r ) => $r->comment_author ) );
		$add( 'review', 'review_excerpt', __( 'Review excerpt', 'ordervox' ), __( 'Great quality, fast delivery.', 'ordervox' ), $review( static fn( \WP_Comment $r ) => wp_trim_words( $r->comment_content, 30, '…' ) ) );
		$add( 'review', 'review_product', __( 'Reviewed product', 'ordervox' ), __( 'Blue T-shirt', 'ordervox' ), $review( static fn( \WP_Comment $r ) => get_the_title( (int) $r->comment_post_ID ) ) );
		$add( 'review', 'review_url', __( 'Review admin link', 'ordervox' ), admin_url( 'edit.php?post_type=product&page=product-reviews' ), $review( static fn( \WP_Comment $r ) => admin_url( 'edit.php?post_type=product&page=product-reviews&comment_status=all&s=' . rawurlencode( (string) $r->comment_author_email ) ) ) );

		// Store.
		$add( 'store', 'store_name', __( 'Store name', 'ordervox' ), get_bloginfo( 'name' ), static fn() => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$add( 'store', 'store_url', __( 'Store URL', 'ordervox' ), home_url( '/' ), static fn() => home_url( '/' ) );
		$add( 'store', 'store_email', __( 'Store email', 'ordervox' ), get_option( 'admin_email' ), static fn() => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) );
		$add( 'store', 'current_date', __( 'Today’s date', 'ordervox' ), wp_date( (string) get_option( 'date_format' ) ), static fn() => (string) wp_date( (string) get_option( 'date_format' ) ) );

		// Daily summary.
		$add( 'summary', 'summary_period', __( 'Summary period', 'ordervox' ), __( 'the last 24 hours', 'ordervox' ), $summary( 'period' ) );
		$add( 'summary', 'summary_orders', __( 'Orders', 'ordervox' ), '23', $summary( 'orders' ) );
		$add( 'summary', 'summary_revenue', __( 'Revenue', 'ordervox' ), '$1,240.00', $summary( 'revenue' ) );
		$add( 'summary', 'summary_aov', __( 'Average order value', 'ordervox' ), '$53.91', $summary( 'aov' ) );
		$add( 'summary', 'summary_top_product', __( 'Top product', 'ordervox' ), __( 'Blue T-shirt (9 sold)', 'ordervox' ), $summary( 'top_product' ) );
		$add( 'summary', 'summary_processing', __( 'Orders awaiting fulfilment', 'ordervox' ), '5', $summary( 'processing' ) );
		$add( 'summary', 'summary_out_of_stock', __( 'Products out of stock', 'ordervox' ), '2', $summary( 'out_of_stock' ) );

		return $defs;
	}

	/**
	 * Formats money as plain text, e.g. `₦12,500.00`.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 */
	public static function money( float $amount, string $currency = '' ): string {
		$html = wc_price( $amount, '' !== $currency ? array( 'currency' => $currency ) : array() );
		return html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * `2× Blue T-shirt (M), 1× Cap`.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_items( \WC_Order $order ): string {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$name = $item->get_name();
			$meta = $item instanceof \WC_Order_Item_Product ? wc_display_item_meta(
				$item,
				array(
					'echo'         => false,
					'before'       => '',
					'after'        => '',
					'separator'    => ', ',
					'label_before' => '',
					'label_after'  => ': ',
					'autop'        => false,
				)
			) : '';
			$meta = trim( wp_strip_all_tags( (string) $meta ) );
			// Variation names already include their attributes; only add meta when it adds something.
			if ( '' !== $meta && false === strpos( $name, $meta ) ) {
				$name .= ' (' . $meta . ')';
			}
			$items[] = $item->get_quantity() . '× ' . $name;
		}

		/**
		 * Filters the `{order_items}` text.
		 *
		 * @param string    $text  Items joined with commas.
		 * @param string[]  $items One entry per line item.
		 * @param \WC_Order $order Order.
		 */
		return (string) apply_filters( 'ordervox_order_items_format', implode( ', ', $items ), $items, $order );
	}

	/**
	 * Number of orders by the same customer (by account, else by billing email).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function customer_order_count( \WC_Order $order ): string {
		$args = array(
			'limit'  => -1,
			'return' => 'ids',
			'status' => array_keys( wc_get_order_statuses() ),
		);
		if ( $order->get_customer_id() ) {
			$args['customer_id'] = $order->get_customer_id();
		} else {
			$args['billing_email'] = $order->get_billing_email();
		}
		return (string) count( wc_get_orders( $args ) );
	}

	/**
	 * Total spent by a registered customer.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function customer_total_spent( \WC_Order $order ): string {
		if ( ! $order->get_customer_id() ) {
			return self::money( (float) $order->get_total(), $order->get_currency() );
		}
		$customer = new \WC_Customer( $order->get_customer_id() );
		return self::money( (float) $customer->get_total_spent(), $order->get_currency() );
	}

	/**
	 * Formatted address as one line.
	 *
	 * @param string $html Address with <br/> separators.
	 */
	private static function address( string $html ): string {
		$parts = preg_split( '/<br\s*\/?>/i', $html );
		return implode( ', ', array_filter( array_map( static fn( $part ) => trim( wp_strip_all_tags( (string) $part ) ), (array) $parts ) ) );
	}
}

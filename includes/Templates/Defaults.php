<?php
/**
 * Alert definitions and default template text.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Labels, placeholder groups and default messages for each alert.
 */
final class Defaults {

	/**
	 * Alert keys in display order. All but daily_summary are toggled under `events` in Settings.
	 */
	public const ALERTS = array(
		'order_new',
		'payment_received',
		'status_processing',
		'status_completed',
		'status_cancelled',
		'status_refunded',
		'payment_failed',
		'stock_low',
		'review_new',
		'daily_summary',
	);

	/**
	 * Placeholder groups available to each alert.
	 */
	private const GROUPS = array(
		'order_new'         => array( 'order', 'customer', 'store' ),
		'payment_received'  => array( 'order', 'customer', 'store' ),
		'status_processing' => array( 'order', 'customer', 'store' ),
		'status_completed'  => array( 'order', 'customer', 'store' ),
		'status_cancelled'  => array( 'order', 'customer', 'store' ),
		'status_refunded'   => array( 'order', 'refund', 'customer', 'store' ),
		'payment_failed'    => array( 'order', 'customer', 'store' ),
		'stock_low'         => array( 'product', 'store' ),
		'review_new'        => array( 'review', 'store' ),
		'daily_summary'     => array( 'summary', 'store' ),
	);

	/**
	 * Placeholder groups for an alert.
	 *
	 * @param string $alert Alert key.
	 * @return string[]
	 */
	public static function groups( string $alert ): array {
		return self::GROUPS[ $alert ] ?? array( 'store' );
	}

	/**
	 * Human label and description for an alert.
	 *
	 * @param string $alert Alert key.
	 * @return array{label: string, description: string}
	 */
	public static function describe( string $alert ): array {
		$all = array(
			'order_new'         => array( __( 'New order', 'ordervox' ), __( 'When a customer places an order.', 'ordervox' ) ),
			'payment_received'  => array( __( 'Payment received', 'ordervox' ), __( 'When an online payment completes.', 'ordervox' ) ),
			'status_processing' => array( __( 'Order processing', 'ordervox' ), __( 'When an order moves to Processing.', 'ordervox' ) ),
			'status_completed'  => array( __( 'Order completed', 'ordervox' ), __( 'When an order is marked Completed.', 'ordervox' ) ),
			'status_cancelled'  => array( __( 'Order cancelled', 'ordervox' ), __( 'When an order is cancelled.', 'ordervox' ) ),
			'status_refunded'   => array( __( 'Order refunded', 'ordervox' ), __( 'When a full or partial refund is made.', 'ordervox' ) ),
			'payment_failed'    => array( __( 'Payment failed', 'ordervox' ), __( 'When a payment attempt fails.', 'ordervox' ) ),
			'stock_low'         => array( __( 'Low or out of stock', 'ordervox' ), __( 'When a product reaches its low-stock threshold or sells out. At most once a day per product.', 'ordervox' ) ),
			'review_new'        => array( __( 'New review', 'ordervox' ), __( 'When a customer reviews a product.', 'ordervox' ) ),
			'daily_summary'     => array( __( 'Daily summary', 'ordervox' ), __( 'Orders, revenue and stock for the last 24 hours, at the time you choose.', 'ordervox' ) ),
		);
		$item = $all[ $alert ] ?? array( $alert, '' );
		return array(
			'label'       => $item[0],
			'description' => $item[1],
		);
	}

	/**
	 * Default body text for an alert, in the site language.
	 *
	 * @param string $alert Alert key.
	 */
	public static function body( string $alert ): string {
		$view = "\n\n" . __( 'Open {order_admin_url} to view the order.', 'ordervox' );

		switch ( $alert ) {
			case 'order_new':
				return __( "🛒 New order #{order_number} for {order_total}.\n\nCustomer: {customer_full_name}, {customer_phone}\nItems: {order_items}\nPayment: {payment_method}\nShip to: {shipping_address}", 'ordervox' ) . $view;
			case 'payment_received':
				return __( "💰 Payment received for order #{order_number}: {order_total} via {payment_method}.\nTransaction ID: {transaction_id}", 'ordervox' ) . $view;
			case 'status_processing':
				return __( '📦 Order #{order_number} from {customer_full_name} is now processing ({order_total}).', 'ordervox' ) . $view;
			case 'status_completed':
				return __( '✅ Order #{order_number} from {customer_full_name} is completed ({order_total}).', 'ordervox' ) . $view;
			case 'status_cancelled':
				return __( '❌ Order #{order_number} from {customer_full_name} was cancelled ({order_total}).', 'ordervox' ) . $view;
			case 'status_refunded':
				return __( "↩️ Refund of {refund_amount} on order #{order_number} from {customer_full_name}.\nReason: {refund_reason}", 'ordervox' ) . $view;
			case 'payment_failed':
				return __( '⚠️ Payment failed for order #{order_number} ({order_total}) from {customer_full_name} via {payment_method}.', 'ordervox' ) . $view;
			case 'stock_low':
				return __( "📉 Stock alert: {product_name} (SKU {product_sku}) is {stock_status}, with {stock_quantity} left.\n\nOpen {product_edit_url} to restock.", 'ordervox' );
			case 'review_new':
				return __( "⭐ New {review_rating}-star review of {review_product} by {review_author}:\n“{review_excerpt}”\n\nOpen {review_url} to read it.", 'ordervox' );
			case 'daily_summary':
				return __( "📊 {store_name} summary for {summary_period}:\nOrders: {summary_orders}\nRevenue: {summary_revenue}\nAverage order: {summary_aov}\nTop product: {summary_top_product}\nAwaiting fulfilment: {summary_processing}\nOut of stock: {summary_out_of_stock}\n\nHave a great day!", 'ordervox' );
		}
		return '';
	}

	/**
	 * Default footer text.
	 */
	public static function footer(): string {
		return __( 'Sent by Ordervox', 'ordervox' );
	}
}

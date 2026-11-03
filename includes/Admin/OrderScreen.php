<?php
/**
 * WhatsApp message history on order screens.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Admin;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Webbcode\Ordervox\Log\MessageRepository;
use Webbcode\Ordervox\Support\Phone;
use Webbcode\Ordervox\Templates\Defaults;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "WhatsApp messages" box to the order edit screen and a status column to the orders list,
 * on both HPOS and legacy order storage.
 */
final class OrderScreen {

	private const COLUMN = 'ordervox_whatsapp';

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Constructor.
	 *
	 * @param MessageRepository $messages Message log.
	 */
	public function __construct( MessageRepository $messages ) {
		$this->messages = $messages;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// HPOS orders list.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Legacy orders list.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 10, 2 );

		add_action( 'admin_head', array( $this, 'styles' ) );
	}

	/**
	 * Adds the meta box on whichever order screen is in use.
	 */
	public function add_meta_box(): void {
		$screen = class_exists( CustomOrdersTableController::class )
			&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box( 'ordervox-messages', __( 'WhatsApp messages', 'ordervox' ), array( $this, 'render_meta_box' ), $screen, 'side', 'default' );
	}

	/**
	 * Lists messages sent for this order.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Legacy post or HPOS order.
	 */
	public function render_meta_box( $post_or_order ): void {
		$order_id = $post_or_order instanceof \WC_Order ? $post_or_order->get_id() : (int) $post_or_order->ID;
		$rows     = $this->messages->paginate(
			array(
				'object_type' => 'order',
				'object_id'   => $order_id,
			),
			1,
			20
		)['items'];

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No WhatsApp messages for this order yet.', 'ordervox' ) . '</p>';
			return;
		}

		echo '<ul class="ordervox-order-messages">';
		foreach ( $rows as $row ) {
			$label = Defaults::describe( (string) $row['event'] )['label'];
			$time  = strtotime( (string) $row['created_at'] . ' UTC' );
			printf(
				'<li><strong>%1$s</strong> %2$s<br /><span class="description">%3$s · %4$s</span>%5$s</li>',
				esc_html( $label ),
				self::badge( (string) $row['status'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
				esc_html( Phone::mask( (string) $row['recipient_phone'] ) ),
				esc_html( (string) wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) $time ) ),
				'' !== (string) $row['error_message'] ? '<br /><span class="ordervox-order-messages__error">' . esc_html( (string) $row['error_message'] ) . '</span>' : ''
			);
		}
		echo '</ul>';
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG ) ),
			esc_html__( 'Open message log', 'ordervox' )
		);
	}

	/**
	 * Adds the WhatsApp column after the order status.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out[ self::COLUMN ] = __( 'WhatsApp', 'ordervox' );
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'WhatsApp', 'ordervox' );
		}
		return $out;
	}

	/**
	 * Shows the latest message status for an order.
	 *
	 * @param string             $column      Column key.
	 * @param int|\WC_Order|null $order_or_id Order (HPOS) or post id (legacy).
	 */
	public function render_column( $column, $order_or_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$order_id = $order_or_id instanceof \WC_Order ? $order_or_id->get_id() : (int) $order_or_id;
		$latest   = $this->messages->paginate(
			array(
				'object_type' => 'order',
				'object_id'   => $order_id,
			),
			1,
			1
		)['items'][0] ?? null;

		echo null === $latest ? '<span aria-hidden="true">–</span>' : self::badge( (string) $latest['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
	}

	/**
	 * Small CSS for the badges, only on order screens.
	 */
	public function styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order' ), true ) ) {
			return;
		}
		echo '<style>
			.ordervox-badge{display:inline-block;padding:0 7px;border-radius:9px;font-size:11px;line-height:18px;background:#f0f0f1;color:#1e1e1e}
			.ordervox-badge--delivered,.ordervox-badge--read{background:#d1f0dc;color:#0b5a2a}
			.ordervox-badge--sent{background:#dbe9fb;color:#0a4b8f}
			.ordervox-badge--queued,.ordervox-badge--sending{background:#fcf0d0;color:#6b4a00}
			.ordervox-badge--failed{background:#fbe0e0;color:#8a1f1f}
			.ordervox-order-messages li{margin-bottom:10px}
			.ordervox-order-messages__error{color:#8a1f1f}
			.column-ordervox_whatsapp{width:90px}
		</style>';
	}

	/**
	 * Escaped status badge.
	 *
	 * @param string $status Message status.
	 */
	private static function badge( string $status ): string {
		$labels = array(
			'queued'    => __( 'Queued', 'ordervox' ),
			'sending'   => __( 'Sending', 'ordervox' ),
			'sent'      => __( 'Sent', 'ordervox' ),
			'delivered' => __( 'Delivered', 'ordervox' ),
			'read'      => __( 'Read', 'ordervox' ),
			'failed'    => __( 'Failed', 'ordervox' ),
			'cancelled' => __( 'Cancelled', 'ordervox' ),
		);
		return sprintf(
			'<span class="ordervox-badge ordervox-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $labels[ $status ] ?? $status )
		);
	}
}

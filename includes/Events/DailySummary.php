<?php
/**
 * Daily sales summary.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Events;

use Webbcode\Ordervox\Placeholders\Registry;
use Webbcode\Ordervox\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the last 24 hours' orders, revenue and stock at the time the store owner picks, in the site timezone.
 */
final class DailySummary {

	public const HOOK = 'ordervox_daily_summary';

	/**
	 * Sender.
	 *
	 * @var EventSender
	 */
	private EventSender $sender;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param EventSender $sender   Sender.
	 * @param Settings    $settings Settings.
	 */
	public function __construct( EventSender $sender, Settings $settings ) {
		$this->sender   = $sender;
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'action_scheduler_init', array( $this, 'ensure_scheduled' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'on_settings_saved' ), 10, 2 );
	}

	/**
	 * Schedules the next run if the summary is on and nothing is scheduled.
	 */
	public function ensure_scheduled(): void {
		if ( $this->settings->get( 'daily_summary.enabled', false ) && ! as_has_scheduled_action( self::HOOK, array(), 'ordervox' ) ) {
			$this->schedule_next( (string) $this->settings->get( 'daily_summary.time', '08:00' ) );
		}
	}

	/**
	 * Reschedules when the summary is switched on or off or its time changes.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value New settings.
	 */
	public function on_settings_saved( $old_value, $new_value ): void {
		$old = is_array( $old_value ) ? (array) ( $old_value['daily_summary'] ?? array() ) : array();
		$new = is_array( $new_value ) ? (array) ( $new_value['daily_summary'] ?? array() ) : array();
		if ( $old == $new ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Compare array contents regardless of key order.
			return;
		}
		as_unschedule_all_actions( self::HOOK, array(), 'ordervox' );
		if ( ! empty( $new['enabled'] ) ) {
			$this->schedule_next( (string) ( $new['time'] ?? '08:00' ) );
		}
	}

	/**
	 * Sends the summary and schedules tomorrow's.
	 */
	public function run(): void {
		$this->sender->send( 'daily_summary', new Context( null, null, null, null, $this->figures( time() ) ), '', 0, wp_date( 'Y-m-d' ) );
		$this->schedule_next( (string) $this->settings->get( 'daily_summary.time', '08:00' ) );
	}

	/**
	 * Figures for the 24 hours before $until.
	 *
	 * @param int $until Unix timestamp.
	 * @return array<string, string>
	 */
	public function figures( int $until ): array {
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'type'         => 'shop_order',
				'status'       => array_merge( wc_get_is_paid_statuses(), array( 'on-hold' ) ),
				'date_created' => ( $until - DAY_IN_SECONDS ) . '...' . $until,
			)
		);

		$revenue  = 0.0;
		$products = array();
		foreach ( $orders as $order ) {
			$revenue += (float) $order->get_total() - (float) $order->get_total_refunded();
			foreach ( $order->get_items() as $item ) {
				$name              = $item->get_name();
				$products[ $name ] = ( $products[ $name ] ?? 0 ) + (int) $item->get_quantity();
			}
		}
		arsort( $products );

		$count = count( $orders );
		$top   = $products ? sprintf(
			/* translators: 1: product name, 2: quantity sold. */
			__( '%1$s (%2$d sold)', 'ordervox' ),
			(string) array_key_first( $products ),
			(int) reset( $products )
		) : __( 'none', 'ordervox' );

		$out_of_stock = wc_get_products(
			array(
				'status'       => 'publish',
				'stock_status' => 'outofstock',
				'limit'        => -1,
				'return'       => 'ids',
			)
		);

		return array(
			'period'       => __( 'the last 24 hours', 'ordervox' ),
			'orders'       => (string) $count,
			'revenue'      => Registry::money( $revenue ),
			'aov'          => Registry::money( $count ? $revenue / $count : 0.0 ),
			'top_product'  => $top,
			'processing'   => (string) wc_orders_count( 'processing' ),
			'out_of_stock' => (string) count( (array) $out_of_stock ),
		);
	}

	/**
	 * Schedules a single run at the next occurrence of HH:MM in the site timezone.
	 *
	 * @param string $time Time as HH:MM.
	 */
	private function schedule_next( string $time ): void {
		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $parts ) ) {
			$parts = array( '', '8', '00' );
		}
		$next = new \DateTimeImmutable( 'now', wp_timezone() );
		$next = $next->setTime( (int) $parts[1], (int) $parts[2] );
		if ( $next->getTimestamp() <= time() + 60 ) {
			$next = $next->modify( '+1 day' );
		}
		as_schedule_single_action( $next->getTimestamp(), self::HOOK, array(), 'ordervox' );
	}
}

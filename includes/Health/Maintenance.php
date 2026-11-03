<?php
/**
 * Daily housekeeping and health notices.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Health;

use Webbcode\Ordervox\Admin\Menu;
use Webbcode\Ordervox\Log\MessageRepository;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Webhook\Processor;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the daily health check and log cleanup, and warns admins when the connection breaks.
 */
final class Maintenance {

	public const HOOK = 'ordervox_daily_maintenance';

	/**
	 * Health check.
	 *
	 * @var HealthCheck
	 */
	private HealthCheck $health;

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param HealthCheck       $health   Health check.
	 * @param MessageRepository $messages Message log.
	 * @param Settings          $settings Settings.
	 */
	public function __construct( HealthCheck $health, MessageRepository $messages, Settings $settings ) {
		$this->health   = $health;
		$this->messages = $messages;
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'action_scheduler_init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Schedules the daily run once.
	 */
	public function schedule(): void {
		if ( ! as_has_scheduled_action( self::HOOK, array(), 'ordervox' ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), 'ordervox' );
		}
	}

	/**
	 * Daily run.
	 */
	public function run(): void {
		$this->messages->purge_older_than( (int) $this->settings->get( 'log_retention_days', 30 ) );
		Processor::purge_events();
		$this->health->run();
	}

	/**
	 * Shows a notice on the dashboard, plugins and WooCommerce screens when the last check found an error.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
			return;
		}

		$last = HealthCheck::last();
		if ( null === $last || 'error' !== ( $last['status'] ?? '' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Ordervox:', 'ordervox' ),
			esc_html__( 'WhatsApp notifications may not be sending.', 'ordervox' ),
			esc_url( admin_url( 'admin.php?page=' . Menu::SLUG ) ),
			esc_html__( 'Check the connection', 'ordervox' )
		);
	}
}

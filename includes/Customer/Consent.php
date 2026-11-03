<?php
/**
 * Customer consent to WhatsApp messages.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Customer;

use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Support\Phone;

defined( 'ABSPATH' ) || exit;

/**
 * Records and checks consent. A customer is only messaged when the order carries their opt-in,
 * the billing phone is valid, their account has not switched messages off, and the number is not
 * on the opt-out list (spec §9.4).
 */
class Consent {

	public const ORDER_OPTIN     = '_ordervox_optin';
	public const ORDER_MARKETING = '_ordervox_optin_marketing';
	public const ORDER_TIME      = '_ordervox_optin_at';
	public const ORDER_TEXT      = '_ordervox_optin_text';
	public const ORDER_SOURCE    = '_ordervox_optin_source';
	public const ORDER_IP_HASH   = '_ordervox_optin_ip_hash';
	public const USER_OPTIN      = 'ordervox_optin';
	public const USER_MARKETING  = 'ordervox_optin_marketing';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Opt-out list.
	 *
	 * @var OptOuts
	 */
	private OptOuts $optouts;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param OptOuts  $optouts  Opt-out list.
	 */
	public function __construct( Settings $settings, OptOuts $optouts ) {
		$this->settings = $settings;
		$this->optouts  = $optouts;
	}

	/**
	 * Checkbox label shown at checkout and in My Account.
	 */
	public function label(): string {
		$label = trim( (string) $this->settings->get( 'customer.optin_label', '' ) );
		return '' !== $label ? $label : __( 'Send me order updates on WhatsApp', 'ordervox' );
	}

	/**
	 * Marketing checkbox label.
	 */
	public function marketing_label(): string {
		return __( 'Also send me offers and reminders on WhatsApp', 'ordervox' );
	}

	/**
	 * Whether the opt-in checkbox is shown.
	 */
	public function is_enabled(): bool {
		return (bool) $this->settings->get( 'customer.optin_enabled', false );
	}

	/**
	 * Whether the marketing checkbox is shown.
	 */
	public function marketing_enabled(): bool {
		return $this->is_enabled() && (bool) $this->settings->get( 'customer.marketing_optin', false );
	}

	/**
	 * Stores the customer's choice on the order (and their account). Does not save the order.
	 *
	 * @param \WC_Order $order     Order.
	 * @param bool      $optin     Order updates opt-in.
	 * @param bool      $marketing Marketing opt-in.
	 * @param string    $source    classic_checkout, block_checkout or account.
	 */
	public function record( \WC_Order $order, bool $optin, bool $marketing, string $source ): void {
		$order->update_meta_data( self::ORDER_OPTIN, $optin ? 'yes' : 'no' );
		$order->update_meta_data( self::ORDER_MARKETING, $optin && $marketing ? 'yes' : 'no' );

		if ( ! $optin ) {
			return;
		}

		$ip = class_exists( \WC_Geolocation::class ) ? \WC_Geolocation::get_ip_address() : '';
		$order->update_meta_data( self::ORDER_TIME, gmdate( 'c' ) );
		$order->update_meta_data( self::ORDER_TEXT, $this->label() );
		$order->update_meta_data( self::ORDER_SOURCE, $source );
		$order->update_meta_data( self::ORDER_IP_HASH, hash( 'sha256', $ip . wp_salt( 'nonce' ) ) );

		if ( $order->get_customer_id() ) {
			update_user_meta( $order->get_customer_id(), self::USER_OPTIN, 'yes' );
			update_user_meta( $order->get_customer_id(), self::USER_MARKETING, $marketing ? 'yes' : 'no' );
		}

		// A fresh, explicit opt-in overrides an earlier STOP.
		$phone = $this->phone( $order );
		if ( null !== $phone ) {
			$this->optouts->remove( $phone );
		}
	}

	/**
	 * The order's billing phone in E.164, using the billing country for national numbers.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function phone( \WC_Order $order ): ?string {
		return Phone::normalise( $order->get_billing_phone(), $order->get_billing_country() );
	}

	/**
	 * Whether this order's customer may receive WhatsApp messages now.
	 *
	 * @param \WC_Order $order     Order.
	 * @param bool      $marketing True to require marketing consent too.
	 */
	public function allows( \WC_Order $order, bool $marketing = false ): bool {
		$allowed = 'yes' === $order->get_meta( self::ORDER_OPTIN )
			&& ( ! $marketing || 'yes' === $order->get_meta( self::ORDER_MARKETING ) );

		if ( $allowed && $order->get_customer_id() && 'no' === get_user_meta( $order->get_customer_id(), self::USER_OPTIN, true ) ) {
			$allowed = false;
		}

		$phone = $allowed ? $this->phone( $order ) : null;
		if ( null === $phone || $this->optouts->is_opted_out( $phone ) ) {
			$allowed = false;
		}

		/**
		 * Filters whether a customer may receive WhatsApp messages for an order.
		 *
		 * @param bool      $allowed   Whether messages are allowed.
		 * @param \WC_Order $order     Order.
		 * @param bool      $marketing Whether marketing consent was required.
		 */
		return (bool) apply_filters( 'ordervox_customer_can_receive', $allowed, $order, $marketing );
	}

	/**
	 * The phone to message, or null when the customer has not consented.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function recipient( \WC_Order $order ): ?string {
		return $this->allows( $order ) ? $this->phone( $order ) : null;
	}

	/**
	 * Saves a registered customer's choice from My Account and updates the opt-out list.
	 *
	 * @param int  $user_id User id.
	 * @param bool $optin   Whether they want messages.
	 */
	public function set_user_choice( int $user_id, bool $optin ): void {
		update_user_meta( $user_id, self::USER_OPTIN, $optin ? 'yes' : 'no' );
		if ( ! $optin ) {
			update_user_meta( $user_id, self::USER_MARKETING, 'no' );
		}

		$customer = new \WC_Customer( $user_id );
		$phone    = Phone::normalise( $customer->get_billing_phone(), $customer->get_billing_country() );
		if ( null === $phone ) {
			return;
		}
		if ( $optin ) {
			$this->optouts->remove( $phone );
		} else {
			$this->optouts->add( $phone, 'account' );
		}
	}

	/**
	 * Whether a registered customer has messages switched on (defaults to off).
	 *
	 * @param int $user_id User id.
	 */
	public function user_opted_in( int $user_id ): bool {
		return 'yes' === get_user_meta( $user_id, self::USER_OPTIN, true );
	}
}

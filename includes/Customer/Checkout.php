<?php
/**
 * WhatsApp opt-in at checkout.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the opt-in checkbox to the classic checkout and, through the Additional Checkout Fields API,
 * to the block checkout, and records the answer on the order.
 */
final class Checkout {

	public const FIELD           = 'ordervox_optin';
	public const MARKETING_FIELD = 'ordervox_optin_marketing';
	public const BLOCK_FIELD     = 'ordervox/optin';
	public const BLOCK_MARKETING = 'ordervox/optin-marketing';

	/**
	 * Consent.
	 *
	 * @var Consent
	 */
	private Consent $consent;

	/**
	 * Constructor.
	 *
	 * @param Consent $consent Consent.
	 */
	public function __construct( Consent $consent ) {
		$this->consent = $consent;
	}

	/**
	 * Registers hooks when the opt-in is switched on.
	 */
	public function register(): void {
		if ( ! $this->consent->is_enabled() ) {
			return;
		}

		// Classic checkout.
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_classic' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_classic' ) );

		// Block checkout.
		add_action( 'woocommerce_init', array( $this, 'register_block_fields' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_block' ) );
	}

	/**
	 * Prints the checkboxes on the classic checkout.
	 */
	public function render_classic(): void {
		$user_id = get_current_user_id();
		$checked = $user_id && $this->consent->user_opted_in( $user_id );

		woocommerce_form_field(
			self::FIELD,
			array(
				'type'  => 'checkbox',
				'class' => array( 'form-row-wide', 'ordervox-optin' ),
				'label' => esc_html( $this->consent->label() ),
			),
			$checked ? 1 : 0
		);

		if ( $this->consent->marketing_enabled() ) {
			woocommerce_form_field(
				self::MARKETING_FIELD,
				array(
					'type'  => 'checkbox',
					'class' => array( 'form-row-wide', 'ordervox-optin-marketing' ),
					'label' => esc_html( $this->consent->marketing_label() ),
				),
				0
			);
		}
	}

	/**
	 * Saves the classic checkout answer. WooCommerce has already verified the checkout nonce.
	 *
	 * @param \WC_Order $order Order being created.
	 */
	public function save_classic( $order ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by WC_Checkout::process_checkout().
		$optin     = ! empty( $_POST[ self::FIELD ] );
		$marketing = ! empty( $_POST[ self::MARKETING_FIELD ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $order instanceof \WC_Order ) {
			$this->consent->record( $order, $optin, $marketing, 'classic_checkout' );
		}
	}

	/**
	 * Registers the block checkout checkboxes in the contact section.
	 */
	public function register_block_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::BLOCK_FIELD,
				'label'    => $this->consent->label(),
				'location' => 'contact',
				'type'     => 'checkbox',
			)
		);

		if ( $this->consent->marketing_enabled() ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => self::BLOCK_MARKETING,
					'label'    => $this->consent->marketing_label(),
					'location' => 'contact',
					'type'     => 'checkbox',
				)
			);
		}
	}

	/**
	 * Saves the block checkout answer once the order is placed.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function save_block( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$this->consent->record(
			$order,
			$this->block_value( $order, self::BLOCK_FIELD ),
			$this->block_value( $order, self::BLOCK_MARKETING ),
			'block_checkout'
		);
		$order->save();
	}

	/**
	 * Reads a block checkout checkbox from the order.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $field Field id.
	 */
	private function block_value( \WC_Order $order, string $field ): bool {
		$fields_class = \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class;
		if ( class_exists( \Automattic\WooCommerce\Blocks\Package::class ) && class_exists( $fields_class ) ) {
			$value = \Automattic\WooCommerce\Blocks\Package::container()->get( $fields_class )->get_field_from_object( $field, $order, 'other' );
		} else {
			$value = $order->get_meta( '_wc_other/' . $field );
		}
		return wc_string_to_bool( (string) $value );
	}
}

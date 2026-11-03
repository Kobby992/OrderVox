<?php
/**
 * WordPress personal data tools.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Privacy;

use Webbcode\Ordervox\Customer\Consent;
use Webbcode\Ordervox\Customer\OptOuts;
use Webbcode\Ordervox\Log\MessageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Ordervox data to Tools → Export/Erase Personal Data, and suggests privacy policy text
 * (spec §14).
 */
final class Privacy {

	private const PER_PAGE = 20;

	/**
	 * Consent.
	 *
	 * @var Consent
	 */
	private Consent $consent;

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Opt-out list.
	 *
	 * @var OptOuts
	 */
	private OptOuts $optouts;

	/**
	 * Constructor.
	 *
	 * @param Consent           $consent  Consent.
	 * @param MessageRepository $messages Message log.
	 * @param OptOuts           $optouts  Opt-out list.
	 */
	public function __construct( Consent $consent, MessageRepository $messages, OptOuts $optouts ) {
		$this->consent  = $consent;
		$this->messages = $messages;
		$this->optouts  = $optouts;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'policy_text' ) );
	}

	/**
	 * Adds the exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['ordervox'] = array(
			'exporter_friendly_name' => __( 'Ordervox WhatsApp messages', 'ordervox' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Adds the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['ordervox'] = array(
			'eraser_friendly_name' => __( 'Ordervox WhatsApp messages', 'ordervox' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports WhatsApp consent and messages for the customer's orders.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, starting at 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$orders = $this->orders( $email, $page );
		$data   = array();
		$phones = array();

		foreach ( $orders as $order ) {
			$fields = array(
				array(
					'name'  => __( 'Order', 'ordervox' ),
					'value' => $order->get_order_number(),
				),
				array(
					'name'  => __( 'WhatsApp order updates', 'ordervox' ),
					'value' => 'yes' === $order->get_meta( Consent::ORDER_OPTIN ) ? __( 'Yes', 'ordervox' ) : __( 'No', 'ordervox' ),
				),
				array(
					'name'  => __( 'WhatsApp offers', 'ordervox' ),
					'value' => 'yes' === $order->get_meta( Consent::ORDER_MARKETING ) ? __( 'Yes', 'ordervox' ) : __( 'No', 'ordervox' ),
				),
				array(
					'name'  => __( 'Consent given at', 'ordervox' ),
					'value' => (string) $order->get_meta( Consent::ORDER_TIME ),
				),
				array(
					'name'  => __( 'Consent wording', 'ordervox' ),
					'value' => (string) $order->get_meta( Consent::ORDER_TEXT ),
				),
			);
			$data[] = array(
				'group_id'    => 'ordervox-consent',
				'group_label' => __( 'WhatsApp consent', 'ordervox' ),
				'item_id'     => 'ordervox-consent-' . $order->get_id(),
				'data'        => $fields,
			);

			$phone = $this->consent->phone( $order );
			if ( null !== $phone ) {
				$phones[ $phone ] = true;
			}
		}

		foreach ( array_keys( $phones ) as $phone ) {
			$data[] = array(
				'group_id'    => 'ordervox-optout',
				'group_label' => __( 'WhatsApp opt-out', 'ordervox' ),
				'item_id'     => 'ordervox-optout-' . md5( $phone ),
				'data'        => array(
					array(
						'name'  => __( 'Phone number', 'ordervox' ),
						'value' => $phone,
					),
					array(
						'name'  => __( 'Unsubscribed', 'ordervox' ),
						'value' => $this->optouts->is_opted_out( $phone ) ? __( 'Yes', 'ordervox' ) : __( 'No', 'ordervox' ),
					),
				),
			);

			foreach ( $this->messages->for_phone( $phone ) as $row ) {
				$data[] = array(
					'group_id'    => 'ordervox-messages',
					'group_label' => __( 'WhatsApp messages', 'ordervox' ),
					'item_id'     => 'ordervox-message-' . $row['id'],
					'data'        => array(
						array(
							'name'  => __( 'Sent to', 'ordervox' ),
							'value' => (string) $row['recipient_phone'],
						),
						array(
							'name'  => __( 'Message', 'ordervox' ),
							'value' => (string) $row['event'],
						),
						array(
							'name'  => __( 'Status', 'ordervox' ),
							'value' => (string) $row['status'],
						),
						array(
							'name'  => __( 'Date', 'ordervox' ),
							'value' => (string) $row['created_at'] . ' UTC',
						),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => count( $orders ) < self::PER_PAGE,
		);
	}

	/**
	 * Erases consent details and message history. The number stays on the opt-out list as a
	 * one-way hash, so an earlier STOP keeps being honoured.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, starting at 1.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$orders   = $this->orders( $email, $page );
		$removed  = false;
		$retained = false;

		foreach ( $orders as $order ) {
			$phone = $this->consent->phone( $order );
			if ( null !== $phone && $this->messages->delete_for_phone( $phone ) > 0 ) {
				$removed = true;
			}
			if ( null !== $phone && $this->optouts->is_opted_out( $phone ) ) {
				$retained = true;
			}

			foreach ( array( Consent::ORDER_OPTIN, Consent::ORDER_MARKETING, Consent::ORDER_TIME, Consent::ORDER_TEXT, Consent::ORDER_SOURCE, Consent::ORDER_IP_HASH ) as $key ) {
				if ( '' !== (string) $order->get_meta( $key ) ) {
					$order->delete_meta_data( $key );
					$removed = true;
				}
			}
			$order->save();
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			delete_user_meta( $user->ID, Consent::USER_OPTIN );
			delete_user_meta( $user->ID, Consent::USER_MARKETING );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'The phone number stays on the WhatsApp opt-out list (as a one-way hash) so the customer is not messaged again.', 'ordervox' ) ) : array(),
			'done'           => count( $orders ) < self::PER_PAGE,
		);
	}

	/**
	 * Suggests text for the site's privacy policy.
	 */
	public function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p class="privacy-policy-tutorial">' . esc_html__( 'Ordervox sends WhatsApp messages through the WhatsApp Cloud API, provided by Meta Platforms, Inc. Adjust the retention period to match your Ordervox log settings.', 'ordervox' ) . '</p>'
			. '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'ordervox' ) . ' </strong>'
			. esc_html__( 'If you ask to receive order updates on WhatsApp at checkout, we send your phone number, name, order details and delivery address to Meta’s WhatsApp Cloud API to deliver those messages. We keep a record of your consent, and a log of the messages we send for 30 days. You can stop these messages at any time by replying STOP or by changing your preference in your account.', 'ordervox' )
			. '</p>';
		wp_add_privacy_policy_content( 'Ordervox', wp_kses_post( $text ) );
	}

	/**
	 * One page of the customer's orders.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, starting at 1.
	 * @return \WC_Order[]
	 */
	private function orders( string $email, int $page ): array {
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'type'          => 'shop_order',
				'limit'         => self::PER_PAGE,
				'paged'         => max( 1, $page ),
				'orderby'       => 'id',
				'order'         => 'ASC',
			)
		);
		return array_values( array_filter( (array) $orders, static fn( $order ) => $order instanceof \WC_Order ) );
	}
}

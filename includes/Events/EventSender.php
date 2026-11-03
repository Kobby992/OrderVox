<?php
/**
 * Turns a store event into WhatsApp messages.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Events;

use Webbcode\Ordervox\Customer\Consent;
use Webbcode\Ordervox\Log\MessageRepository;
use Webbcode\Ordervox\Messaging\Dispatcher;
use Webbcode\Ordervox\Messaging\OutboundMessage;
use Webbcode\Ordervox\Placeholders\Registry;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Templates\Compiler;
use Webbcode\Ordervox\Templates\Defaults;
use Webbcode\Ordervox\Templates\Language;
use Webbcode\Ordervox\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the alert is on, finds recipients and the approved template, fills the variables and dispatches.
 */
final class EventSender {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Stored templates.
	 *
	 * @var TemplateRepository
	 */
	private TemplateRepository $templates;

	/**
	 * Placeholders.
	 *
	 * @var Registry
	 */
	private Registry $placeholders;

	/**
	 * Dispatcher.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Customer consent.
	 *
	 * @var Consent|null
	 */
	private ?Consent $consent;

	/**
	 * Constructor.
	 *
	 * @param Settings           $settings     Settings.
	 * @param TemplateRepository $templates    Stored templates.
	 * @param Registry           $placeholders Placeholders.
	 * @param Dispatcher         $dispatcher   Dispatcher.
	 * @param MessageRepository  $messages     Message log.
	 * @param Consent|null       $consent      Customer consent; without it customer alerts go nowhere.
	 */
	public function __construct( Settings $settings, TemplateRepository $templates, Registry $placeholders, Dispatcher $dispatcher, MessageRepository $messages, ?Consent $consent = null ) {
		$this->settings     = $settings;
		$this->templates    = $templates;
		$this->placeholders = $placeholders;
		$this->dispatcher   = $dispatcher;
		$this->messages     = $messages;
		$this->consent      = $consent;
	}

	/**
	 * Whether an alert is switched on.
	 *
	 * @param string $alert Alert key.
	 */
	public function is_enabled( string $alert ): bool {
		return (bool) $this->settings->get( Defaults::setting( $alert ), false );
	}

	/**
	 * Sends an alert to every recipient.
	 *
	 * @param string  $alert       Alert key.
	 * @param Context $context     Event data.
	 * @param string  $object_type order, product, review or ''.
	 * @param int     $object_id   Related object id.
	 * @param string  $dedupe      Extra text that makes this occurrence unique (e.g. a status or date).
	 * @return int Number of messages queued.
	 */
	public function send( string $alert, Context $context, string $object_type = '', int $object_id = 0, string $dedupe = '' ): int {
		if ( ! $this->is_enabled( $alert ) ) {
			return 0;
		}

		$language = Language::current( $this->settings );
		$template = $this->templates->active( $alert, $language );
		$queued   = 0;

		foreach ( $this->recipients( $alert, $context ) as $recipient ) {
			$key = implode( ':', array( $alert, $object_type, $object_id, $dedupe, $recipient['phone'] ) );

			if ( null === $template ) {
				// Record the miss so the store owner can see why no message arrived.
				$this->messages->insert(
					array(
						'idempotency_key' => substr( $key, 0, 191 ),
						'event'           => $alert,
						'object_type'     => $object_type,
						'object_id'       => $object_id,
						'recipient_type'  => $recipient['type'],
						'recipient_phone' => $recipient['phone'],
						'status'          => 'failed',
						'error_message'   => __( 'This alert’s WhatsApp template is not approved yet. Check its status under Ordervox → Alerts.', 'ordervox' ),
					)
				);
				continue;
			}

			$id = $this->dispatcher->dispatch(
				new OutboundMessage(
					$alert,
					$recipient['phone'],
					(string) $template['name'],
					(string) $template['language'],
					$this->components( (array) $template['keys'], $context ),
					$recipient['type'],
					$object_type,
					$object_id,
					$key
				)
			);
			if ( null !== $id ) {
				++$queued;
			}
		}

		return $queued;
	}

	/**
	 * Body parameters for the template's variables, in order.
	 *
	 * @param string[] $keys    Placeholder names behind {{1}}, {{2}}…
	 * @param Context  $context Event data.
	 * @return array<int, array<string, mixed>>
	 */
	public function components( array $keys, Context $context ): array {
		if ( ! $keys ) {
			return array();
		}
		return array(
			array(
				'type'       => 'body',
				'parameters' => array_map(
					fn( string $key ) => array(
						'type' => 'text',
						'text' => Compiler::clean_value( $this->placeholders->resolve( $key, $context ) ),
					),
					array_values( $keys )
				),
			),
		);
	}

	/**
	 * Who receives an alert: the store owner, or for customer alerts the customer if they consented. Pro adds team routing.
	 *
	 * @param string  $alert   Alert key.
	 * @param Context $context Event data.
	 * @return array<int, array{phone: string, type: string}>
	 */
	private function recipients( string $alert, Context $context ): array {
		if ( Defaults::is_customer( $alert ) ) {
			$phone = null !== $this->consent && null !== $context->order ? $this->consent->recipient( $context->order ) : null;
			$type  = 'customer';
		} else {
			$phone = (string) $this->settings->get( 'owner_phone', '' );
			$type  = 'owner';
		}

		$recipients = null !== $phone && '' !== $phone ? array(
			array(
				'phone' => $phone,
				'type'  => $type,
			),
		) : array();

		/**
		 * Filters who receives an alert.
		 *
		 * @param array<int, array{phone: string, type: string}> $recipients Recipients with E.164 numbers.
		 * @param string                                         $alert      Alert key.
		 * @param Context                                        $context    Event data.
		 */
		$recipients = (array) apply_filters( 'ordervox_recipients', $recipients, $alert, $context );

		return array_values(
			array_filter(
				$recipients,
				static fn( $recipient ) => is_array( $recipient ) && ! empty( $recipient['phone'] ) && ! empty( $recipient['type'] )
			)
		);
	}
}

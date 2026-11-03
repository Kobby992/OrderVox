<?php
/**
 * A message waiting to be sent.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Messaging;

defined( 'ABSPATH' ) || exit;

/**
 * Template message addressed to one recipient, plus the context it was sent for.
 */
final class OutboundMessage {

	/**
	 * Constructor.
	 *
	 * @param string                           $event           Event key, e.g. `order.new`.
	 * @param string                           $recipient_phone Recipient in E.164.
	 * @param string                           $template        Meta template name.
	 * @param string                           $language        Meta template language code.
	 * @param array<int, array<string, mixed>> $components      Template components with parameters.
	 * @param string                           $recipient_type  owner, team, customer or vendor.
	 * @param string                           $object_type     order, product, review, cart or ''.
	 * @param int                              $object_id       Related object id.
	 * @param string                           $idempotency_key Prevents sending the same message twice. Generated when empty.
	 */
	public function __construct(
		public readonly string $event,
		public readonly string $recipient_phone,
		public readonly string $template,
		public readonly string $language,
		public readonly array $components = array(),
		public readonly string $recipient_type = 'owner',
		public readonly string $object_type = '',
		public readonly int $object_id = 0,
		public readonly string $idempotency_key = ''
	) {}

	/**
	 * The idempotency key, derived from the message content when none was given.
	 */
	public function key(): string {
		if ( '' !== $this->idempotency_key ) {
			return $this->idempotency_key;
		}
		return implode(
			':',
			array(
				$this->event,
				$this->object_type,
				$this->object_id,
				$this->recipient_phone,
				md5( (string) wp_json_encode( array( $this->template, $this->language, $this->components ) ) ),
			)
		);
	}
}

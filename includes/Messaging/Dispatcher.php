<?php
/**
 * Entry point for sending messages.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Messaging;

use Webbcode\Ordervox\Log\MessageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Logs each message once and hands it to the background queue.
 */
final class Dispatcher {

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Background queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param MessageRepository $messages Message log.
	 * @param Queue             $queue    Background queue.
	 */
	public function __construct( MessageRepository $messages, Queue $queue ) {
		$this->messages = $messages;
		$this->queue    = $queue;
	}

	/**
	 * Logs and enqueues a message. Returns the log id, or null when it was vetoed or already sent.
	 *
	 * @param OutboundMessage $message Message to send.
	 * @param bool            $enqueue False to leave sending to the caller (e.g. a synchronous test message).
	 */
	public function dispatch( OutboundMessage $message, bool $enqueue = true ): ?int {
		/**
		 * Filters whether a message should be sent at all.
		 *
		 * @param bool            $send    Whether to send.
		 * @param OutboundMessage $message The message.
		 */
		if ( ! apply_filters( 'ordervox_should_send', true, $message ) ) {
			return null;
		}

		$id = $this->messages->insert(
			array(
				'idempotency_key'   => substr( $message->key(), 0, 191 ),
				'event'             => $message->event,
				'object_type'       => $message->object_type,
				'object_id'         => $message->object_id,
				'recipient_type'    => $message->recipient_type,
				'recipient_phone'   => $message->recipient_phone,
				'template_name'     => $message->template,
				'template_language' => $message->language,
				'payload'           => (string) wp_json_encode( array( 'components' => $message->components ) ),
			)
		);

		if ( 0 === $id ) {
			return null;
		}

		if ( $enqueue ) {
			$this->queue->enqueue( $id );
		}

		return $id;
	}
}

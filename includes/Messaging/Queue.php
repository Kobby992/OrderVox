<?php
/**
 * Background sending with retries.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Messaging;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Log\MessageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Sends logged messages through Action Scheduler, retrying temporary failures with backoff.
 */
final class Queue {

	public const HOOK  = 'ordervox_send_message';
	public const GROUP = 'ordervox';

	/**
	 * Seconds to wait before each retry. The message fails after the last one.
	 */
	public const BACKOFF = array( 60, 300, 1800, 7200, 43200 );

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Cloud API.
	 *
	 * @var WhatsAppApi
	 */
	private WhatsAppApi $api;

	/**
	 * Constructor.
	 *
	 * @param MessageRepository $messages Message log.
	 * @param WhatsAppApi       $api      Cloud API.
	 */
	public function __construct( MessageRepository $messages, WhatsAppApi $api ) {
		$this->messages = $messages;
		$this->api      = $api;
	}

	/**
	 * Registers the Action Scheduler callback.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'process' ) );
	}

	/**
	 * Schedules a send.
	 *
	 * @param int $log_id Message log id.
	 * @param int $delay  Seconds to wait; 0 sends as soon as possible.
	 */
	public function enqueue( int $log_id, int $delay = 0 ): void {
		$args = array( 'log_id' => $log_id );
		if ( $delay > 0 ) {
			as_schedule_single_action( time() + $delay, self::HOOK, $args, self::GROUP );
		} else {
			as_enqueue_async_action( self::HOOK, $args, self::GROUP );
		}
	}

	/**
	 * Sends one logged message. Returns the updated row.
	 *
	 * @param int $log_id Message log id.
	 * @return array<string, mixed>|null
	 */
	public function process( $log_id ): ?array {
		$log_id = (int) $log_id;
		$row    = $this->messages->find( $log_id );
		if ( null === $row || 'queued' !== $row['status'] ) {
			return $row;
		}

		$attempts = (int) $row['attempts'] + 1;
		$this->messages->update(
			$log_id,
			array(
				'status'   => 'sending',
				'attempts' => $attempts,
			)
		);

		$payload    = json_decode( (string) $row['payload'], true );
		$components = is_array( $payload['components'] ?? null ) ? $payload['components'] : array();

		/**
		 * Filters template components right before sending.
		 *
		 * @param array<int, array<string, mixed>> $components Template components.
		 * @param array<string, mixed>             $row        Message log row.
		 */
		$components = (array) apply_filters( 'ordervox_message_payload', $components, $row );

		/**
		 * Fires before a message is sent.
		 *
		 * @param array<string, mixed> $row Message log row.
		 */
		do_action( 'ordervox_before_send', $row );

		try {
			$result = $this->api->send_template(
				(string) $row['recipient_phone'],
				(string) $row['template_name'],
				(string) $row['template_language'],
				$components
			);

			$this->messages->update(
				$log_id,
				array(
					'status'        => 'sent',
					'wamid'         => $result['wamid'],
					'sent_at'       => MessageRepository::now(),
					'error_code'    => null,
					'error_message' => null,
				)
			);
		} catch ( ApiException $e ) {
			$retry = $e->is_retryable() && $attempts <= count( self::BACKOFF );

			$this->messages->update(
				$log_id,
				array(
					'status'        => $retry ? 'queued' : 'failed',
					'error_code'    => (string) $e->meta_code(),
					'error_message' => $e->hint(),
				)
			);

			if ( $retry ) {
				$this->enqueue( $log_id, self::BACKOFF[ $attempts - 1 ] );
			}
		}

		$row = $this->messages->find( $log_id );

		/**
		 * Fires after a send attempt, whether it succeeded, failed, or will be retried.
		 *
		 * @param array<string, mixed>|null $row Updated message log row.
		 */
		do_action( 'ordervox_after_send', $row );

		return $row;
	}
}

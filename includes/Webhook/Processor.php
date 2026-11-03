<?php
/**
 * Applies webhook notifications from Meta.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Webhook;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Log\MessageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Routes each change in a webhook payload: delivery statuses, inbound messages, template and quality updates.
 */
final class Processor {

	public const LAST_RECEIVED_OPTION = 'ordervox_webhook_last_at';
	public const WINDOW_TRANSIENT     = 'ordervox_window_';

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Records ids already handled, so Meta's redeliveries are ignored. Returns false for duplicates.
	 *
	 * @var callable(string, string): bool
	 */
	private $remember;

	/**
	 * Constructor.
	 *
	 * @param MessageRepository                     $messages Message log.
	 * @param (callable(string, string): bool)|null $remember Dedupe store; defaults to the webhook_events table.
	 */
	public function __construct( MessageRepository $messages, ?callable $remember = null ) {
		$this->messages = $messages;
		$this->remember = $remember ?? array( self::class, 'remember_event' );
	}

	/**
	 * Processes a decoded webhook body.
	 *
	 * @param array<string, mixed> $payload Decoded JSON.
	 */
	public function process( array $payload ): void {
		update_option( self::LAST_RECEIVED_OPTION, time(), false );

		foreach ( (array) ( $payload['entry'] ?? array() ) as $entry ) {
			foreach ( (array) ( $entry['changes'] ?? array() ) as $change ) {
				$field = (string) ( $change['field'] ?? '' );
				$value = is_array( $change['value'] ?? null ) ? $change['value'] : array();

				switch ( $field ) {
					case 'messages':
						foreach ( (array) ( $value['statuses'] ?? array() ) as $status ) {
							$this->handle_status( (array) $status );
						}
						foreach ( (array) ( $value['messages'] ?? array() ) as $message ) {
							$this->handle_inbound( (array) $message, $value );
						}
						break;

					case 'message_template_status_update':
						delete_transient( 'ordervox_templates' );
						/**
						 * Fires when Meta approves, rejects, pauses or disables a template.
						 *
						 * @param array<string, mixed> $value Update details (event, message_template_name, reason…).
						 */
						do_action( 'ordervox_template_status_update', $value );
						break;

					case 'phone_number_quality_update':
						update_option( 'ordervox_quality_update', $value + array( 'received_at' => time() ), false );
						/**
						 * Fires when the business number's quality rating or messaging limit changes.
						 *
						 * @param array<string, mixed> $value Update details.
						 */
						do_action( 'ordervox_phone_quality_update', $value );
						break;
				}
			}
		}
	}

	/**
	 * Updates a log row from a delivery status. Statuses only move forward; a late `delivered` never
	 * overwrites `read`.
	 *
	 * @param array<string, mixed> $status Status object from Meta.
	 */
	private function handle_status( array $status ): void {
		$wamid = (string) ( $status['id'] ?? '' );
		$state = (string) ( $status['status'] ?? '' );
		if ( '' === $wamid || '' === $state || ! ( $this->remember )( 's:' . $wamid . ':' . $state, 'status' ) ) {
			return;
		}

		$row = $this->messages->find_by_wamid( $wamid );
		if ( null === $row ) {
			return;
		}

		$time    = gmdate( 'Y-m-d H:i:s', (int) ( $status['timestamp'] ?? time() ) );
		$current = (string) $row['status'];
		$fields  = array();

		if ( 'failed' === $state ) {
			$error                   = (array) ( $status['errors'][0] ?? array() );
			$code                    = (int) ( $error['code'] ?? 0 );
			$fields['status']        = 'failed';
			$fields['error_code']    = (string) $code;
			$fields['error_message'] = ApiException::hint_for( $code, (string) ( $error['error_data']['details'] ?? $error['title'] ?? 'Delivery failed.' ) );
		} elseif ( isset( MessageRepository::STATUS_RANK[ $state ] ) ) {
			if ( 'failed' !== $current && MessageRepository::STATUS_RANK[ $state ] > ( MessageRepository::STATUS_RANK[ $current ] ?? -1 ) ) {
				$fields['status'] = $state;
			}
			if ( 'delivered' === $state || 'read' === $state ) {
				$fields[ $state . '_at' ] = $time;
			}
			if ( 'read' === $state && empty( $row['delivered_at'] ) ) {
				$fields['delivered_at'] = $time;
			}
		}

		if ( ! empty( $status['pricing']['category'] ) ) {
			$fields['pricing_category'] = substr( (string) $status['pricing']['category'], 0, 32 );
		}

		if ( ! $fields ) {
			return;
		}

		$this->messages->update( (int) $row['id'], $fields );

		if ( isset( $fields['status'] ) ) {
			/**
			 * Fires when a message's delivery status changes.
			 *
			 * @param array<string, mixed> $row    Row before the change.
			 * @param string               $status New status.
			 * @param array<string, mixed> $raw    Status object from Meta.
			 */
			do_action( 'ordervox_message_status_changed', $row, $fields['status'], $status );
		}
	}

	/**
	 * Records the 24-hour window for the sender and passes the message on to add-ons.
	 *
	 * @param array<string, mixed> $message Message object from Meta.
	 * @param array<string, mixed> $value   The whole change value (metadata, contacts).
	 */
	private function handle_inbound( array $message, array $value ): void {
		$id   = (string) ( $message['id'] ?? '' );
		$from = (string) ( $message['from'] ?? '' );
		if ( '' === $id || '' === $from || ! ( $this->remember )( 'm:' . $id, 'message' ) ) {
			return;
		}

		set_transient( self::WINDOW_TRANSIENT . md5( '+' . ltrim( $from, '+' ) ), time(), DAY_IN_SECONDS );

		/**
		 * Fires for every inbound WhatsApp message.
		 *
		 * @param array<string, mixed> $message Message object from Meta.
		 * @param array<string, mixed> $value   Change value with metadata and contacts.
		 */
		do_action( 'ordervox_inbound_message', $message, $value );

		$payload = (string) ( $message['button']['payload'] ?? $message['interactive']['button_reply']['id'] ?? '' );
		if ( '' !== $payload ) {
			/**
			 * Fires when someone taps a quick-reply or interactive reply button.
			 *
			 * @param string               $payload Button payload or reply id.
			 * @param string               $from    Sender number without `+`.
			 * @param array<string, mixed> $message Message object from Meta.
			 */
			do_action( 'ordervox_button_reply', $payload, $from, $message );
		}
	}

	/**
	 * Whether a 24-hour customer service window is open with this number.
	 *
	 * @param string $e164 Number in E.164.
	 */
	public static function window_open( string $e164 ): bool {
		$opened = (int) get_transient( self::WINDOW_TRANSIENT . md5( $e164 ) );
		return $opened > 0 && time() - $opened < DAY_IN_SECONDS;
	}

	/**
	 * Stores a handled event id. Returns false when it was already stored.
	 *
	 * @param string $key  Unique id for the event.
	 * @param string $type Event type.
	 */
	public static function remember_event( string $key, string $type ): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom dedupe table; the unique index rejects repeats.
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'ordervox_webhook_events',
			array(
				'meta_id'     => substr( $key, 0, 191 ),
				'type'        => $type,
				'received_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$wpdb->suppress_errors( $suppress );
		return (bool) $inserted;
	}

	/**
	 * Deletes dedupe records older than two days.
	 */
	public static function purge_events(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Housekeeping on our own table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE received_at < %s LIMIT 1000', $wpdb->prefix . 'ordervox_webhook_events', gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ) ) );
	}
}

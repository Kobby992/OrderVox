<?php
/**
 * STOP and START replies.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Customer;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Log\MessageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles opt-out and opt-in keywords sent to the business number. STOP takes effect at once:
 * the number joins the opt-out list and anything still queued for it is cancelled.
 */
final class Keywords {

	/**
	 * Words that unsubscribe (compared in upper case, without punctuation).
	 */
	private const STOP = array( 'STOP', 'STOPALL', 'UNSUBSCRIBE', 'OPTOUT', 'OPT OUT', 'ARRET', 'ARRÊT', 'PARAR', 'BAJA' );

	/**
	 * Words that subscribe again.
	 */
	private const START = array( 'START', 'SUBSCRIBE', 'UNSTOP', 'OPTIN', 'OPT IN' );

	/**
	 * Opt-out list.
	 *
	 * @var OptOuts
	 */
	private OptOuts $optouts;

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Cloud API, for the confirmation reply.
	 *
	 * @var WhatsAppApi
	 */
	private WhatsAppApi $api;

	/**
	 * Constructor.
	 *
	 * @param OptOuts           $optouts  Opt-out list.
	 * @param MessageRepository $messages Message log.
	 * @param WhatsAppApi       $api      Cloud API.
	 */
	public function __construct( OptOuts $optouts, MessageRepository $messages, WhatsAppApi $api ) {
		$this->optouts  = $optouts;
		$this->messages = $messages;
		$this->api      = $api;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'ordervox_inbound_message', array( $this, 'handle' ) );
	}

	/**
	 * Acts on a keyword in an inbound text or button message.
	 *
	 * @param array<string, mixed> $message Message object from Meta.
	 * @return string|null `stop`, `start`, or null when the message is not a keyword.
	 */
	public function handle( array $message ): ?string {
		$text  = (string) ( $message['text']['body'] ?? $message['button']['text'] ?? '' );
		$from  = '+' . ltrim( (string) ( $message['from'] ?? '' ), '+' );
		$words = self::normalise( $text );

		if ( '+' === $from || '' === $words ) {
			return null;
		}

		if ( in_array( $words, self::STOP, true ) ) {
			$this->optouts->add( $from, 'keyword' );
			$this->messages->cancel_queued_for( $from );
			$this->reply(
				$from,
				sprintf(
					/* translators: %s: store name. */
					__( 'You will no longer receive WhatsApp messages from %s. Reply START to subscribe again.', 'ordervox' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
				)
			);
			/**
			 * Fires when a customer unsubscribes by keyword.
			 *
			 * @param string $from Number in E.164.
			 */
			do_action( 'ordervox_customer_opted_out', $from );
			return 'stop';
		}

		if ( in_array( $words, self::START, true ) ) {
			$this->optouts->remove( $from );
			$this->reply(
				$from,
				sprintf(
					/* translators: %s: store name. */
					__( 'You are subscribed to WhatsApp messages from %s again. Reply STOP at any time to unsubscribe.', 'ordervox' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
				)
			);
			/**
			 * Fires when a customer subscribes again by keyword.
			 *
			 * @param string $from Number in E.164.
			 */
			do_action( 'ordervox_customer_opted_in', $from );
			return 'start';
		}

		return null;
	}

	/**
	 * Upper case, without punctuation or extra spaces, e.g. " stop! " → "STOP".
	 *
	 * @param string $text Message text.
	 */
	public static function normalise( string $text ): string {
		$text = mb_strtoupper( trim( $text ) );
		$text = (string) preg_replace( '/[^\p{L}\p{N} ]+/u', '', $text );
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Sends a free-form confirmation. Allowed because the customer just messaged us.
	 *
	 * @param string $to   Number in E.164.
	 * @param string $text Reply text.
	 */
	private function reply( string $to, string $text ): void {
		try {
			$this->api->send_text( $to, $text );
		} catch ( ApiException $e ) {
			// The keyword is honoured even if the confirmation cannot be sent.
			unset( $e );
		}
	}
}

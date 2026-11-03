<?php
/**
 * Connection health check.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Health;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Log\MessageRepository;
use Webbcode\Ordervox\Settings\Credentials;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Webhook\Processor;

defined( 'ABSPATH' ) || exit;

/**
 * Checks token, phone number, webhook and queue, and stores the result for the dashboard and notices.
 */
final class HealthCheck {

	public const OPTION = 'ordervox_health';

	/**
	 * Permissions a System User token needs.
	 */
	private const REQUIRED_SCOPES = array( 'whatsapp_business_messaging', 'whatsapp_business_management' );

	/**
	 * Saved credentials.
	 *
	 * @var Credentials
	 */
	private Credentials $credentials;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Cloud API.
	 *
	 * @var WhatsAppApi
	 */
	private WhatsAppApi $api;

	/**
	 * Message log.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Constructor.
	 *
	 * @param Credentials       $credentials Saved credentials.
	 * @param Settings          $settings    Settings.
	 * @param WhatsAppApi       $api         Cloud API.
	 * @param MessageRepository $messages    Message log.
	 */
	public function __construct( Credentials $credentials, Settings $settings, WhatsAppApi $api, MessageRepository $messages ) {
		$this->credentials = $credentials;
		$this->settings    = $settings;
		$this->api         = $api;
		$this->messages    = $messages;
	}

	/**
	 * Runs every check and stores the result.
	 *
	 * @return array<string, mixed>
	 */
	public function run(): array {
		$checks = array();
		$phone  = null;

		if ( ! $this->credentials->is_configured() ) {
			$checks['credentials'] = self::check( 'error', __( 'Connect your WhatsApp Business number to start sending messages.', 'ordervox' ) );
		} else {
			$checks['credentials'] = self::check( 'ok', __( 'Credentials saved.', 'ordervox' ) );
			$checks['token']       = $this->check_token();

			try {
				$phone           = $this->api->phone_number_info();
				$checks['phone'] = $this->check_phone( $phone );
			} catch ( ApiException $e ) {
				$checks['phone'] = self::check( 'error', $e->hint() );
			}
		}

		$checks['owner_phone'] = '' === (string) $this->settings->get( 'owner_phone' )
			? self::check( 'warning', __( 'Add the WhatsApp number that should receive store alerts.', 'ordervox' ) )
			: self::check( 'ok', __( 'Alert number saved.', 'ordervox' ) );

		$checks['webhook'] = $this->check_webhook();
		$checks['queue']   = $this->check_queue();

		$result = array(
			'status'     => self::overall( $checks ),
			'checks'     => $checks,
			'phone'      => $phone,
			'checked_at' => time(),
		);

		update_option( self::OPTION, $result, false );
		return $result;
	}

	/**
	 * Last stored result, or null if never run.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function last(): ?array {
		$last = get_option( self::OPTION );
		return is_array( $last ) ? $last : null;
	}

	/**
	 * Token validity, expiry and permissions, when App ID and App Secret are saved.
	 *
	 * @return array{status: string, message: string}
	 */
	private function check_token(): array {
		try {
			$data = $this->api->debug_token();
		} catch ( ApiException $e ) {
			return self::check( 'warning', $e->hint() );
		}

		if ( empty( $data['is_valid'] ) ) {
			return self::check( 'error', ApiException::hint_for( 190, '' ) );
		}

		$missing = array_diff( self::REQUIRED_SCOPES, (array) ( $data['scopes'] ?? array() ) );
		if ( $missing ) {
			return self::check(
				'error',
				/* translators: %s: comma-separated permission names. */
				sprintf( __( 'The access token is missing these permissions: %s.', 'ordervox' ), implode( ', ', $missing ) )
			);
		}

		$expires = (int) ( $data['expires_at'] ?? 0 );
		if ( $expires > 0 ) {
			return self::check(
				$expires - time() < 7 * DAY_IN_SECONDS ? 'error' : 'warning',
				sprintf(
					/* translators: %s: date the token expires. */
					__( 'This access token expires on %s. Use a permanent System User token so messages keep sending.', 'ordervox' ),
					wp_date( get_option( 'date_format' ), $expires )
				)
			);
		}

		return self::check( 'ok', __( 'Permanent access token with the right permissions.', 'ordervox' ) );
	}

	/**
	 * Phone number quality rating.
	 *
	 * @param array<string, mixed> $phone Phone number info from Meta.
	 * @return array{status: string, message: string}
	 */
	private function check_phone( array $phone ): array {
		$rating = strtoupper( (string) ( $phone['quality_rating'] ?? '' ) );
		$name   = (string) ( $phone['verified_name'] ?? '' );
		$number = (string) ( $phone['display_phone_number'] ?? '' );

		if ( 'RED' === $rating ) {
			return self::check( 'error', __( 'Your number has a low quality rating. Meta may limit how many messages you can send.', 'ordervox' ) );
		}
		if ( 'YELLOW' === $rating ) {
			return self::check( 'warning', __( 'Your number has a medium quality rating. Watch for customers blocking or reporting messages.', 'ordervox' ) );
		}

		/* translators: 1: business display name, 2: phone number. */
		return self::check( 'ok', sprintf( __( 'Connected as %1$s (%2$s).', 'ordervox' ), $name, $number ) );
	}

	/**
	 * Whether Meta has reached the webhook.
	 *
	 * @return array{status: string, message: string}
	 */
	private function check_webhook(): array {
		$last = (int) get_option( Processor::LAST_RECEIVED_OPTION, 0 );
		if ( 0 === $last ) {
			return self::check( 'warning', __( 'No webhook calls received yet. Add the callback URL and verify token in your Meta app so delivery statuses appear.', 'ordervox' ) );
		}
		return self::check(
			'ok',
			sprintf(
				/* translators: %s: human time difference, e.g. "5 mins". */
				__( 'Last webhook call received %s ago.', 'ordervox' ),
				human_time_diff( $last )
			)
		);
	}

	/**
	 * Queue backlog and the latest failure.
	 *
	 * @return array{status: string, message: string}
	 */
	private function check_queue(): array {
		$counts  = $this->messages->count_by_status();
		$backlog = ( $counts['queued'] ?? 0 ) + ( $counts['sending'] ?? 0 );
		$failed  = $this->messages->latest_failed();

		if ( null !== $failed && strtotime( (string) $failed['updated_at'] . ' UTC' ) > time() - DAY_IN_SECONDS ) {
			return self::check(
				'warning',
				/* translators: %s: error explanation. */
				sprintf( __( 'A message failed in the last 24 hours: %s', 'ordervox' ), (string) $failed['error_message'] )
			);
		}
		if ( $backlog > 50 ) {
			/* translators: %d: number of messages waiting. */
			return self::check( 'warning', sprintf( __( '%d messages are waiting to be sent. Check that WP-Cron or Action Scheduler is running.', 'ordervox' ), $backlog ) );
		}
		return self::check( 'ok', __( 'Message queue is healthy.', 'ordervox' ) );
	}

	/**
	 * Worst status across checks.
	 *
	 * @param array<string, array{status: string, message: string}> $checks Checks.
	 */
	private static function overall( array $checks ): string {
		$statuses = array_column( $checks, 'status' );
		if ( in_array( 'error', $statuses, true ) ) {
			return 'error';
		}
		return in_array( 'warning', $statuses, true ) ? 'warning' : 'ok';
	}

	/**
	 * One check result.
	 *
	 * @param string $status  ok, warning or error.
	 * @param string $message Explanation.
	 * @return array{status: string, message: string}
	 */
	private static function check( string $status, string $message ): array {
		return array(
			'status'  => $status,
			'message' => $message,
		);
	}
}

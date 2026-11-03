<?php
/**
 * WhatsApp Cloud API operations used by Ordervox.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Api;

use Webbcode\Ordervox\Settings\Credentials;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the Cloud API endpoints listed in spec §6.2.
 */
final class WhatsAppApi {

	/**
	 * Saved credentials.
	 *
	 * @var Credentials
	 */
	private Credentials $credentials;

	/**
	 * Builds the HTTP client for a token. Swappable in tests.
	 *
	 * @var callable(string): GraphClient
	 */
	private $client_factory;

	/**
	 * Constructor.
	 *
	 * @param Credentials                        $credentials    Saved credentials.
	 * @param (callable(string): GraphClient)|null $client_factory Optional client factory.
	 */
	public function __construct( Credentials $credentials, ?callable $client_factory = null ) {
		$this->credentials    = $credentials;
		$this->client_factory = $client_factory ?? static fn( string $token ) => new GraphClient( $token );
	}

	/**
	 * Sends a template message.
	 *
	 * @param string                           $to         Recipient number in E.164.
	 * @param string                           $template   Template name.
	 * @param string                           $language   Template language code, e.g. `en_US`.
	 * @param array<int, array<string, mixed>> $components Template components (header/body/button parameters).
	 * @return array{wamid: string, wa_id: string}
	 */
	public function send_template( string $to, string $template, string $language, array $components = array() ): array {
		$payload = array(
			'name'     => $template,
			'language' => array( 'code' => $language ),
		);
		if ( $components ) {
			$payload['components'] = $components;
		}

		return $this->send(
			array(
				'to'       => ltrim( $to, '+' ),
				'type'     => 'template',
				'template' => $payload,
			)
		);
	}

	/**
	 * Sends a free-form text message. Only allowed inside a 24-hour customer service window.
	 *
	 * @param string $to   Recipient number in E.164.
	 * @param string $text Message text.
	 * @return array{wamid: string, wa_id: string}
	 */
	public function send_text( string $to, string $text ): array {
		return $this->send(
			array(
				'to'   => ltrim( $to, '+' ),
				'type' => 'text',
				'text' => array(
					'preview_url' => true,
					'body'        => $text,
				),
			)
		);
	}

	/**
	 * Sends any message payload. `messaging_product` and `recipient_type` are added.
	 *
	 * @param array<string, mixed> $message Message fields.
	 * @return array{wamid: string, wa_id: string}
	 * @throws ApiException When Meta returns no message id.
	 */
	public function send( array $message ): array {
		$response = $this->client()->post(
			$this->phone_number_id() . '/messages',
			array_merge(
				array(
					'messaging_product' => 'whatsapp',
					'recipient_type'    => 'individual',
				),
				$message
			)
		);

		$wamid = (string) ( $response['messages'][0]['id'] ?? '' );
		if ( '' === $wamid ) {
			throw new ApiException( 'WhatsApp did not return a message ID.' );
		}

		return array(
			'wamid' => $wamid,
			'wa_id' => (string) ( $response['contacts'][0]['wa_id'] ?? '' ),
		);
	}

	/**
	 * Details of the business phone number.
	 *
	 * @return array<string, mixed>
	 */
	public function phone_number_info(): array {
		/**
		 * Filters the phone number fields requested for the connection health check.
		 *
		 * @param string $fields Comma-separated Graph fields.
		 */
		$fields = (string) apply_filters( 'ordervox_phone_number_fields', 'display_phone_number,verified_name,quality_rating,code_verification_status,name_status' );
		return $this->client()->get( $this->phone_number_id(), array( 'fields' => $fields ) );
	}

	/**
	 * Message templates in the WhatsApp Business Account.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function templates(): array {
		$templates = array();
		$after     = '';

		// Follow cursors, capped so a huge account cannot stall the request.
		for ( $page = 0; $page < 10; $page++ ) {
			$query = array(
				'fields' => 'id,name,status,language,category,components,rejected_reason',
				'limit'  => 100,
			);
			if ( '' !== $after ) {
				$query['after'] = $after;
			}

			$response  = $this->client()->get( $this->waba_id() . '/message_templates', $query );
			$templates = array_merge( $templates, is_array( $response['data'] ?? null ) ? $response['data'] : array() );
			$after     = (string) ( $response['paging']['cursors']['after'] ?? '' );

			if ( '' === $after || empty( $response['paging']['next'] ) ) {
				break;
			}
		}

		return $templates;
	}

	/**
	 * Creates a message template in the WhatsApp Business Account.
	 *
	 * @param array<string, mixed> $template Template definition (name, language, category, components).
	 * @return array<string, mixed>
	 */
	public function create_template( array $template ): array {
		return $this->client()->post( $this->waba_id() . '/message_templates', $template );
	}

	/**
	 * Subscribes this app to webhooks for the WhatsApp Business Account.
	 */
	public function subscribe_app(): bool {
		$response = $this->client()->post( $this->waba_id() . '/subscribed_apps' );
		return ! empty( $response['success'] );
	}

	/**
	 * Inspects the access token with the app token (App ID|App Secret).
	 *
	 * @return array<string, mixed> Token data: is_valid, expires_at (0 = never), scopes, type.
	 * @throws ApiException When App ID or App Secret are missing.
	 */
	public function debug_token(): array {
		$app_id     = (string) $this->credentials->get( 'app_id' );
		$app_secret = (string) $this->credentials->get( 'app_secret' );
		if ( '' === $app_id || '' === $app_secret ) {
			throw new ApiException( 'Save the App ID and App Secret to check the access token.' );
		}

		$response = ( $this->client_factory )( $app_id . '|' . $app_secret )->get(
			'debug_token',
			array( 'input_token' => (string) $this->credentials->get( 'access_token' ) )
		);

		return is_array( $response['data'] ?? null ) ? $response['data'] : array();
	}

	/**
	 * HTTP client for the saved access token.
	 *
	 * @throws ApiException When the token cannot be decrypted.
	 */
	private function client(): GraphClient {
		$token = $this->credentials->get( 'access_token' );
		if ( null === $token ) {
			throw new ApiException( 'The saved access token cannot be read. The site security keys may have changed; enter the token again.', 190 );
		}
		return ( $this->client_factory )( $token );
	}

	/**
	 * Saved Phone Number ID.
	 *
	 * @throws ApiException When missing.
	 */
	private function phone_number_id(): string {
		$id = (string) $this->credentials->get( 'phone_number_id' );
		if ( '' === $id ) {
			throw new ApiException( 'No Phone Number ID is saved.', 100 );
		}
		return rawurlencode( $id );
	}

	/**
	 * Saved WhatsApp Business Account ID.
	 *
	 * @throws ApiException When missing.
	 */
	private function waba_id(): string {
		$id = (string) $this->credentials->get( 'waba_id' );
		if ( '' === $id ) {
			throw new ApiException( 'No WhatsApp Business Account ID is saved.', 100 );
		}
		return rawurlencode( $id );
	}
}

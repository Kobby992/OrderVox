<?php
/**
 * Public webhook endpoint for Meta.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Webhook;

use Webbcode\Ordervox\Settings\Credentials;

defined( 'ABSPATH' ) || exit;

/**
 * `GET|POST /wp-json/ordervox/v1/webhook`. GET answers Meta's verification handshake;
 * POST accepts signed notifications.
 */
final class Controller {

	public const NAMESPACE = 'ordervox/v1';
	public const ROUTE     = '/webhook';

	private const MAX_BODY_BYTES = 1048576;

	/**
	 * Saved credentials.
	 *
	 * @var Credentials
	 */
	private Credentials $credentials;

	/**
	 * Payload processor.
	 *
	 * @var Processor
	 */
	private Processor $processor;

	/**
	 * Constructor.
	 *
	 * @param Credentials $credentials Saved credentials.
	 * @param Processor   $processor   Payload processor.
	 */
	public function __construct( Credentials $credentials, Processor $processor ) {
		$this->credentials = $credentials;
		$this->processor   = $processor;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_challenge' ), 10, 4 );
	}

	/**
	 * Public URL Meta should call.
	 */
	public static function url(): string {
		return rest_url( self::NAMESPACE . self::ROUTE );
	}

	/**
	 * Registers the route. Authentication is the verify token (GET) or the signature (POST).
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'verify' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'receive' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Verification handshake: echo `hub.challenge` when `hub.verify_token` matches.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify( \WP_REST_Request $request ) {
		// PHP turns the dots in `hub.mode` into underscores; accept both spellings.
		$mode      = (string) ( $request->get_param( 'hub_mode' ) ?? $request->get_param( 'hub.mode' ) ?? '' );
		$token     = (string) ( $request->get_param( 'hub_verify_token' ) ?? $request->get_param( 'hub.verify_token' ) ?? '' );
		$challenge = (string) ( $request->get_param( 'hub_challenge' ) ?? $request->get_param( 'hub.challenge' ) ?? '' );
		$expected  = (string) $this->credentials->get( 'webhook_verify_token' );

		if ( 'subscribe' !== $mode || '' === $expected || ! hash_equals( $expected, $token ) ) {
			return new \WP_Error( 'ordervox_forbidden', 'Verification failed.', array( 'status' => 403 ) );
		}

		update_option( Processor::LAST_RECEIVED_OPTION, time(), false );
		return new \WP_REST_Response( $challenge, 200 );
	}

	/**
	 * Receives a notification. Responds 200 quickly; unsigned or oversized requests get 401/413.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function receive( \WP_REST_Request $request ) {
		$body = $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return new \WP_Error( 'ordervox_too_large', 'Payload too large.', array( 'status' => 413 ) );
		}

		$secret = (string) $this->credentials->get( 'app_secret' );
		if ( ! Signature::is_valid( $body, (string) $request->get_header( 'x-hub-signature-256' ), $secret ) ) {
			return new \WP_Error( 'ordervox_bad_signature', 'Invalid signature.', array( 'status' => 401 ) );
		}

		$payload = json_decode( $body, true );
		if ( is_array( $payload ) && 'whatsapp_business_account' === ( $payload['object'] ?? '' ) ) {
			$this->processor->process( $payload );
		}

		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Sends the verification challenge as plain text instead of JSON.
	 *
	 * @param bool              $served  Whether the request was already served.
	 * @param \WP_HTTP_Response $result  Response.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 */
	public function serve_challenge( $served, $result, $request, $server ): bool {
		if ( $served || 'GET' !== $request->get_method() || self::NAMESPACE . self::ROUTE !== ltrim( $request->get_route(), '/' ) || 200 !== $result->get_status() ) {
			return (bool) $served;
		}
		$server->send_header( 'Content-Type', 'text/plain; charset=utf-8' );
		echo esc_html( (string) $result->get_data() );
		return true;
	}
}

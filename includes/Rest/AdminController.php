<?php
/**
 * REST routes for the admin app.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Rest;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Health\HealthCheck;
use Webbcode\Ordervox\Log\MessageRepository;
use Webbcode\Ordervox\Messaging\Dispatcher;
use Webbcode\Ordervox\Messaging\OutboundMessage;
use Webbcode\Ordervox\Messaging\Queue;
use Webbcode\Ordervox\Settings\Credentials;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Support\Phone;
use Webbcode\Ordervox\Webhook\Controller as WebhookController;

defined( 'ABSPATH' ) || exit;

/**
 * `ordervox/v1` routes used by the setup wizard, dashboard and log (spec §12).
 */
final class AdminController {

	private const NAMESPACE           = 'ordervox/v1';
	private const TEMPLATES_TRANSIENT = 'ordervox_templates';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Credentials.
	 *
	 * @var Credentials
	 */
	private Credentials $credentials;

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
	 * Dispatcher.
	 *
	 * @var Dispatcher
	 */
	private Dispatcher $dispatcher;

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Health check.
	 *
	 * @var HealthCheck
	 */
	private HealthCheck $health;

	/**
	 * Constructor.
	 *
	 * @param Settings          $settings    Settings.
	 * @param Credentials       $credentials Credentials.
	 * @param WhatsAppApi       $api         Cloud API.
	 * @param MessageRepository $messages    Message log.
	 * @param Dispatcher        $dispatcher  Dispatcher.
	 * @param Queue             $queue       Queue.
	 * @param HealthCheck       $health      Health check.
	 */
	public function __construct( Settings $settings, Credentials $credentials, WhatsAppApi $api, MessageRepository $messages, Dispatcher $dispatcher, Queue $queue, HealthCheck $health ) {
		$this->settings    = $settings;
		$this->credentials = $credentials;
		$this->api         = $api;
		$this->messages    = $messages;
		$this->dispatcher  = $dispatcher;
		$this->queue       = $queue;
		$this->health      = $health;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		$routes = array(
			'/settings'                    => array(
				array( 'GET', 'get_settings' ),
				array( 'POST', 'update_settings' ),
			),
			'/connection/verify'           => array( array( 'POST', 'verify_connection' ) ),
			'/connection/subscribe'        => array( array( 'POST', 'subscribe' ) ),
			'/connection/test-message'     => array( array( 'POST', 'send_test_message' ) ),
			'/templates'                   => array( array( 'GET', 'get_templates' ) ),
			'/health'                      => array( array( 'GET', 'get_health' ) ),
			'/messages'                    => array( array( 'GET', 'get_messages' ) ),
			'/messages/(?P<id>\d+)'        => array( array( 'GET', 'get_message' ) ),
			'/messages/(?P<id>\d+)/resend' => array( array( 'POST', 'resend_message' ) ),
		);

		foreach ( $routes as $route => $endpoints ) {
			$args = array();
			foreach ( $endpoints as $endpoint ) {
				$args[] = array(
					'methods'             => $endpoint[0],
					'callback'            => array( $this, $endpoint[1] ),
					'permission_callback' => array( $this, 'can_manage' ),
				);
			}
			register_rest_route( self::NAMESPACE, $route, $args );
		}
	}

	/**
	 * Only shop managers and administrators may use these routes.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Settings, masked credentials and webhook details.
	 */
	public function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'settings'     => $this->settings->all(),
				'credentials'  => $this->credentials->masked(),
				'configured'   => $this->credentials->is_configured(),
				'webhook'      => array(
					'url'           => WebhookController::url(),
					'verify_token'  => (string) $this->credentials->get( 'webhook_verify_token' ),
					'last_received' => (int) get_option( 'ordervox_webhook_last_at', 0 ),
				),
				'calling_code' => Phone::calling_code(),
			)
		);
	}

	/**
	 * Saves settings and credentials. Masked secret values sent back unchanged are ignored.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$settings    = (array) $request->get_param( 'settings' );
		$credentials = (array) $request->get_param( 'credentials' );

		if ( isset( $settings['owner_phone'] ) && '' !== trim( (string) $settings['owner_phone'] ) ) {
			$phone = Phone::to_e164( (string) $settings['owner_phone'], Phone::calling_code() );
			if ( null === $phone ) {
				return new \WP_Error( 'ordervox_invalid_phone', __( 'Enter the alert number in international format, for example +14155550123.', 'ordervox' ), array( 'status' => 400 ) );
			}
			$settings['owner_phone'] = $phone;
		}

		$secrets = array_filter(
			$credentials,
			static function ( $value, $field ) {
				if ( ! array_key_exists( $field, Credentials::FIELDS ) ) {
					return false;
				}
				$value = (string) $value;
				// Secrets come back masked when untouched; only save real new values.
				return ! Credentials::FIELDS[ $field ] || ( '' !== $value && 'unreadable' !== $value && ! str_starts_with( $value, '••••' ) );
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( $settings ) {
			$this->settings->update( $settings );
		}
		if ( $secrets ) {
			$this->credentials->set( array_map( 'strval', $secrets ) );
			delete_transient( self::TEMPLATES_TRANSIENT );
		}

		return $this->get_settings();
	}

	/**
	 * Checks the saved credentials against Meta.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify_connection() {
		try {
			$phone = $this->api->phone_number_info();
		} catch ( ApiException $e ) {
			return self::error( $e );
		}

		$warnings = array();
		try {
			$token = $this->api->debug_token();
			if ( (int) ( $token['expires_at'] ?? 0 ) > 0 ) {
				$warnings[] = __( 'This access token expires. Use a permanent System User token so messages keep sending.', 'ordervox' );
			}
		} catch ( ApiException $e ) {
			$warnings[] = $e->hint();
		}

		return new \WP_REST_Response(
			array(
				'phone'    => $phone,
				'warnings' => $warnings,
			)
		);
	}

	/**
	 * Subscribes the Meta app to this account's webhooks.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function subscribe() {
		try {
			return new \WP_REST_Response( array( 'success' => $this->api->subscribe_app() ) );
		} catch ( ApiException $e ) {
			return self::error( $e );
		}
	}

	/**
	 * Sends a template message straight away and returns its log row.
	 *
	 * @param \WP_REST_Request $request Request with phone (optional, defaults to the alert number), template, language.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_test_message( \WP_REST_Request $request ) {
		$raw   = (string) ( $request->get_param( 'phone' ) ?? '' );
		$raw   = '' !== $raw ? $raw : (string) $this->settings->get( 'owner_phone' );
		$phone = Phone::to_e164( $raw, Phone::calling_code() );
		if ( null === $phone ) {
			return new \WP_Error( 'ordervox_invalid_phone', __( 'Enter a valid WhatsApp number in international format.', 'ordervox' ), array( 'status' => 400 ) );
		}

		$template = sanitize_text_field( (string) ( $request->get_param( 'template' ) ?? 'hello_world' ) );
		$language = sanitize_text_field( (string) ( $request->get_param( 'language' ) ?? 'en_US' ) );

		// Sample values for templates with body variables ({{1}}, {{2}}…).
		$components = array();
		$variables  = array_values( array_filter( array_map( 'sanitize_text_field', (array) $request->get_param( 'variables' ) ), 'strlen' ) );
		if ( $variables ) {
			$components[] = array(
				'type'       => 'body',
				'parameters' => array_map(
					static fn( string $text ) => array(
						'type' => 'text',
						'text' => $text,
					),
					$variables
				),
			);
		}

		$id = $this->dispatcher->dispatch(
			new OutboundMessage(
				'test',
				$phone,
				$template,
				$language,
				$components,
				'owner',
				'',
				0,
				'test:' . wp_generate_uuid4()
			),
			false
		);
		if ( null === $id ) {
			return new \WP_Error( 'ordervox_not_sent', __( 'The test message was blocked by another plugin.', 'ordervox' ), array( 'status' => 409 ) );
		}

		$row = $this->queue->process( $id );
		if ( null !== $row && 'failed' === $row['status'] ) {
			return new \WP_Error( 'ordervox_send_failed', (string) $row['error_message'], array( 'status' => 502 ) );
		}

		return new \WP_REST_Response( self::format_row( (array) $row ) );
	}

	/**
	 * Templates from Meta, cached for ten minutes.
	 *
	 * @param \WP_REST_Request $request Request; `refresh=1` bypasses the cache.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_templates( \WP_REST_Request $request ) {
		$cached = get_transient( self::TEMPLATES_TRANSIENT );
		if ( ! is_array( $cached ) || $request->get_param( 'refresh' ) ) {
			try {
				$cached = array_map( array( self::class, 'format_template' ), $this->api->templates() );
			} catch ( ApiException $e ) {
				return self::error( $e );
			}
			set_transient( self::TEMPLATES_TRANSIENT, $cached, 10 * MINUTE_IN_SECONDS );
		}
		return new \WP_REST_Response( $cached );
	}

	/**
	 * Health status; `refresh=1` runs the checks now.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get_health( \WP_REST_Request $request ): \WP_REST_Response {
		$last = HealthCheck::last();
		if ( null === $last || $request->get_param( 'refresh' ) ) {
			$last = $this->health->run();
		}
		$last['counts'] = $this->messages->count_by_status();
		return new \WP_REST_Response( $last );
	}

	/**
	 * Paged message log.
	 *
	 * @param \WP_REST_Request $request Request with page, per_page, status, event, object_type, object_id.
	 */
	public function get_messages( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->messages->paginate(
			array(
				'status'      => sanitize_key( (string) $request->get_param( 'status' ) ),
				'event'       => sanitize_text_field( (string) $request->get_param( 'event' ) ),
				'object_type' => sanitize_key( (string) $request->get_param( 'object_type' ) ),
				'object_id'   => absint( $request->get_param( 'object_id' ) ),
			),
			max( 1, absint( $request->get_param( 'page' ) ) ),
			absint( $request->get_param( 'per_page' ) ) ? absint( $request->get_param( 'per_page' ) ) : 20
		);

		return new \WP_REST_Response(
			array(
				'items' => array_map( array( self::class, 'format_row' ), $result['items'] ),
				'total' => $result['total'],
			)
		);
	}

	/**
	 * One log row.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_message( \WP_REST_Request $request ) {
		$row = $this->messages->find( (int) $request['id'] );
		if ( null === $row ) {
			return new \WP_Error( 'ordervox_not_found', __( 'Message not found.', 'ordervox' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( self::format_row( $row ) );
	}

	/**
	 * Queues a failed or sent message again.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resend_message( \WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = $this->messages->find( $id );
		if ( null === $row ) {
			return new \WP_Error( 'ordervox_not_found', __( 'Message not found.', 'ordervox' ), array( 'status' => 404 ) );
		}

		$this->messages->update(
			$id,
			array(
				'status'        => 'queued',
				'attempts'      => 0,
				'wamid'         => null,
				'error_code'    => null,
				'error_message' => null,
				'sent_at'       => null,
				'delivered_at'  => null,
				'read_at'       => null,
			)
		);
		$this->queue->enqueue( $id );

		return new \WP_REST_Response( self::format_row( (array) $this->messages->find( $id ) ) );
	}

	/**
	 * Log row for the browser: masked number, decoded payload, ISO dates.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	public static function format_row( array $row ): array {
		$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
		$dates   = array();
		foreach ( array( 'created_at', 'updated_at', 'sent_at', 'delivered_at', 'read_at' ) as $column ) {
			$dates[ $column ] = empty( $row[ $column ] ) ? null : gmdate( 'c', (int) strtotime( $row[ $column ] . ' UTC' ) );
		}

		return array_merge(
			array(
				'id'               => (int) ( $row['id'] ?? 0 ),
				'event'            => (string) ( $row['event'] ?? '' ),
				'object_type'      => (string) ( $row['object_type'] ?? '' ),
				'object_id'        => (int) ( $row['object_id'] ?? 0 ),
				'recipient_type'   => (string) ( $row['recipient_type'] ?? '' ),
				'recipient'        => Phone::mask( (string) ( $row['recipient_phone'] ?? '' ) ),
				'template'         => (string) ( $row['template_name'] ?? '' ),
				'language'         => (string) ( $row['template_language'] ?? '' ),
				'status'           => (string) ( $row['status'] ?? '' ),
				'attempts'         => (int) ( $row['attempts'] ?? 0 ),
				'error_code'       => (string) ( $row['error_code'] ?? '' ),
				'error_message'    => (string) ( $row['error_message'] ?? '' ),
				'pricing_category' => (string) ( $row['pricing_category'] ?? '' ),
				'payload'          => is_array( $payload ) ? $payload : null,
			),
			$dates
		);
	}

	/**
	 * Template for the browser: name, language, status and the body text with its variable count.
	 *
	 * @param array<string, mixed> $template Template from Meta.
	 * @return array<string, mixed>
	 */
	public static function format_template( array $template ): array {
		$body = '';
		foreach ( (array) ( $template['components'] ?? array() ) as $component ) {
			if ( 'BODY' === ( $component['type'] ?? '' ) ) {
				$body = (string) ( $component['text'] ?? '' );
			}
		}
		preg_match_all( '/\{\{\s*\w+\s*\}\}/', $body, $matches );

		return array(
			'id'              => (string) ( $template['id'] ?? '' ),
			'name'            => (string) ( $template['name'] ?? '' ),
			'language'        => (string) ( $template['language'] ?? '' ),
			'status'          => (string) ( $template['status'] ?? '' ),
			'category'        => (string) ( $template['category'] ?? '' ),
			'body'            => $body,
			'variables'       => count( array_unique( $matches[0] ) ),
			'rejected_reason' => (string) ( $template['rejected_reason'] ?? '' ),
		);
	}

	/**
	 * REST error from an API exception, with the plain-language hint as the message.
	 *
	 * @param ApiException $e Exception.
	 */
	private static function error( ApiException $e ): \WP_Error {
		return new \WP_Error(
			'ordervox_api_error',
			$e->hint(),
			array(
				'status'    => 502,
				'meta_code' => $e->meta_code(),
				'details'   => $e->getMessage(),
			)
		);
	}
}

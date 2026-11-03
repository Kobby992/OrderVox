<?php
/**
 * Error returned by the Graph API or the HTTP layer.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Carries Meta's error code alongside a store-owner-friendly explanation and whether a retry may succeed.
 */
final class ApiException extends \RuntimeException {

	/**
	 * Meta error codes worth retrying later: throttling and temporary failures.
	 */
	private const RETRYABLE_CODES = array( 1, 2, 4, 17, 341, 80007, 130429, 131000, 131016, 131048, 131056 );

	/**
	 * Plain-language explanations for common Meta error codes.
	 */
	private const HINTS = array(
		10     => 'The access token is missing a permission. Generate a System User token with whatsapp_business_messaging and whatsapp_business_management.',
		100    => 'Meta rejected a parameter. Check that the Phone Number ID and WhatsApp Business Account ID are correct.',
		190    => 'The access token is invalid or has expired. Create a permanent System User token in Meta Business Settings.',
		200    => 'The access token is missing a permission. Generate a System User token with whatsapp_business_messaging and whatsapp_business_management.',
		368    => 'Meta has temporarily blocked this account for policy reasons. Check WhatsApp Manager for details.',
		131026 => 'The message could not be delivered. The number may not use WhatsApp, or has not accepted the latest terms.',
		131030 => 'This number is not on the test recipient list. Add it under WhatsApp > API Setup in your Meta app, or use a registered business number.',
		131031 => 'The WhatsApp Business account is locked. Check WhatsApp Manager.',
		131042 => 'There is a billing problem with the WhatsApp Business account. Add or fix the payment method in WhatsApp Manager.',
		131047 => 'More than 24 hours have passed since the recipient last messaged you, so only an approved template can be sent.',
		131051 => 'This message type is not supported.',
		132000 => 'The number of values does not match the template variables. Check the template in Ordervox and in WhatsApp Manager.',
		132001 => 'This template does not exist in the chosen language, or is not approved yet.',
		132007 => 'The template text breaks a WhatsApp formatting rule.',
		132015 => 'This template is paused because of low quality. Edit it in WhatsApp Manager.',
		132016 => 'This template is disabled because of low quality.',
		133010 => 'The business phone number is not registered with the Cloud API yet.',
	);

	/**
	 * Meta error code, or 0 for transport errors.
	 *
	 * @var int
	 */
	private int $meta_code;

	/**
	 * HTTP status, or 0 when no response arrived.
	 *
	 * @var int
	 */
	private int $http_status;

	/**
	 * Constructor.
	 *
	 * @param string $message     Raw error message from Meta or WordPress.
	 * @param int    $meta_code   Meta error code.
	 * @param int    $http_status HTTP status code.
	 */
	public function __construct( string $message, int $meta_code = 0, int $http_status = 0 ) {
		parent::__construct( $message, $meta_code );
		$this->meta_code   = $meta_code;
		$this->http_status = $http_status;
	}

	/**
	 * Builds an exception from a decoded Graph API error body.
	 *
	 * @param array<string, mixed> $body        Decoded JSON response.
	 * @param int                  $http_status HTTP status code.
	 */
	public static function from_response( array $body, int $http_status ): self {
		$error   = is_array( $body['error'] ?? null ) ? $body['error'] : array();
		$message = (string) ( $error['error_data']['details'] ?? $error['message'] ?? 'Unknown WhatsApp API error.' );
		return new self( $message, (int) ( $error['code'] ?? 0 ), $http_status );
	}

	/**
	 * Meta error code.
	 */
	public function meta_code(): int {
		return $this->meta_code;
	}

	/**
	 * HTTP status code.
	 */
	public function http_status(): int {
		return $this->http_status;
	}

	/**
	 * Whether the same request may succeed later.
	 */
	public function is_retryable(): bool {
		if ( 0 === $this->http_status || $this->http_status >= 500 || 429 === $this->http_status ) {
			return true;
		}
		return in_array( $this->meta_code, self::RETRYABLE_CODES, true );
	}

	/**
	 * Explanation to show a store owner: a known hint, or Meta's own message.
	 */
	public function hint(): string {
		return self::hint_for( $this->meta_code, $this->getMessage() );
	}

	/**
	 * Explanation for a Meta error code, falling back to the given message.
	 *
	 * @param int    $code     Meta error code.
	 * @param string $fallback Message used when the code is unknown.
	 */
	public static function hint_for( int $code, string $fallback ): string {
		return self::HINTS[ $code ] ?? $fallback;
	}
}

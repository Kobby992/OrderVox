<?php
/**
 * Webhook signature verification.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Webhook;

defined( 'ABSPATH' ) || exit;

/**
 * Checks Meta's `X-Hub-Signature-256` header: `sha256=` + HMAC-SHA256 of the raw body keyed with the App Secret.
 */
final class Signature {

	/**
	 * Whether the header matches the body.
	 *
	 * @param string $raw_body   Raw request body, exactly as received.
	 * @param string $header     X-Hub-Signature-256 header value.
	 * @param string $app_secret Meta App Secret.
	 */
	public static function is_valid( string $raw_body, string $header, string $app_secret ): bool {
		if ( '' === $app_secret || ! str_starts_with( $header, 'sha256=' ) ) {
			return false;
		}
		$expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $app_secret );
		return hash_equals( $expected, strtolower( trim( $header ) ) );
	}
}

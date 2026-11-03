<?php
/**
 * Tests for webhook Signature.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Webbcode\Ordervox\Webhook\Signature;

/**
 * Covers HMAC verification of Meta webhook calls.
 */
final class SignatureTest extends TestCase {

	private const SECRET = 'app-secret';

	public function test_valid_signature(): void {
		$body = '{"object":"whatsapp_business_account"}';
		$sig  = 'sha256=' . hash_hmac( 'sha256', $body, self::SECRET );

		$this->assertTrue( Signature::is_valid( $body, $sig, self::SECRET ) );
		$this->assertTrue( Signature::is_valid( $body, 'sha256=' . strtoupper( substr( $sig, 7 ) ), self::SECRET ) );
	}

	public function test_rejects_modified_body_wrong_secret_and_bad_headers(): void {
		$body = '{"a":1}';
		$sig  = 'sha256=' . hash_hmac( 'sha256', $body, self::SECRET );

		$this->assertFalse( Signature::is_valid( '{"a":2}', $sig, self::SECRET ) );
		$this->assertFalse( Signature::is_valid( $body, $sig, 'other-secret' ) );
		$this->assertFalse( Signature::is_valid( $body, substr( $sig, 7 ), self::SECRET ) );
		$this->assertFalse( Signature::is_valid( $body, '', self::SECRET ) );
		$this->assertFalse( Signature::is_valid( $body, $sig, '' ) );
	}
}

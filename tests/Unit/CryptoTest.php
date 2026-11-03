<?php
/**
 * Tests for Crypto.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Webbcode\Ordervox\Support\Crypto;

/**
 * Covers encryption round trips and failure cases.
 */
final class CryptoTest extends TestCase {

	public function test_round_trip(): void {
		$crypto = new Crypto( str_repeat( 'k', 32 ) );
		$stored = $crypto->encrypt( 'EAAG-secret-token' );

		$this->assertStringStartsWith( 'ovx1:', $stored );
		$this->assertStringNotContainsString( 'secret', $stored );
		$this->assertSame( 'EAAG-secret-token', $crypto->decrypt( $stored ) );
	}

	public function test_same_input_encrypts_differently_each_time(): void {
		$crypto = new Crypto( str_repeat( 'k', 32 ) );
		$this->assertNotSame( $crypto->encrypt( 'abc' ), $crypto->encrypt( 'abc' ) );
	}

	public function test_empty_string_stays_empty(): void {
		$crypto = new Crypto( str_repeat( 'k', 32 ) );
		$this->assertSame( '', $crypto->encrypt( '' ) );
		$this->assertSame( '', $crypto->decrypt( '' ) );
	}

	public function test_wrong_key_returns_null(): void {
		$stored = ( new Crypto( str_repeat( 'a', 32 ) ) )->encrypt( 'secret' );
		$this->assertNull( ( new Crypto( str_repeat( 'b', 32 ) ) )->decrypt( $stored ) );
	}

	public function test_tampered_or_foreign_values_return_null(): void {
		$crypto = new Crypto( str_repeat( 'k', 32 ) );
		$stored = $crypto->encrypt( 'secret' );

		$tampered       = $stored;
		$tampered[10]   = 'A' === $tampered[10] ? 'B' : 'A';
		$this->assertNull( $crypto->decrypt( $tampered ) );
		$this->assertNull( $crypto->decrypt( 'plain-text-token' ) );
		$this->assertNull( $crypto->decrypt( 'ovx1:not base64!' ) );
		$this->assertNull( $crypto->decrypt( 'ovx1:' . base64_encode( 'short' ) ) );
	}

	public function test_rejects_short_key(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Crypto( 'too-short' );
	}
}

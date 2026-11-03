<?php
/**
 * Symmetric encryption for secrets stored in the database.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts strings with libsodium secretbox (XSalsa20-Poly1305).
 *
 * The key comes from the ORDERVOX_ENCRYPTION_KEY constant when defined, otherwise from the
 * site's AUTH salt. If the salt changes, stored secrets can no longer be decrypted and
 * decrypt() returns null, so the user must re-enter their credentials.
 */
final class Crypto {

	private const PREFIX = 'ovx1:';

	/**
	 * Raw 32-byte key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Builds an instance from a raw 32-byte key.
	 *
	 * @param string $key Raw key bytes.
	 * @throws \InvalidArgumentException When the key has the wrong length.
	 */
	public function __construct( string $key ) {
		if ( SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen( $key ) ) {
			throw new \InvalidArgumentException( 'Encryption key must be 32 bytes.' );
		}
		$this->key = $key;
	}

	/**
	 * Builds an instance keyed from the site configuration.
	 */
	public static function from_environment(): self {
		$material = defined( 'ORDERVOX_ENCRYPTION_KEY' ) ? (string) constant( 'ORDERVOX_ENCRYPTION_KEY' ) : wp_salt( 'auth' );
		return new self( hash( 'sha256', 'ordervox|' . $material, true ) );
	}

	/**
	 * Encrypts a string. Empty strings stay empty.
	 *
	 * @param string $plain Plain text.
	 */
	public function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe storage of ciphertext.
		return self::PREFIX . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $this->key ) );
	}

	/**
	 * Decrypts a value from encrypt(). Returns null when it cannot be decrypted.
	 *
	 * @param string $stored Stored value.
	 */
	public function decrypt( string $stored ): ?string {
		if ( '' === $stored ) {
			return '';
		}
		if ( ! str_starts_with( $stored, self::PREFIX ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary-safe storage of ciphertext.
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return null;
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $this->key );

		return false === $plain ? null : $plain;
	}
}

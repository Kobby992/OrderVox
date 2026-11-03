<?php
/**
 * WhatsApp Cloud API credentials repository.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Settings;

use Webbcode\Ordervox\Support\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Stores Meta credentials in a non-autoloaded option, encrypting the secret fields.
 */
final class Credentials {

	public const OPTION = 'ordervox_credentials';

	/**
	 * Fields and whether each is secret (encrypted at rest, never sent back to the browser).
	 */
	public const FIELDS = array(
		'app_id'               => false,
		'app_secret'           => true,
		'waba_id'              => false,
		'phone_number_id'      => false,
		'access_token'         => true,
		'webhook_verify_token' => true,
	);

	/**
	 * Encryption helper.
	 *
	 * @var Crypto
	 */
	private Crypto $crypto;

	/**
	 * Constructor.
	 *
	 * @param Crypto $crypto Encryption helper.
	 */
	public function __construct( Crypto $crypto ) {
		$this->crypto = $crypto;
	}

	/**
	 * Returns a decrypted field. Null means the stored secret could not be decrypted
	 * (for example after the site's salts changed).
	 *
	 * @param string $field Field name from FIELDS.
	 */
	public function get( string $field ): ?string {
		$stored = $this->stored();
		$value  = isset( $stored[ $field ] ) ? (string) $stored[ $field ] : '';

		return ( self::FIELDS[ $field ] ?? false ) ? $this->crypto->decrypt( $value ) : $value;
	}

	/**
	 * Saves the given fields. Unknown fields are ignored; secrets are encrypted.
	 *
	 * @param array<string, string> $values Field values.
	 */
	public function set( array $values ): void {
		$stored = $this->stored();
		foreach ( $values as $field => $value ) {
			if ( ! array_key_exists( $field, self::FIELDS ) ) {
				continue;
			}
			$value            = trim( (string) $value );
			$stored[ $field ] = self::FIELDS[ $field ] ? $this->crypto->encrypt( $value ) : sanitize_text_field( $value );
		}
		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Values safe to show in the admin UI: secrets are replaced by a mask or flagged unreadable.
	 *
	 * @return array<string, string>
	 */
	public function masked(): array {
		$out = array();
		foreach ( self::FIELDS as $field => $secret ) {
			$value = $this->get( $field );
			if ( ! $secret ) {
				$out[ $field ] = (string) $value;
			} elseif ( null === $value ) {
				$out[ $field ] = 'unreadable';
			} else {
				$out[ $field ] = '' === $value ? '' : '••••' . substr( $value, -4 );
			}
		}
		return $out;
	}

	/**
	 * Whether everything needed to send messages is present and readable.
	 */
	public function is_configured(): bool {
		foreach ( array( 'phone_number_id', 'waba_id', 'access_token' ) as $field ) {
			$value = $this->get( $field );
			if ( null === $value || '' === $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Creates the webhook verify token if missing.
	 */
	public function ensure_verify_token(): void {
		$token = $this->get( 'webhook_verify_token' );
		if ( null === $token || '' === $token ) {
			$this->set( array( 'webhook_verify_token' => wp_generate_password( 32, false ) ) );
		}
	}

	/**
	 * Raw stored option.
	 *
	 * @return array<string, string>
	 */
	private function stored(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}
}

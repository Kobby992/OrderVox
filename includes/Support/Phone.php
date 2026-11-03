<?php
/**
 * Phone number normalisation.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Normalises phone numbers to E.164 (`+` and 8–15 digits).
 *
 * Release builds bundle libphonenumber under the plugin's own namespace (see `extra.strauss` in
 * composer.json) for per-country parsing. Without it, a lightweight rule-based normaliser is used.
 */
final class Phone {

	private const LIB_UTIL   = '\\Webbcode\\Ordervox\\Vendor\\libphonenumber\\PhoneNumberUtil';
	private const LIB_FORMAT = '\\Webbcode\\Ordervox\\Vendor\\libphonenumber\\PhoneNumberFormat';

	/**
	 * Converts a number to E.164 using the rules of a country, or null when it cannot be a valid number.
	 *
	 * @param string $raw     Number as typed.
	 * @param string $country ISO 3166-1 alpha-2 country for national numbers. Defaults to the store's country.
	 */
	public static function normalise( string $raw, string $country = '' ): ?string {
		$country = strtoupper( '' !== $country ? $country : self::base_country() );

		if ( '' !== $country && class_exists( self::LIB_UTIL ) && function_exists( 'mb_substr' ) ) {
			try {
				$util   = call_user_func( array( self::LIB_UTIL, 'getInstance' ) );
				$number = $util->parse( $raw, $country );
				// "Possible" (right length for the country) rather than "valid", so numbers in newly
				// allocated ranges are not rejected by older metadata.
				return $util->isPossibleNumber( $number ) ? $util->format( $number, constant( self::LIB_FORMAT . '::E164' ) ) : null;
			} catch ( \Throwable $e ) {
				return null;
			}
		}

		return self::to_e164( $raw, self::calling_code( $country ) );
	}

	/**
	 * Converts a raw number to E.164 with simple rules, or null when it cannot be a valid number.
	 *
	 * @param string $raw          Number as typed.
	 * @param string $calling_code Default country calling code without `+`, e.g. `234`, used for national numbers.
	 */
	public static function to_e164( string $raw, string $calling_code = '' ): ?string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		$international = str_starts_with( $raw, '+' ) || str_starts_with( $raw, '00' );
		$digits        = (string) preg_replace( '/\D+/', '', $raw );

		if ( str_starts_with( $raw, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( ! $international ) {
			$calling_code = (string) preg_replace( '/\D+/', '', $calling_code );
			if ( '' === $calling_code ) {
				return null;
			}
			// Drop the national trunk prefix (0) and add the country code, unless it is already there.
			if ( str_starts_with( $digits, '0' ) ) {
				$digits = $calling_code . ltrim( $digits, '0' );
			} elseif ( ! str_starts_with( $digits, $calling_code ) || strlen( $digits ) < 10 ) {
				$digits = $calling_code . $digits;
			}
		}

		$length = strlen( $digits );
		if ( $length < 8 || $length > 15 || str_starts_with( $digits, '0' ) ) {
			return null;
		}

		return '+' . $digits;
	}

	/**
	 * Calling code for a WooCommerce country code, e.g. `NG` → `234`.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code. Defaults to the store base country.
	 */
	public static function calling_code( string $country = '' ): string {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}
		$country = '' !== $country ? $country : WC()->countries->get_base_country();
		$code    = WC()->countries->get_country_calling_code( $country );
		$code    = is_array( $code ) ? (string) reset( $code ) : (string) $code;
		return ltrim( $code, '+' );
	}

	/**
	 * The WooCommerce store's country code, or '' outside WooCommerce.
	 */
	private static function base_country(): string {
		return function_exists( 'WC' ) && WC()->countries ? (string) WC()->countries->get_base_country() : '';
	}

	/**
	 * Hides the middle digits for logs and screens, e.g. `+23480****5678`.
	 *
	 * @param string $e164 Number in E.164.
	 */
	public static function mask( string $e164 ): string {
		$length = strlen( $e164 );
		if ( $length <= 8 ) {
			return $e164;
		}
		return substr( $e164, 0, 6 ) . str_repeat( '*', max( 2, $length - 10 ) ) . substr( $e164, -4 );
	}
}

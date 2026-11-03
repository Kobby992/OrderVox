<?php
/**
 * Converts editable templates into Meta templates.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Turns `{placeholder}` text into Meta's numbered `{{1}}` variables, checks Meta's template rules,
 * and cleans values before they are sent.
 */
final class Compiler {

	public const BODY_MAX   = 1024;
	public const FOOTER_MAX = 60;
	public const VALUE_MAX  = 300;

	private const PLACEHOLDER = '/\{([a-z0-9_:]+)\}/';

	/**
	 * Replaces each known placeholder with the next `{{n}}`. Unknown placeholders stay as typed.
	 *
	 * @param string   $body  Editable body.
	 * @param string[] $known Allowed placeholder names.
	 * @return array{text: string, keys: string[]}
	 */
	public static function compile( string $body, array $known ): array {
		$keys = array();
		$text = (string) preg_replace_callback(
			self::PLACEHOLDER,
			static function ( array $match ) use ( &$keys, $known ) {
				if ( ! in_array( $match[1], $known, true ) ) {
					return $match[0];
				}
				$keys[] = $match[1];
				return '{{' . count( $keys ) . '}}';
			},
			self::normalise( $body )
		);

		return array(
			'text' => $text,
			'keys' => $keys,
		);
	}

	/**
	 * Fills placeholders with values, for previews.
	 *
	 * @param string                $body   Editable body.
	 * @param array<string, string> $values Values by placeholder name.
	 */
	public static function render( string $body, array $values ): string {
		return (string) preg_replace_callback(
			self::PLACEHOLDER,
			static fn( array $match ) => array_key_exists( $match[1], $values ) ? self::clean_value( $values[ $match[1] ] ) : $match[0],
			self::normalise( $body )
		);
	}

	/**
	 * Problems that would make Meta reject the template, or that the user should know about.
	 *
	 * @param string   $body   Editable body.
	 * @param string   $footer Footer text.
	 * @param string[] $known  Allowed placeholder names.
	 * @return array{errors: string[], warnings: string[]}
	 */
	public static function validate( string $body, string $footer, array $known ): array {
		$errors   = array();
		$warnings = array();
		$body     = self::normalise( $body );
		$compiled = self::compile( $body, $known );
		$text     = trim( $compiled['text'] );

		if ( '' === $text ) {
			$errors[] = __( 'The message cannot be empty.', 'ordervox' );
		}
		if ( mb_strlen( $text ) > self::BODY_MAX ) {
			/* translators: %d: maximum characters. */
			$errors[] = sprintf( __( 'The message is too long. WhatsApp allows %d characters.', 'ordervox' ), self::BODY_MAX );
		}
		if ( preg_match( '/^\{\{\d+\}\}/', $text ) || preg_match( '/\{\{\d+\}\}$/', $text ) ) {
			$errors[] = __( 'The message cannot start or end with a placeholder. Add some text before or after it.', 'ordervox' );
		}
		if ( preg_match( '/\}\}\s*\{\{/', $text ) ) {
			$errors[] = __( 'Two placeholders cannot be next to each other. Put some text between them.', 'ordervox' );
		}
		if ( mb_strlen( trim( $footer ) ) > self::FOOTER_MAX ) {
			/* translators: %d: maximum characters. */
			$errors[] = sprintf( __( 'The footer is too long. WhatsApp allows %d characters.', 'ordervox' ), self::FOOTER_MAX );
		}
		if ( preg_match( self::PLACEHOLDER, $footer ) ) {
			$errors[] = __( 'The footer cannot contain placeholders.', 'ordervox' );
		}

		preg_match_all( self::PLACEHOLDER, $body, $all );
		$unknown = array_diff( array_unique( $all[1] ), $known );
		if ( $unknown ) {
			$warnings[] = sprintf(
				/* translators: %s: comma-separated placeholders. */
				__( 'These placeholders are not available for this alert and will be sent as plain text: %s', 'ordervox' ),
				'{' . implode( '}, {', $unknown ) . '}'
			);
		}

		// Meta rejects templates with too many variables for their length.
		$plain_words = str_word_count( (string) preg_replace( '/\{\{\d+\}\}/', ' ', $text ) );
		$variables   = count( $compiled['keys'] );
		if ( $variables > 0 && $plain_words < $variables * 2 ) {
			$warnings[] = __( 'Meta may reject this template because it has many placeholders and little text. Add more words around them.', 'ordervox' );
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Makes a value safe for a template variable: no newlines or tabs, no runs of spaces,
	 * not empty, and not too long.
	 *
	 * @param string $value Raw value.
	 */
	public static function clean_value( string $value ): string {
		$value = (string) preg_replace( '/\s*[\r\n]+\s*/', ' · ', trim( $value ) );
		$value = (string) preg_replace( '/[\t ]{2,}/', ' ', str_replace( "\t", ' ', $value ) );
		if ( '' === $value ) {
			return '-';
		}
		if ( mb_strlen( $value ) > self::VALUE_MAX ) {
			$value = rtrim( mb_substr( $value, 0, self::VALUE_MAX - 1 ) ) . '…';
		}
		return $value;
	}

	/**
	 * Unifies line endings and trims trailing spaces on each line.
	 *
	 * @param string $text Text.
	 */
	private static function normalise( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		return trim( (string) preg_replace( '/[ \t]+\n/', "\n", $text ) );
	}
}

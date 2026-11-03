<?php
/**
 * WhatsApp template language codes.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Templates;

use Webbcode\Ordervox\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Maps WordPress locales to the language codes Meta accepts for templates.
 */
final class Language {

	/**
	 * Space-separated language codes supported by WhatsApp message templates.
	 */
	public const CODES_LIST = 'af sq ar az bn bg ca zh_CN zh_HK zh_TW hr cs da nl en en_GB en_US et fil fi fr ka de el gu ha he hi hu id ga it ja kn kk rw_RW ko ky_KG lo lv lt mk ms ml mr nb fa pl pt_BR pt_PT pa ro ru sr sk sl es es_AR es_ES es_MX sw sv ta te th tr uk ur uz vi zu';

	/**
	 * Language codes supported by WhatsApp message templates.
	 *
	 * @return string[]
	 */
	public static function codes(): array {
		return explode( ' ', self::CODES_LIST );
	}

	/**
	 * Language used for templates: the saved choice, else derived from the site locale.
	 *
	 * @param Settings $settings Settings.
	 */
	public static function current( Settings $settings ): string {
		$saved = (string) $settings->get( 'template_language', '' );
		return in_array( $saved, self::codes(), true ) ? $saved : self::from_locale( determine_locale() );
	}

	/**
	 * Closest template language for a WordPress locale, e.g. `fr_FR` → `fr`, `pt_BR` → `pt_BR`.
	 *
	 * @param string $locale WordPress locale.
	 */
	public static function from_locale( string $locale ): string {
		if ( in_array( $locale, self::codes(), true ) ) {
			return $locale;
		}
		$language = strtolower( (string) strtok( $locale, '_' ) );
		if ( 'en' === $language ) {
			return 'en_US';
		}
		return in_array( $language, self::codes(), true ) ? $language : 'en_US';
	}
}

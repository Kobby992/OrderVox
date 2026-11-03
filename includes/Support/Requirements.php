<?php
/**
 * Runtime requirement checks.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that WooCommerce is active and recent enough.
 */
final class Requirements {

	/**
	 * Returns a list of unmet requirements, empty when all are met.
	 *
	 * @return string[]
	 */
	public static function check(): array {
		$errors = array();

		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			$errors[] = __( 'Ordervox requires WooCommerce to be installed and active.', 'ordervox' );
		} elseif ( version_compare( WC_VERSION, ORDERVOX_MIN_WC, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WooCommerce version, 2: installed WooCommerce version. */
				__( 'Ordervox requires WooCommerce %1$s or newer. You have version %2$s.', 'ordervox' ),
				ORDERVOX_MIN_WC,
				WC_VERSION
			);
		}

		return $errors;
	}

	/**
	 * Shows unmet requirements as admin notices to users who can fix them.
	 *
	 * @param string[] $errors Messages from check().
	 */
	public static function show_notices( array $errors ): void {
		add_action(
			'admin_notices',
			static function () use ( $errors ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				foreach ( $errors as $error ) {
					printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
				}
			}
		);
	}
}

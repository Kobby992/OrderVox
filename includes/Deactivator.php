<?php
/**
 * Deactivation routines.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox;

defined( 'ABSPATH' ) || exit;

/**
 * Stops scheduled work. Data is kept; uninstall.php removes it when the user opts in.
 */
final class Deactivator {

	/**
	 * Deactivation hook callback.
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), 'ordervox' );
		}
	}
}

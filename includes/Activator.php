<?php
/**
 * Activation routines.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox;

use Webbcode\Ordervox\Migrations\Migrator;
use Webbcode\Ordervox\Settings\Credentials;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Support\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Creates tables and default options, per site on multisite.
 */
final class Activator {

	/**
	 * Activation hook callback.
	 *
	 * @param bool $network_wide Whether activated for the whole network.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}
			return;
		}
		self::activate_site();
	}

	/**
	 * Sets up a site created after a network-wide activation.
	 *
	 * @param \WP_Site $site New site.
	 */
	public static function activate_new_site( \WP_Site $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( ORDERVOX_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::activate_site();
		restore_current_blog();
	}

	/**
	 * Sets up the current site.
	 */
	private static function activate_site(): void {
		Migrator::migrate();
		add_option( Settings::OPTION, Settings::defaults(), '', true );
		( new Credentials( Crypto::from_environment() ) )->ensure_verify_token();
	}
}

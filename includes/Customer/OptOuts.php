<?php
/**
 * Numbers that asked not to be messaged.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Customer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; opt-outs must take effect immediately, so no caching.

/**
 * Suppression list in `{prefix}ordervox_optouts`. Numbers are stored as SHA-256 hashes of their
 * E.164 form, so the list blocks messages without keeping the numbers themselves.
 *
 * Not final so tests can substitute an in-memory version.
 */
class OptOuts {

	/**
	 * Full table name.
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ordervox_optouts';
	}

	/**
	 * Whether the number has opted out.
	 *
	 * @param string $e164 Number in E.164.
	 */
	public function is_opted_out( string $e164 ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE phone_hash = %s', $this->table(), self::hash( $e164 ) ) );
	}

	/**
	 * Adds a number to the suppression list.
	 *
	 * @param string $e164   Number in E.164.
	 * @param string $source Where the opt-out came from: keyword, account, admin.
	 */
	public function add( string $e164, string $source ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (phone_hash, source, created_at) VALUES (%s, %s, %s)',
				$this->table(),
				self::hash( $e164 ),
				substr( $source, 0, 16 ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Removes a number after the customer gives consent again.
	 *
	 * @param string $e164 Number in E.164.
	 */
	public function remove( string $e164 ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'phone_hash' => self::hash( $e164 ) ) );
	}

	/**
	 * Hash stored for a number.
	 *
	 * @param string $e164 Number in E.164.
	 */
	public static function hash( string $e164 ): string {
		return hash( 'sha256', '+' . ltrim( $e164, '+' ) );
	}
}

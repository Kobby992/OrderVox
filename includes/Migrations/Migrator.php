<?php
/**
 * Database schema and versioned migrations.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's custom tables (spec §10).
 */
final class Migrator {

	public const DB_VERSION = 2;
	public const OPTION     = 'ordervox_db_version';

	/**
	 * Runs migrations when the stored schema version is behind the code.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPTION, 0 ) < self::DB_VERSION ) {
			self::migrate();
		}
	}

	/**
	 * Applies the current schema with dbDelta and records the version.
	 */
	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'ordervox_';

		// dbDelta formatting rules: one column per line, two spaces after PRIMARY KEY, KEY not INDEX.
		dbDelta(
			"CREATE TABLE {$prefix}messages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  idempotency_key varchar(191) NOT NULL,
  event varchar(64) NOT NULL,
  object_type varchar(32) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  recipient_type varchar(16) NOT NULL,
  recipient_phone varchar(32) NOT NULL,
  template_name varchar(128) NOT NULL DEFAULT '',
  template_language varchar(16) NOT NULL DEFAULT '',
  payload longtext NULL,
  status varchar(16) NOT NULL DEFAULT 'queued',
  wamid varchar(128) NULL,
  attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
  error_code varchar(32) NULL,
  error_message text NULL,
  pricing_category varchar(32) NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  sent_at datetime NULL,
  delivered_at datetime NULL,
  read_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY idempotency_key (idempotency_key),
  KEY wamid (wamid),
  KEY object (object_type,object_id),
  KEY status_created (status,created_at),
  KEY event_created (event,created_at)
) {$charset};
CREATE TABLE {$prefix}webhook_events (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  meta_id varchar(191) NOT NULL,
  type varchar(32) NOT NULL,
  received_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY meta_id (meta_id),
  KEY received_at (received_at)
) {$charset};
CREATE TABLE {$prefix}optouts (
  phone_hash char(64) NOT NULL,
  source varchar(16) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (phone_hash)
) {$charset};"
		);

		update_option( self::OPTION, self::DB_VERSION, true );
	}

	/**
	 * Table names owned by the free plugin, used by uninstall.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		global $wpdb;
		return array(
			$wpdb->prefix . 'ordervox_messages',
			$wpdb->prefix . 'ordervox_webhook_events',
			$wpdb->prefix . 'ordervox_optouts',
		);
	}
}

<?php
/**
 * General settings repository.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the autoloaded `ordervox_settings` option, merged over defaults.
 */
final class Settings {

	public const OPTION = 'ordervox_settings';

	/**
	 * Admin events and whether each is on by default (spec §7).
	 */
	public const EVENTS = array(
		'order_new'         => true,
		'payment_received'  => true,
		'status_processing' => false,
		'status_completed'  => false,
		'status_cancelled'  => false,
		'status_refunded'   => false,
		'payment_failed'    => true,
		'stock_low'         => true,
		'review_new'        => false,
	);

	/**
	 * Cached merged settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'owner_phone'              => '',
			'events'                   => self::EVENTS,
			'template_language'        => '',
			'daily_summary'            => array(
				'enabled' => false,
				'time'    => '08:00',
			),
			'customer'                 => array(
				'optin_enabled'    => false,
				'optin_label'      => '',
				'notify_confirmed' => false,
				'notify_completed' => false,
			),
			'log_retention_days'       => 30,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = array_replace_recursive( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->cache;
	}

	/**
	 * Reads one setting by dot path, e.g. `events.order_new`.
	 *
	 * @param string $path     Dot-separated key path.
	 * @param mixed  $fallback Returned when the path does not exist.
	 * @return mixed
	 */
	public function get( string $path, $fallback = null ) {
		$value = $this->all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Whether an admin event is switched on.
	 *
	 * @param string $event Event key from EVENTS.
	 */
	public function is_event_enabled( string $event ): bool {
		return (bool) $this->get( 'events.' . $event, false );
	}

	/**
	 * Merges and saves changes. Unknown keys are dropped and values are coerced to the
	 * type of their default.
	 *
	 * @param array<string, mixed> $changes Partial settings.
	 */
	public function update( array $changes ): void {
		$merged = self::sanitize( array_replace_recursive( $this->all(), $changes ), self::defaults() );
		update_option( self::OPTION, $merged, true );
		$this->cache = $merged;
	}

	/**
	 * Keeps only keys present in $defaults and coerces scalars to the default's type.
	 *
	 * @param array<string, mixed> $values   Values to clean.
	 * @param array<string, mixed> $defaults Shape to enforce.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $values, array $defaults ): array {
		$clean = array();
		foreach ( $defaults as $key => $default ) {
			$value = $values[ $key ] ?? $default;
			if ( is_array( $default ) ) {
				$clean[ $key ] = self::sanitize( is_array( $value ) ? $value : array(), $default );
			} elseif ( is_bool( $default ) ) {
				$clean[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = max( 0, (int) $value );
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}
}

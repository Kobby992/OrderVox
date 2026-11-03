<?php
/**
 * Main plugin container.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox;

use Webbcode\Ordervox\Admin\Menu;
use Webbcode\Ordervox\Migrations\Migrator;
use Webbcode\Ordervox\Settings\Credentials;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Support\Crypto;
use Webbcode\Ordervox\Support\Requirements;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up services and registers hooks once WooCommerce is available.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Lazily built services, keyed by id.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks. Runs on plugins_loaded.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$errors = Requirements::check();
		if ( $errors ) {
			Requirements::show_notices( $errors );
			return;
		}

		Migrator::maybe_upgrade();
		add_action( 'wp_initialize_site', array( Activator::class, 'activate_new_site' ) );

		if ( is_admin() ) {
			( new Menu() )->register();
		}

		/**
		 * Fires after Ordervox has registered its own hooks. Add-ons (Ordervox Pro) attach here.
		 *
		 * @param Plugin $plugin The plugin container.
		 */
		do_action( 'ordervox_loaded', $this );
	}

	/**
	 * Plugin settings repository.
	 */
	public function settings(): Settings {
		return $this->service( 'settings', static fn() => new Settings() );
	}

	/**
	 * Encrypted Meta credentials repository.
	 */
	public function credentials(): Credentials {
		return $this->service( 'credentials', static fn() => new Credentials( Crypto::from_environment() ) );
	}

	/**
	 * Returns a service, building it on first use.
	 *
	 * @template T of object
	 * @param string          $id      Service id.
	 * @param callable(): T   $factory Builds the service.
	 * @return T
	 */
	private function service( string $id, callable $factory ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $factory();
		}
		return $this->services[ $id ];
	}
}

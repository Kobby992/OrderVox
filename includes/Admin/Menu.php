<?php
/**
 * Admin menu and app shell.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Ordervox screen under WooCommerce and loads the React admin app on it.
 */
final class Menu {

	public const SLUG = 'ordervox';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ORDERVOX_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the submenu page.
	 */
	public function add_page(): void {
		$hook = add_submenu_page(
			'woocommerce',
			__( 'Ordervox', 'ordervox' ),
			__( 'Ordervox', 'ordervox' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Renders the mount point for the React app.
	 */
	public function render(): void {
		echo '<div class="wrap"><div id="ordervox-admin-root"><h1>' . esc_html__( 'Ordervox', 'ordervox' ) . '</h1></div></div>';
	}

	/**
	 * Loads the admin bundle on the Ordervox screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$asset_file = ORDERVOX_PATH . 'build/admin.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'ordervox-admin',
			ORDERVOX_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'ordervox-admin', 'ordervox', ORDERVOX_PATH . 'languages' );

		if ( is_readable( ORDERVOX_PATH . 'build/admin.css' ) ) {
			wp_enqueue_style( 'ordervox-admin', ORDERVOX_URL . 'build/admin.css', array( 'wp-components' ), $asset['version'] );
		}
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ordervox' ) . '</a>' );
		return $links;
	}
}

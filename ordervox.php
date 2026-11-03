<?php
/**
 * Plugin Name:          Ordervox – Order Notifications via WhatsApp for WooCommerce
 * Description:          Sends WooCommerce order, payment, stock and review alerts to WhatsApp through the official WhatsApp Cloud API.
 * Version:              0.1.0
 * Requires at least:    6.6
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               Webbcode Studios
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          ordervox
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 *
 * @package Ordervox
 */

// This file must stay parseable on old PHP versions so the version notice can show.

defined( 'ABSPATH' ) || exit;

define( 'ORDERVOX_VERSION', '0.1.0' );
define( 'ORDERVOX_FILE', __FILE__ );
define( 'ORDERVOX_PATH', plugin_dir_path( __FILE__ ) );
define( 'ORDERVOX_URL', plugin_dir_url( __FILE__ ) );
define( 'ORDERVOX_MIN_PHP', '8.1' );
define( 'ORDERVOX_MIN_WC', '9.0' );

if ( version_compare( PHP_VERSION, ORDERVOX_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Ordervox requires PHP %1$s or newer. This site runs PHP %2$s.', 'ordervox' ),
						ORDERVOX_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Webbcode\\Ordervox\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = ORDERVOX_PATH . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Third-party libraries are namespaced with Strauss into vendor-prefixed/ at build time.
if ( is_readable( ORDERVOX_PATH . 'vendor-prefixed/autoload.php' ) ) {
	require ORDERVOX_PATH . 'vendor-prefixed/autoload.php';
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ORDERVOX_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ORDERVOX_FILE, true );
		}
	}
);

register_activation_hook( ORDERVOX_FILE, array( \Webbcode\Ordervox\Activator::class, 'activate' ) );
register_deactivation_hook( ORDERVOX_FILE, array( \Webbcode\Ordervox\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Webbcode\Ordervox\Plugin::instance()->boot();
	}
);

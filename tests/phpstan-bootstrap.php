<?php
/**
 * Constants PHPStan needs to analyse the plugin outside WordPress.
 *
 * @package Ordervox
 */

define( 'ORDERVOX_VERSION', '0.1.0' );
define( 'ORDERVOX_FILE', dirname( __DIR__ ) . '/ordervox.php' );
define( 'ORDERVOX_PATH', dirname( __DIR__ ) . '/' );
define( 'ORDERVOX_URL', 'https://example.test/wp-content/plugins/ordervox/' );
define( 'ORDERVOX_MIN_PHP', '8.1' );
define( 'ORDERVOX_MIN_WC', '9.0' );
define( 'WC_VERSION', '11.2.0' );

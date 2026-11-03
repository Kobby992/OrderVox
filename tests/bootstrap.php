<?php
/**
 * Unit test bootstrap. Unit tests run without WordPress; WordPress functions are
 * mocked per test with Brain Monkey.
 *
 * @package Ordervox
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', sys_get_temp_dir() . '/ordervox-wp/' );
require_once __DIR__ . '/phpstan-bootstrap.php';

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

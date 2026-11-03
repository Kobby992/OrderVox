<?php
/**
 * Tests for Settings.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Functions;
use Webbcode\Ordervox\Settings\Settings;

/**
 * Covers defaults merging, dot-path reads and sanitising on save.
 */
final class SettingsTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $value ) => trim( strip_tags( (string) $value ) ) );
	}

	public function test_defaults_when_option_missing(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$settings = new Settings();

		$this->assertTrue( $settings->is_event_enabled( 'order_new' ) );
		$this->assertFalse( $settings->is_event_enabled( 'review_new' ) );
		$this->assertSame( 30, $settings->get( 'log_retention_days' ) );
		$this->assertSame( '08:00', $settings->get( 'daily_summary.time' ) );
	}

	public function test_stored_values_override_defaults(): void {
		Functions\when( 'get_option' )->justReturn( array( 'events' => array( 'review_new' => true ) ) );
		$settings = new Settings();

		$this->assertTrue( $settings->is_event_enabled( 'review_new' ) );
		$this->assertTrue( $settings->is_event_enabled( 'order_new' ) );
	}

	public function test_missing_path_returns_fallback(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$settings = new Settings();

		$this->assertSame( 'x', $settings->get( 'nope.deeper', 'x' ) );
		$this->assertFalse( $settings->is_event_enabled( 'not_an_event' ) );
	}

	public function test_update_drops_unknown_keys_and_coerces_types(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$saved = null;
		Functions\expect( 'update_option' )->once()->andReturnUsing(
			static function ( $name, $value ) use ( &$saved ) {
				$saved = $value;
				return true;
			}
		);

		( new Settings() )->update(
			array(
				'owner_phone'        => ' <b>+2348012345678</b> ',
				'events'             => array( 'review_new' => '1' ),
				'log_retention_days' => '-5',
				'unknown'            => 'dropped',
			)
		);

		$this->assertSame( '+2348012345678', $saved['owner_phone'] );
		$this->assertTrue( $saved['events']['review_new'] );
		$this->assertSame( 0, $saved['log_retention_days'] );
		$this->assertArrayNotHasKey( 'unknown', $saved );
	}
}

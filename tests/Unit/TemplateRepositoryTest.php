<?php
/**
 * Tests for TemplateRepository.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Functions;
use Webbcode\Ordervox\Templates\TemplateRepository;

/**
 * Covers drafts, submission, approval promotion and status changes.
 */
final class TemplateRepositoryTest extends TestCase {

	/**
	 * Fake option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	protected function set_up(): void {
		parent::set_up();
		$this->options = array();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->alias( fn( $name, $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->options[ $name ] = $value;
				return true;
			}
		);
	}

	private static function version( string $name, int $version, string $status ): array {
		return array(
			'name'    => $name,
			'keys'    => array( 'order_number' ),
			'status'  => $status,
			'version' => $version,
		);
	}

	public function test_defaults_until_saved(): void {
		$entry = ( new TemplateRepository() )->get( 'order_new', 'en_US' );

		$this->assertStringContainsString( '{order_number}', $entry['body'] );
		$this->assertNull( $entry['submitted'] );
		$this->assertNull( $entry['active'] );
	}

	public function test_approval_promotes_submitted_version(): void {
		$repo = new TemplateRepository();
		$repo->set_submitted( 'order_new', 'en_US', self::version( 'ordervox_order_new_v1', 1, 'PENDING' ) );

		$this->assertNull( $repo->active( 'order_new', 'en_US' ) );

		$this->assertTrue( $repo->apply_status( 'ordervox_order_new_v1', 'en_US', 'APPROVED' ) );
		$this->assertSame( 'ordervox_order_new_v1', $repo->active( 'order_new', 'en_US' )['name'] );
	}

	public function test_new_version_does_not_replace_active_until_approved(): void {
		$repo = new TemplateRepository();
		$repo->set_submitted( 'order_new', 'en_US', self::version( 'ordervox_order_new_v1', 1, 'APPROVED' ) );
		$repo->set_submitted( 'order_new', 'en_US', self::version( 'ordervox_order_new_v2', 2, 'PENDING' ) );

		$this->assertSame( 'ordervox_order_new_v1', $repo->active( 'order_new', 'en_US' )['name'] );
		$this->assertSame( 2, $repo->last_version( 'order_new', 'en_US' ) );

		$repo->apply_status( 'ordervox_order_new_v2', 'en_US', 'REJECTED', 'INVALID_FORMAT' );
		$entry = $repo->get( 'order_new', 'en_US' );
		$this->assertSame( 'REJECTED', $entry['submitted']['status'] );
		$this->assertSame( 'INVALID_FORMAT', $entry['submitted']['reason'] );
		$this->assertSame( 'ordervox_order_new_v1', $repo->active( 'order_new', 'en_US' )['name'] );
	}

	public function test_paused_active_template_stops_sending(): void {
		$repo = new TemplateRepository();
		$repo->set_submitted( 'stock_low', 'en_US', self::version( 'ordervox_stock_low_v1', 1, 'APPROVED' ) );

		$repo->apply_status( 'ordervox_stock_low_v1', 'en_US', 'PAUSED' );

		$this->assertNull( $repo->active( 'stock_low', 'en_US' ) );
	}

	public function test_unknown_name_and_other_language_are_ignored(): void {
		$repo = new TemplateRepository();
		$repo->set_submitted( 'order_new', 'en_US', self::version( 'ordervox_order_new_v1', 1, 'PENDING' ) );

		$this->assertFalse( $repo->apply_status( 'someone_else', 'en_US', 'APPROVED' ) );
		$this->assertFalse( $repo->apply_status( 'ordervox_order_new_v1', 'fr', 'APPROVED' ) );
		$this->assertNull( $repo->active( 'order_new', 'en_US' ) );
	}
}

<?php
/**
 * Tests for Consent.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use Webbcode\Ordervox\Customer\Consent;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryOptOuts;

/**
 * Covers when a customer may be messaged, and recording consent.
 */
final class ConsentTest extends TestCase {

	private const PHONE = '+2348012345678';

	private InMemoryOptOuts $optouts;
	private Consent $consent;

	/**
	 * User meta store.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $user_meta = array();

	protected function set_up(): void {
		parent::set_up();
		$this->user_meta = array();

		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->justReturn( array( 'customer' => array( 'optin_enabled' => true ) ) );
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		Functions\when( 'get_user_meta' )->alias( fn( $id, $key ) => $this->user_meta[ $id ][ $key ] ?? '' );
		Functions\when( 'update_user_meta' )->alias(
			function ( $id, $key, $value ) {
				$this->user_meta[ $id ][ $key ] = $value;
				return true;
			}
		);

		$this->optouts = new InMemoryOptOuts();
		$this->consent = new Consent( new Settings(), $this->optouts );
	}

	/**
	 * Order double with the given meta.
	 *
	 * @param array<string, string> $meta        Order meta.
	 * @param int                   $customer_id Customer id.
	 * @param string                $phone       Billing phone.
	 */
	private function order( array $meta, int $customer_id = 0, string $phone = self::PHONE ): \WC_Order {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->andReturnUsing( static fn( $key ) => $meta[ $key ] ?? '' );
		$order->shouldReceive( 'get_billing_phone' )->andReturn( $phone );
		$order->shouldReceive( 'get_billing_country' )->andReturn( 'NG' );
		$order->shouldReceive( 'get_customer_id' )->andReturn( $customer_id );
		return $order;
	}

	public function test_no_opt_in_means_no_messages(): void {
		$this->assertNull( $this->consent->recipient( $this->order( array() ) ) );
		$this->assertNull( $this->consent->recipient( $this->order( array( Consent::ORDER_OPTIN => 'no' ) ) ) );
	}

	public function test_opted_in_order_returns_phone(): void {
		$this->assertSame( self::PHONE, $this->consent->recipient( $this->order( array( Consent::ORDER_OPTIN => 'yes' ) ) ) );
	}

	public function test_invalid_phone_blocks_messages(): void {
		$this->assertNull( $this->consent->recipient( $this->order( array( Consent::ORDER_OPTIN => 'yes' ), 0, '12' ) ) );
	}

	public function test_stop_overrides_order_consent(): void {
		$this->optouts->add( self::PHONE, 'keyword' );
		$this->assertNull( $this->consent->recipient( $this->order( array( Consent::ORDER_OPTIN => 'yes' ) ) ) );
	}

	public function test_account_switch_off_overrides_order_consent(): void {
		$this->user_meta[7][ Consent::USER_OPTIN ] = 'no';
		$this->assertNull( $this->consent->recipient( $this->order( array( Consent::ORDER_OPTIN => 'yes' ), 7 ) ) );
	}

	public function test_marketing_needs_its_own_consent(): void {
		$order = $this->order( array( Consent::ORDER_OPTIN => 'yes' ) );
		$this->assertTrue( $this->consent->allows( $order ) );
		$this->assertFalse( $this->consent->allows( $order, true ) );

		$order = $this->order(
			array(
				Consent::ORDER_OPTIN     => 'yes',
				Consent::ORDER_MARKETING => 'yes',
			)
		);
		$this->assertTrue( $this->consent->allows( $order, true ) );
	}

	public function test_record_stores_proof_and_clears_earlier_stop(): void {
		$this->optouts->add( self::PHONE, 'keyword' );

		$saved = array();
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'update_meta_data' )->andReturnUsing(
			static function ( $key, $value ) use ( &$saved ) {
				$saved[ $key ] = $value;
			}
		);
		$order->shouldReceive( 'get_customer_id' )->andReturn( 7 );
		$order->shouldReceive( 'get_billing_phone' )->andReturn( self::PHONE );
		$order->shouldReceive( 'get_billing_country' )->andReturn( 'NG' );

		$this->consent->record( $order, true, false, 'block_checkout' );

		$this->assertSame( 'yes', $saved[ Consent::ORDER_OPTIN ] );
		$this->assertSame( 'no', $saved[ Consent::ORDER_MARKETING ] );
		$this->assertSame( 'Send me order updates on WhatsApp', $saved[ Consent::ORDER_TEXT ] );
		$this->assertSame( 'block_checkout', $saved[ Consent::ORDER_SOURCE ] );
		$this->assertSame( 64, strlen( $saved[ Consent::ORDER_IP_HASH ] ) );
		$this->assertSame( 'yes', $this->user_meta[7][ Consent::USER_OPTIN ] );
		$this->assertFalse( $this->optouts->is_opted_out( self::PHONE ) );
	}

	public function test_declining_records_no_and_nothing_else(): void {
		$saved = array();
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'update_meta_data' )->andReturnUsing(
			static function ( $key, $value ) use ( &$saved ) {
				$saved[ $key ] = $value;
			}
		);

		$this->consent->record( $order, false, true, 'classic_checkout' );

		$this->assertSame(
			array(
				Consent::ORDER_OPTIN     => 'no',
				Consent::ORDER_MARKETING => 'no',
			),
			$saved
		);
	}
}

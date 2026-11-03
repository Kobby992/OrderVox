<?php
/**
 * Tests for the webhook Processor.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryMessages;
use Webbcode\Ordervox\Webhook\Processor;

/**
 * Covers delivery status updates, dedupe, inbound messages and template updates.
 */
final class ProcessorTest extends TestCase {

	private InMemoryMessages $messages;
	private Processor $processor;

	/**
	 * Event keys already seen by the dedupe store.
	 *
	 * @var array<string, bool>
	 */
	private array $seen = array();

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'update_option' )->justReturn( true );

		$this->seen      = array();
		$this->messages  = new InMemoryMessages();
		$this->processor = new Processor(
			$this->messages,
			function ( string $key ): bool {
				if ( isset( $this->seen[ $key ] ) ) {
					return false;
				}
				$this->seen[ $key ] = true;
				return true;
			}
		);

		$this->messages->insert(
			array(
				'idempotency_key' => 'k1',
				'status'          => 'sent',
				'wamid'           => 'wamid.1',
			)
		);
	}

	/**
	 * Webhook body with one `messages` change.
	 *
	 * @param array<string, mixed> $value Change value.
	 * @return array<string, mixed>
	 */
	private static function payload( array $value, string $field = 'messages' ): array {
		return array(
			'object' => 'whatsapp_business_account',
			'entry'  => array(
				array(
					'id'      => '222',
					'changes' => array(
						array(
							'field' => $field,
							'value' => $value,
						),
					),
				),
			),
		);
	}

	/**
	 * Status object.
	 *
	 * @return array<string, mixed>
	 */
	private static function status( string $state, array $extra = array() ): array {
		return array_merge(
			array(
				'id'           => 'wamid.1',
				'status'       => $state,
				'timestamp'    => '1762160000',
				'recipient_id' => '2348012345678',
			),
			$extra
		);
	}

	public function test_statuses_move_forward_only(): void {
		$this->processor->process( self::payload( array( 'statuses' => array( self::status( 'read' ) ) ) ) );
		$this->processor->process( self::payload( array( 'statuses' => array( self::status( 'delivered' ) ) ) ) );

		$row = $this->messages->find( 1 );
		$this->assertSame( 'read', $row['status'] );
		$this->assertSame( '2025-11-03 08:53:20', $row['read_at'] );
		$this->assertNotEmpty( $row['delivered_at'] );
	}

	public function test_failed_status_records_error_and_pricing(): void {
		Actions\expectDone( 'ordervox_message_status_changed' )->once();

		$this->processor->process(
			self::payload(
				array(
					'statuses' => array(
						self::status(
							'failed',
							array(
								'errors'  => array(
									array(
										'code'  => 131026,
										'title' => 'Message undeliverable',
									),
								),
								'pricing' => array( 'category' => 'utility' ),
							)
						),
					),
				)
			)
		);

		$row = $this->messages->find( 1 );
		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( '131026', $row['error_code'] );
		$this->assertStringContainsString( 'could not be delivered', $row['error_message'] );
		$this->assertSame( 'utility', $row['pricing_category'] );
	}

	public function test_duplicate_deliveries_are_ignored(): void {
		Actions\expectDone( 'ordervox_message_status_changed' )->once();

		$payload = self::payload( array( 'statuses' => array( self::status( 'delivered' ) ) ) );
		$this->processor->process( $payload );
		$this->processor->process( $payload );

		$this->assertSame( 'delivered', $this->messages->find( 1 )['status'] );
	}

	public function test_unknown_wamid_is_ignored(): void {
		$this->processor->process( self::payload( array( 'statuses' => array( self::status( 'read', array( 'id' => 'wamid.other' ) ) ) ) ) );
		$this->assertSame( 'sent', $this->messages->find( 1 )['status'] );
	}

	public function test_inbound_button_reply_fires_actions_and_opens_window(): void {
		Functions\expect( 'set_transient' )->once()->with( 'ordervox_window_' . md5( '+2348012345678' ), \Mockery::type( 'int' ), DAY_IN_SECONDS );
		Actions\expectDone( 'ordervox_inbound_message' )->once();
		Actions\expectDone( 'ordervox_button_reply' )->once()->with( 'act:ship:1001:abc', '2348012345678', \Mockery::type( 'array' ) );

		$this->processor->process(
			self::payload(
				array(
					'messages' => array(
						array(
							'from'      => '2348012345678',
							'id'        => 'wamid.in1',
							'timestamp' => '1762160000',
							'type'      => 'button',
							'button'    => array(
								'payload' => 'act:ship:1001:abc',
								'text'    => 'Mark shipped',
							),
						),
					),
				)
			)
		);
	}

	public function test_template_status_update_clears_cache(): void {
		Functions\expect( 'delete_transient' )->once()->with( 'ordervox_templates' );
		Actions\expectDone( 'ordervox_template_status_update' )->once();

		$this->processor->process(
			self::payload(
				array(
					'event'                 => 'APPROVED',
					'message_template_name' => 'ordervox_new_order_v1',
				),
				'message_template_status_update'
			)
		);
	}
}

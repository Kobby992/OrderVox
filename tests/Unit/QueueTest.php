<?php
/**
 * Tests for Dispatcher and Queue.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Functions;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Messaging\Dispatcher;
use Webbcode\Ordervox\Messaging\OutboundMessage;
use Webbcode\Ordervox\Messaging\Queue;
use Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryMessages;

/**
 * Covers dedupe, sending, retries with backoff, and permanent failures.
 */
final class QueueTest extends HttpTestCase {

	private InMemoryMessages $messages;
	private Queue $queue;
	private Dispatcher $dispatcher;

	/**
	 * Calls to as_schedule_single_action: [timestamp, hook, args].
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $scheduled = array();

	protected function set_up(): void {
		parent::set_up();

		$this->scheduled = array();
		Functions\when( 'as_enqueue_async_action' )->justReturn( 1 );
		Functions\when( 'as_schedule_single_action' )->alias(
			function ( ...$args ) {
				$this->scheduled[] = $args;
				return 1;
			}
		);

		$api              = new WhatsAppApi(
			$this->credentials(
				array(
					'phone_number_id' => '111',
					'waba_id'         => '222',
					'access_token'    => 'EAAG-token',
				)
			)
		);
		$this->messages   = new InMemoryMessages();
		$this->queue      = new Queue( $this->messages, $api );
		$this->dispatcher = new Dispatcher( $this->messages, $this->queue );
	}

	private function message( string $key = '' ): OutboundMessage {
		return new OutboundMessage( 'order.new', '+2348012345678', 'ordervox_new_order_v1', 'en_US', array(), 'owner', 'order', 1001, $key );
	}

	public function test_same_message_is_only_logged_once(): void {
		$first  = $this->dispatcher->dispatch( $this->message( 'order.new:1001' ) );
		$second = $this->dispatcher->dispatch( $this->message( 'order.new:1001' ) );

		$this->assertSame( 1, $first );
		$this->assertNull( $second );
		$this->assertCount( 1, $this->messages->rows );
	}

	public function test_veto_filter_stops_dispatch(): void {
		\Brain\Monkey\Filters\expectApplied( 'ordervox_should_send' )->once()->andReturn( false );
		$this->assertNull( $this->dispatcher->dispatch( $this->message() ) );
		$this->assertCount( 0, $this->messages->rows );
	}

	public function test_successful_send_marks_row_sent(): void {
		$id                = $this->dispatcher->dispatch( $this->message() );
		$this->responses[] = array( 200, array( 'messages' => array( array( 'id' => 'wamid.OK' ) ) ) );

		$row = $this->queue->process( $id );

		$this->assertSame( 'sent', $row['status'] );
		$this->assertSame( 'wamid.OK', $row['wamid'] );
		$this->assertSame( 1, $row['attempts'] );
		$this->assertNotEmpty( $row['sent_at'] );
	}

	public function test_temporary_failure_is_retried_with_backoff(): void {
		$id                = $this->dispatcher->dispatch( $this->message() );
		$this->responses[] = array( 503, array() );

		$row = $this->queue->process( $id );

		$this->assertSame( 'queued', $row['status'] );
		$this->assertCount( 1, $this->scheduled );
		$this->assertEqualsWithDelta( time() + Queue::BACKOFF[0], $this->scheduled[0][0], 2 );
		$this->assertSame( Queue::HOOK, $this->scheduled[0][1] );
		$this->assertSame( array( 'log_id' => $id ), $this->scheduled[0][2] );
	}

	public function test_gives_up_after_last_backoff(): void {
		$id = $this->dispatcher->dispatch( $this->message() );
		$this->messages->update( $id, array( 'attempts' => count( Queue::BACKOFF ) ) );
		$this->responses[] = array( 503, array() );

		$row = $this->queue->process( $id );

		$this->assertSame( 'failed', $row['status'] );
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_permanent_failure_is_not_retried(): void {
		$id                = $this->dispatcher->dispatch( $this->message() );
		$this->responses[] = array(
			400,
			array(
				'error' => array(
					'code'    => 132001,
					'message' => 'Template name does not exist in the translation',
				),
			),
		);

		$row = $this->queue->process( $id );

		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( '132001', $row['error_code'] );
		$this->assertStringContainsString( 'template does not exist', $row['error_message'] );
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_customer_who_unsubscribed_is_not_messaged(): void {
		Functions\when( '__' )->returnArg();
		$optouts = new \Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryOptOuts();
		$queue   = new Queue(
			$this->messages,
			new WhatsAppApi(
				$this->credentials(
					array(
						'phone_number_id' => '111',
						'access_token'    => 'EAAG-token',
					)
				)
			),
			$optouts
		);
		$id = ( new Dispatcher( $this->messages, $queue ) )->dispatch(
			new OutboundMessage( 'customer_order_confirmed', '+2348012345678', 'ordervox_customer_order_confirmed_v1', 'en_US', array(), 'customer', 'order', 1001 )
		);
		$optouts->add( '+2348012345678', 'keyword' );

		$row = $queue->process( $id );

		$this->assertSame( 'cancelled', $row['status'] );
		$this->assertCount( 0, $this->requests );
	}

	public function test_already_sent_row_is_not_sent_again(): void {
		$id = $this->dispatcher->dispatch( $this->message() );
		$this->messages->update( $id, array( 'status' => 'sent' ) );

		$this->queue->process( $id );

		$this->assertCount( 0, $this->requests );
	}
}

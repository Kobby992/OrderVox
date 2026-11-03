<?php
/**
 * Tests for STOP/START keywords.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Customer\Keywords;
use Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryMessages;
use Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryOptOuts;

/**
 * Covers keyword matching, opting out and back in, and cancelling queued messages.
 */
final class KeywordsTest extends HttpTestCase {

	private InMemoryOptOuts $optouts;
	private InMemoryMessages $messages;
	private Keywords $keywords;

	protected function set_up(): void {
		parent::set_up();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Shop' );
		Functions\when( 'wp_specialchars_decode' )->returnArg();

		$this->optouts  = new InMemoryOptOuts();
		$this->messages = new InMemoryMessages();
		$this->keywords = new Keywords(
			$this->optouts,
			$this->messages,
			new WhatsAppApi(
				$this->credentials(
					array(
						'phone_number_id' => '111',
						'access_token'    => 'EAAG-token',
					)
				)
			)
		);
	}

	private static function text( string $body ): array {
		return array(
			'from' => '2348012345678',
			'id'   => 'wamid.in',
			'type' => 'text',
			'text' => array( 'body' => $body ),
		);
	}

	/**
	 * @dataProvider stop_words
	 */
	public function test_stop_variants( string $body ): void {
		$this->responses[] = array( 200, array( 'messages' => array( array( 'id' => 'wamid.reply' ) ) ) );
		$this->assertSame( 'stop', $this->keywords->handle( self::text( $body ) ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function stop_words(): array {
		return array(
			'plain'       => array( 'STOP' ),
			'lower case'  => array( ' stop ' ),
			'punctuation' => array( 'Stop!' ),
			'opt-out'     => array( 'opt-out' ),
			'french'      => array( 'Arrêt' ),
		);
	}

	public function test_stop_opts_out_cancels_queue_and_confirms(): void {
		Actions\expectDone( 'ordervox_customer_opted_out' )->once()->with( '+2348012345678' );
		$this->messages->insert(
			array(
				'idempotency_key' => 'a',
				'recipient_phone' => '+2348012345678',
				'status'          => 'queued',
			)
		);
		$this->messages->insert(
			array(
				'idempotency_key' => 'b',
				'recipient_phone' => '+2348012345678',
				'status'          => 'sent',
			)
		);
		$this->responses[] = array( 200, array( 'messages' => array( array( 'id' => 'wamid.reply' ) ) ) );

		$this->keywords->handle( self::text( 'STOP' ) );

		$this->assertTrue( $this->optouts->is_opted_out( '+2348012345678' ) );
		$this->assertSame( 'cancelled', $this->messages->find( 1 )['status'] );
		$this->assertSame( 'sent', $this->messages->find( 2 )['status'] );

		$body = json_decode( $this->requests[0]['args']['body'], true );
		$this->assertSame( 'text', $body['type'] );
		$this->assertStringContainsString( 'Reply START', $body['text']['body'] );
	}

	public function test_start_opts_back_in(): void {
		$this->optouts->add( '+2348012345678', 'keyword' );
		$this->responses[] = array( 200, array( 'messages' => array( array( 'id' => 'wamid.reply' ) ) ) );

		$this->assertSame( 'start', $this->keywords->handle( self::text( 'start' ) ) );
		$this->assertFalse( $this->optouts->is_opted_out( '+2348012345678' ) );
	}

	public function test_stop_is_honoured_when_reply_fails(): void {
		$this->responses[] = array( 500, array() );

		$this->assertSame( 'stop', $this->keywords->handle( self::text( 'STOP' ) ) );
		$this->assertTrue( $this->optouts->is_opted_out( '+2348012345678' ) );
	}

	public function test_other_messages_are_ignored(): void {
		$this->assertNull( $this->keywords->handle( self::text( 'Please stop by tomorrow' ) ) );
		$this->assertNull( $this->keywords->handle( array( 'from' => '234', 'type' => 'image' ) ) );
		$this->assertSame( array(), $this->optouts->hashes );
		$this->assertCount( 0, $this->requests );
	}
}

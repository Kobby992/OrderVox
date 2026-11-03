<?php
/**
 * Tests for EventSender.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Webbcode\Ordervox\Api\WhatsAppApi;
use Webbcode\Ordervox\Events\Context;
use Webbcode\Ordervox\Events\EventSender;
use Webbcode\Ordervox\Messaging\Dispatcher;
use Webbcode\Ordervox\Messaging\Queue;
use Webbcode\Ordervox\Placeholders\Registry;
use Webbcode\Ordervox\Settings\Credentials;
use Webbcode\Ordervox\Settings\Settings;
use Webbcode\Ordervox\Support\Crypto;
use Webbcode\Ordervox\Templates\TemplateRepository;
use Webbcode\Ordervox\Tests\Unit\Fakes\InMemoryMessages;

/**
 * Covers alert switches, missing templates, variable filling and recipients.
 */
final class EventSenderTest extends TestCase {

	/**
	 * Fake option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	private InMemoryMessages $messages;

	protected function set_up(): void {
		parent::set_up();

		$this->options = array(
			Settings::OPTION => array(
				'owner_phone' => '+2348012345678',
				'events'      => array( 'review_new' => true ),
			),
		);

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
		Functions\when( 'admin_url' )->alias( static fn( $path = '' ) => 'https://shop.test/wp-admin/' . $path );
		Functions\when( 'home_url' )->alias( static fn( $path = '' ) => 'https://shop.test' . $path );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Shop' );
		Functions\when( 'wp_specialchars_decode' )->returnArg();
		Functions\when( 'wp_date' )->justReturn( 'November 3, 2025' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'as_enqueue_async_action' )->justReturn( 1 );
		Functions\when( 'get_option' )->alias( fn( $name, $fallback = false ) => $this->options[ $name ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->options[ $name ] = $value;
				return true;
			}
		);

		$this->messages = new InMemoryMessages();
	}

	private function sender( TemplateRepository $templates ): EventSender {
		$queue = new Queue( $this->messages, new WhatsAppApi( new Credentials( new Crypto( str_repeat( 'k', 32 ) ) ) ) );
		return new EventSender( new Settings(), $templates, new Registry(), new Dispatcher( $this->messages, $queue ), $this->messages );
	}

	private function approved_templates(): TemplateRepository {
		$templates = new TemplateRepository();
		$templates->set_submitted(
			'review_new',
			'en_US',
			array(
				'name'     => 'ordervox_review_new_v1',
				'language' => 'en_US',
				'keys'     => array( 'store_name', 'review_author' ),
				'status'   => 'APPROVED',
				'version'  => 1,
			)
		);
		return $templates;
	}

	public function test_disabled_alert_sends_nothing(): void {
		$sent = $this->sender( $this->approved_templates() )->send( 'status_cancelled', new Context(), 'order', 1001 );

		$this->assertSame( 0, $sent );
		$this->assertCount( 0, $this->messages->rows );
	}

	public function test_missing_template_is_logged_as_failed(): void {
		$sent = $this->sender( new TemplateRepository() )->send( 'review_new', new Context(), 'review', 7 );

		$this->assertSame( 0, $sent );
		$row = $this->messages->find( 1 );
		$this->assertSame( 'failed', $row['status'] );
		$this->assertStringContainsString( 'not approved yet', $row['error_message'] );
		$this->assertSame( 'review', $row['object_type'] );
	}

	public function test_fills_variables_in_template_order(): void {
		$sent = $this->sender( $this->approved_templates() )->send( 'review_new', new Context(), 'review', 7 );

		$this->assertSame( 1, $sent );
		$row     = $this->messages->find( 1 );
		$payload = json_decode( $row['payload'], true );

		$this->assertSame( 'queued', $row['status'] );
		$this->assertSame( 'ordervox_review_new_v1', $row['template_name'] );
		$this->assertSame( '+2348012345678', $row['recipient_phone'] );
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Test Shop',
				),
				array(
					'type' => 'text',
					'text' => '-',
				),
			),
			$payload['components'][0]['parameters']
		);
	}

	public function test_same_event_is_not_sent_twice(): void {
		$sender = $this->sender( $this->approved_templates() );
		$sender->send( 'review_new', new Context(), 'review', 7 );
		$sender->send( 'review_new', new Context(), 'review', 7 );

		$this->assertCount( 1, $this->messages->rows );
	}

	public function test_recipients_filter_adds_team_members(): void {
		Filters\expectApplied( 'ordervox_recipients' )->once()->andReturnUsing(
			static fn( array $recipients ) => array_merge(
				$recipients,
				array(
					array(
						'phone' => '+14155550123',
						'type'  => 'team',
					),
					array( 'phone' => '' ),
				)
			)
		);

		$sent = $this->sender( $this->approved_templates() )->send( 'review_new', new Context(), 'review', 7 );

		$this->assertSame( 2, $sent );
		$this->assertSame( array( '+2348012345678', '+14155550123' ), array_column( $this->messages->rows, 'recipient_phone' ) );
	}
}

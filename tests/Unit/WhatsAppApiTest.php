<?php
/**
 * Tests for WhatsAppApi and GraphClient.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Webbcode\Ordervox\Api\ApiException;
use Webbcode\Ordervox\Api\GraphClient;
use Webbcode\Ordervox\Api\WhatsAppApi;

/**
 * Covers request building, response parsing and error mapping.
 */
final class WhatsAppApiTest extends HttpTestCase {

	private function api(): WhatsAppApi {
		return new WhatsAppApi(
			$this->credentials(
				array(
					'phone_number_id' => '111',
					'waba_id'         => '222',
					'access_token'    => 'EAAG-token',
					'app_id'          => '333',
					'app_secret'      => 'shh',
				)
			)
		);
	}

	public function test_send_template_builds_cloud_api_request(): void {
		$this->responses[] = array(
			200,
			array(
				'messaging_product' => 'whatsapp',
				'contacts'          => array( array( 'wa_id' => '2348012345678' ) ),
				'messages'          => array( array( 'id' => 'wamid.ABC' ) ),
			),
		);

		$result = $this->api()->send_template(
			'+2348012345678',
			'ordervox_new_order_v1',
			'en_US',
			array(
				array(
					'type'       => 'body',
					'parameters' => array(
						array(
							'type' => 'text',
							'text' => '1001',
						),
					),
				),
			)
		);

		$this->assertSame( 'wamid.ABC', $result['wamid'] );
		$this->assertSame( '2348012345678', $result['wa_id'] );

		$request = $this->requests[0];
		$this->assertSame( 'https://graph.facebook.com/' . GraphClient::VERSION . '/111/messages', $request['url'] );
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'Bearer EAAG-token', $request['args']['headers']['Authorization'] );

		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( 'whatsapp', $body['messaging_product'] );
		$this->assertSame( '2348012345678', $body['to'] );
		$this->assertSame( 'template', $body['type'] );
		$this->assertSame( 'ordervox_new_order_v1', $body['template']['name'] );
		$this->assertSame( array( 'code' => 'en_US' ), $body['template']['language'] );
		$this->assertSame( '1001', $body['template']['components'][0]['parameters'][0]['text'] );
	}

	public function test_meta_error_becomes_api_exception(): void {
		$this->responses[] = array(
			401,
			array(
				'error' => array(
					'message' => 'Error validating access token',
					'code'    => 190,
				),
			),
		);

		try {
			$this->api()->phone_number_info();
			$this->fail( 'Expected ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 190, $e->meta_code() );
			$this->assertSame( 401, $e->http_status() );
			$this->assertFalse( $e->is_retryable() );
		}
	}

	public function test_transport_error_is_retryable(): void {
		$this->responses[] = $this->wp_error( 'cURL error 28: Operation timed out' );

		try {
			$this->api()->send_text( '+2348012345678', 'hi' );
			$this->fail( 'Expected ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertTrue( $e->is_retryable() );
			$this->assertStringContainsString( 'timed out', $e->getMessage() );
		}
	}

	public function test_templates_follow_paging_cursors(): void {
		$this->responses[] = array(
			200,
			array(
				'data'   => array( array( 'name' => 'a' ) ),
				'paging' => array(
					'cursors' => array( 'after' => 'CUR' ),
					'next'    => 'https://graph.facebook.com/next',
				),
			),
		);
		$this->responses[] = array(
			200,
			array(
				'data'   => array( array( 'name' => 'b' ) ),
				'paging' => array( 'cursors' => array( 'after' => 'END' ) ),
			),
		);

		$templates = $this->api()->templates();

		$this->assertSame( array( 'a', 'b' ), array_column( $templates, 'name' ) );
		$this->assertCount( 2, $this->requests );
		$this->assertStringContainsString( '/222/message_templates', $this->requests[0]['url'] );
		$this->assertStringContainsString( 'after=CUR', $this->requests[1]['url'] );
	}

	public function test_debug_token_uses_app_token(): void {
		$this->responses[] = array(
			200,
			array(
				'data' => array(
					'is_valid'   => true,
					'expires_at' => 0,
				),
			),
		);

		$data = $this->api()->debug_token();

		$this->assertTrue( $data['is_valid'] );
		$this->assertSame( 'Bearer 333|shh', $this->requests[0]['args']['headers']['Authorization'] );
		$this->assertStringContainsString( 'input_token=EAAG-token', $this->requests[0]['url'] );
	}

	public function test_missing_ids_fail_before_any_request(): void {
		$api = new WhatsAppApi( $this->credentials( array( 'access_token' => 'EAAG-token' ) ) );

		$this->expectException( ApiException::class );
		try {
			$api->send_text( '+2348012345678', 'hi' );
		} finally {
			$this->assertCount( 0, $this->requests );
		}
	}
}

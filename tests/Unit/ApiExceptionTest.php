<?php
/**
 * Tests for ApiException.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Webbcode\Ordervox\Api\ApiException;

/**
 * Covers parsing Meta error bodies and retry classification.
 */
final class ApiExceptionTest extends TestCase {

	public function test_parses_meta_error_body(): void {
		$e = ApiException::from_response(
			array(
				'error' => array(
					'message'    => '(#131030) Recipient phone number not in allowed list',
					'code'       => 131030,
					'error_data' => array( 'details' => 'Recipient phone number not in allowed list' ),
				),
			),
			400
		);

		$this->assertSame( 131030, $e->meta_code() );
		$this->assertSame( 400, $e->http_status() );
		$this->assertSame( 'Recipient phone number not in allowed list', $e->getMessage() );
		$this->assertStringContainsString( 'test recipient list', $e->hint() );
		$this->assertFalse( $e->is_retryable() );
	}

	public function test_unknown_code_falls_back_to_meta_message(): void {
		$e = new ApiException( 'Something odd', 999999, 400 );
		$this->assertSame( 'Something odd', $e->hint() );
	}

	/**
	 * @dataProvider retry_cases
	 */
	public function test_retry_classification( int $code, int $http, bool $expected ): void {
		$this->assertSame( $expected, ( new ApiException( 'x', $code, $http ) )->is_retryable() );
	}

	/**
	 * @return array<string, array{int, int, bool}>
	 */
	public static function retry_cases(): array {
		return array(
			'no response'        => array( 0, 0, true ),
			'server error'       => array( 0, 503, true ),
			'http 429'           => array( 0, 429, true ),
			'throughput limit'   => array( 130429, 400, true ),
			'spam rate limit'    => array( 131048, 400, true ),
			'invalid token'      => array( 190, 401, false ),
			'template missing'   => array( 132001, 404, false ),
			'undeliverable'      => array( 131026, 400, false ),
			'invalid parameter'  => array( 100, 400, false ),
		);
	}
}

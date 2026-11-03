<?php
/**
 * Tests for Phone.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Webbcode\Ordervox\Support\Phone;

/**
 * Covers E.164 normalisation and masking.
 */
final class PhoneTest extends TestCase {

	/**
	 * @dataProvider numbers
	 */
	public function test_to_e164( string $raw, string $calling_code, ?string $expected ): void {
		$this->assertSame( $expected, Phone::to_e164( $raw, $calling_code ) );
	}

	/**
	 * @return array<string, array{string, string, ?string}>
	 */
	public static function numbers(): array {
		return array(
			'international with spaces'   => array( '+234 801 234 5678', '1', '+2348012345678' ),
			'00 prefix'                   => array( '00447911123456', '1', '+447911123456' ),
			'national with trunk 0'       => array( '0801 234 5678', '234', '+2348012345678' ),
			'national without trunk'      => array( '(202) 555-0123', '1', '+12025550123' ),
			'already has country code'    => array( '12025550123', '1', '+12025550123' ),
			'ghana national'              => array( '024 123 4567', '233', '+233241234567' ),
			'national needs calling code' => array( '0801 234 5678', '', null ),
			'too short'                   => array( '+12345', '1', null ),
			'too long'                    => array( '+1234567890123456', '1', null ),
			'empty'                       => array( '', '1', null ),
			'letters only'                => array( 'call me', '1', null ),
		);
	}

	public function test_mask_hides_middle_digits(): void {
		$this->assertSame( '+23480****5678', Phone::mask( '+2348012345678' ) );
		$this->assertSame( '+12025**0123', Phone::mask( '+12025550123' ) );
		$this->assertSame( '+1234', Phone::mask( '+1234' ) );
	}
}

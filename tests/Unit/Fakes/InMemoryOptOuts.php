<?php
/**
 * In-memory opt-out list for unit tests.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit\Fakes;

use Webbcode\Ordervox\Customer\OptOuts;

/**
 * Keeps hashes in an array instead of the database.
 */
final class InMemoryOptOuts extends OptOuts {

	/**
	 * Opted-out hashes mapped to their source.
	 *
	 * @var array<string, string>
	 */
	public array $hashes = array();

	public function is_opted_out( string $e164 ): bool {
		return isset( $this->hashes[ self::hash( $e164 ) ] );
	}

	public function add( string $e164, string $source ): void {
		$this->hashes[ self::hash( $e164 ) ] = $source;
	}

	public function remove( string $e164 ): void {
		unset( $this->hashes[ self::hash( $e164 ) ] );
	}
}

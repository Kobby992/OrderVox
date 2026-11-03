<?php
/**
 * In-memory message log for unit tests.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit\Fakes;

use Webbcode\Ordervox\Log\MessageRepository;

/**
 * Stores rows in an array instead of the database.
 */
final class InMemoryMessages extends MessageRepository {

	/**
	 * Rows keyed by id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = array();

	public function insert( array $row ): int {
		foreach ( $this->rows as $existing ) {
			if ( $existing['idempotency_key'] === ( $row['idempotency_key'] ?? null ) ) {
				return 0;
			}
		}
		$id                = count( $this->rows ) + 1;
		$this->rows[ $id ] = array_merge(
			array(
				'id'            => $id,
				'status'        => 'queued',
				'attempts'      => 0,
				'wamid'         => null,
				'payload'       => '',
				'error_code'    => null,
				'error_message' => null,
				'delivered_at'  => null,
				'read_at'       => null,
			),
			$row
		);
		return $id;
	}

	public function update( int $id, array $fields ): void {
		$this->rows[ $id ] = array_merge( $this->rows[ $id ], $fields );
	}

	public function find( int $id ): ?array {
		return $this->rows[ $id ] ?? null;
	}

	public function cancel_queued_for( string $e164 ): int {
		$cancelled = 0;
		foreach ( $this->rows as $id => $row ) {
			if ( $row['recipient_phone'] === $e164 && 'queued' === $row['status'] ) {
				$this->rows[ $id ]['status'] = 'cancelled';
				++$cancelled;
			}
		}
		return $cancelled;
	}

	public function find_by_wamid( string $wamid ): ?array {
		foreach ( $this->rows as $row ) {
			if ( $row['wamid'] === $wamid ) {
				return $row;
			}
		}
		return null;
	}
}

<?php
/**
 * Message log storage.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Log;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table owned by this plugin; caching would serve stale delivery statuses.

/**
 * Reads and writes rows in `{prefix}ordervox_messages`.
 *
 * Not final so tests can substitute an in-memory version.
 */
class MessageRepository {

	/**
	 * Delivery statuses in the order they progress. `failed` is handled separately.
	 */
	public const STATUS_RANK = array(
		'queued'    => 0,
		'sending'   => 1,
		'sent'      => 2,
		'delivered' => 3,
		'read'      => 4,
	);

	/**
	 * Full table name.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ordervox_messages';
	}

	/**
	 * Inserts a row. Returns the new id, or 0 when a row with the same idempotency key exists.
	 *
	 * @param array<string, mixed> $row Column values.
	 */
	public function insert( array $row ): int {
		global $wpdb;

		$now = self::now();
		$row = array_merge(
			array(
				'status'     => 'queued',
				'created_at' => $now,
				'updated_at' => $now,
			),
			$row
		);

		$suppress = $wpdb->suppress_errors( true );
		$inserted = $wpdb->insert( $this->table(), $row );
		$wpdb->suppress_errors( $suppress );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Updates a row and bumps `updated_at`.
	 *
	 * @param int                  $id     Row id.
	 * @param array<string, mixed> $fields Column values.
	 */
	public function update( int $id, array $fields ): void {
		global $wpdb;
		$fields['updated_at'] = self::now();
		$wpdb->update( $this->table(), $fields, array( 'id' => $id ) );
	}

	/**
	 * One row by id.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * One row by Meta message id.
	 *
	 * @param string $wamid Meta message id.
	 * @return array<string, mixed>|null
	 */
	public function find_by_wamid( string $wamid ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE wamid = %s LIMIT 1', $this->table(), $wamid ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Paged list, newest first.
	 *
	 * @param array<string, mixed> $filters  Optional status, event, object_type, object_id.
	 * @param int                  $page     1-based page.
	 * @param int                  $per_page Rows per page.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function paginate( array $filters, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array( $this->table() );
		foreach ( array( 'status', 'event', 'object_type' ) as $column ) {
			if ( ! empty( $filters[ $column ] ) ) {
				$where[] = "{$column} = %s";
				$args[]  = (string) $filters[ $column ];
			}
		}
		if ( ! empty( $filters['object_id'] ) ) {
			$where[] = 'object_id = %d';
			$args[]  = (int) $filters['object_id'];
		}
		$where_sql = implode( ' AND ', $where );

		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql holds only fixed column names and placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", $args ) );
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- As above.
			$wpdb->prepare( "SELECT * FROM %i WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, $offset ) ) ),
			ARRAY_A
		);

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Row counts per status.
	 *
	 * @return array<string, int>
	 */
	public function count_by_status(): array {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', $this->table() ), ARRAY_A );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}
		return $counts;
	}

	/**
	 * Most recent failed row.
	 *
	 * @return array<string, mixed>|null
	 */
	public function latest_failed(): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE status = 'failed' ORDER BY id DESC LIMIT 1", $this->table() ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Cancels messages still waiting to be sent to a number.
	 *
	 * @param string $e164 Number in E.164.
	 * @return int Rows cancelled.
	 */
	public function cancel_queued_for( string $e164 ): int {
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'cancelled', error_message = %s, updated_at = %s WHERE recipient_phone = %s AND status = 'queued'",
				$this->table(),
				__( 'Cancelled because the customer unsubscribed.', 'ordervox' ),
				self::now(),
				$e164
			)
		);
	}

	/**
	 * Rows sent to a number, for privacy exports.
	 *
	 * @param string $e164  Number in E.164.
	 * @param int    $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_phone( string $e164, int $limit = 500 ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE recipient_phone = %s ORDER BY id DESC LIMIT %d', $this->table(), $e164, $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Deletes rows sent to a number, for privacy erasure.
	 *
	 * @param string $e164 Number in E.164.
	 * @return int Rows deleted.
	 */
	public function delete_for_phone( string $e164 ): int {
		global $wpdb;
		return (int) $wpdb->delete( $this->table(), array( 'recipient_phone' => $e164 ) );
	}

	/**
	 * Deletes rows older than the given number of days, in batches.
	 *
	 * @param int $days Retention in days. 0 keeps everything.
	 */
	public function purge_older_than( int $days ): int {
		global $wpdb;
		if ( $days <= 0 ) {
			return 0;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 500', $this->table(), $cutoff ) );
	}

	/**
	 * Current UTC time in MySQL format.
	 */
	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

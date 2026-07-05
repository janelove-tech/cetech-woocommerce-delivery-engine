<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Enum\RecordStatus;

/**
 * Shared helpers for wpdb-backed configuration repositories.
 */
abstract class AbstractWpdbRepository {

	abstract protected function table_suffix(): string;

	protected function table_name(): string {
		return TableNames::for( $this->table_suffix() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function fetch_row_by_id( int $id ): ?array {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function fetch_row_by_code( string $code ): ?array {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "SELECT * FROM `{$table}` WHERE internal_code = %s LIMIT 1";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $code ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $criteria
	 *
	 * @return list<array<string, mixed>>
	 */
	protected function fetch_list( array $criteria = [], int $limit = 100 ): array {
		global $wpdb;

		$table = $this->table_name();
		$where = '1=1';
		$args  = [];

		if ( isset( $criteria['status'] ) ) {
			$where .= ' AND status = %s';
			$args[] = (string) $criteria['status'];
		}

		$sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id ASC LIMIT %d";
		$args[] = max( 1, min( 500, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	protected function mark_inactive( int $id ): bool {
		global $wpdb;

		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->update(
			$table,
			[
				'status'     => RecordStatus::Inactive->value,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}
}

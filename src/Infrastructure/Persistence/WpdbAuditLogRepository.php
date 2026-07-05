<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Audit\AuditLogRepositoryInterface;

final class WpdbAuditLogRepository extends AbstractWpdbRepository implements AuditLogRepositoryInterface {

	protected function table_suffix(): string {
		return 'audit_log';
	}

	public function findById( int $id ): ?array {
		return $this->fetch_row_by_id( $id );
	}

	public function append( array $data ): int {
		global $wpdb;

		$table = $this->table_name();
		$now   = gmdate( 'Y-m-d H:i:s' );

		$row = [
			'actor_user_id'   => isset( $data['actor_user_id'] ) ? (int) $data['actor_user_id'] : null,
			'action'          => (string) ( $data['action'] ?? '' ),
			'entity_type'     => (string) ( $data['entity_type'] ?? '' ),
			'entity_id'       => isset( $data['entity_id'] ) ? (int) $data['entity_id'] : null,
			'previous_value'  => isset( $data['previous_value'] ) ? (string) $data['previous_value'] : null,
			'new_value'       => isset( $data['new_value'] ) ? (string) $data['new_value'] : null,
			'site_context'    => isset( $data['site_context'] ) ? (string) $data['site_context'] : null,
			'created_at'      => $now,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			$row,
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	public function list( array $criteria = [] ): array {
		global $wpdb;

		$table = $this->table_name();
		$where = '1=1';
		$args  = [];

		if ( isset( $criteria['entity_type'] ) ) {
			$where .= ' AND entity_type = %s';
			$args[] = (string) $criteria['entity_type'];
		}

		if ( isset( $criteria['entity_id'] ) ) {
			$where .= ' AND entity_id = %d';
			$args[] = (int) $criteria['entity_id'];
		}

		$limit = max( 1, min( 500, (int) ( $criteria['limit'] ?? 100 ) ) );
		$sql   = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id DESC LIMIT %d";
		$args[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}
}

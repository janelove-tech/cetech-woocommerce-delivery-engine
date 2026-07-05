<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;

final class WpdbOriginRepository extends AbstractWpdbRepository implements OriginRepositoryInterface {

	protected function table_suffix(): string {
		return 'origins';
	}

	public function findById( int $id ): ?array {
		return $this->fetch_row_by_id( $id );
	}

	public function findByCode( string $code ): ?array {
		return $this->fetch_row_by_code( $code );
	}

	public function save( array $data ): int {
		$this->throw_save_not_implemented();
	}

	public function list( array $criteria = [] ): array {
		if ( isset( $criteria['supplier_id'] ) ) {
			return $this->fetch_by_supplier( (int) $criteria['supplier_id'] );
		}

		return $this->fetch_list( $criteria );
	}

	public function deactivate( int $id ): bool {
		return $this->mark_inactive( $id );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function fetch_by_supplier( int $supplier_id ): array {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "SELECT * FROM `{$table}` WHERE supplier_id = %d ORDER BY id ASC LIMIT 100";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $supplier_id ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;

/**
 * Private operational origin persistence.
 *
 * Admin and infrastructure use only. Must not be read by customer-facing
 * templates, REST/Store API responses, emails, or checkout flows.
 */
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
		$now = gmdate( 'Y-m-d H:i:s' );
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$row = [
			'supplier_id'            => (int) ( $data['supplier_id'] ?? 0 ),
			'internal_code'          => (string) ( $data['internal_code'] ?? '' ),
			'internal_name'          => (string) ( $data['internal_name'] ?? '' ),
			'internal_address'       => $this->nullable_string( $data['internal_address'] ?? null ),
			'country_code'           => $this->nullable_country_code( $data['country_code'] ?? null ),
			'dispatch_lead_days_min' => $this->nullable_int( $data['dispatch_lead_days_min'] ?? null ),
			'dispatch_lead_days_max' => $this->nullable_int( $data['dispatch_lead_days_max'] ?? null ),
			'internal_notes'         => $this->nullable_string( $data['internal_notes'] ?? null ),
			'status'                 => (string) ( $data['status'] ?? 'active' ),
			'updated_at'             => $now,
		];

		$formats = [ '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ];

		if ( $id > 0 ) {
			if ( ! $this->update_row( $id, $row, $formats ) ) {
				return 0;
			}

			return $id;
		}

		$row['created_at'] = $now;
		$insert_formats    = array_merge( $formats, [ '%s' ] );

		$insert_id = $this->insert_row( $row, $insert_formats );

		return $insert_id > 0 ? $insert_id : 0;
	}

	public function list( array $criteria = [] ): array {
		if ( isset( $criteria['supplier_id'] ) ) {
			return $this->fetch_by_supplier( (int) $criteria['supplier_id'] );
		}

		return $this->fetch_list( $criteria, (int) ( $criteria['limit'] ?? 500 ) );
	}

	public function deactivate( int $id ): bool {
		return $this->mark_inactive( $id );
	}

	public function hardDelete( int $id ): bool {
		return $this->delete_row_by_id( $id );
	}

	public function count_all(): int {
		return parent::count_all();
	}

	public function countBySupplierId( int $supplier_id ): int {
		return $this->count_where( 'supplier_id', $supplier_id );
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

	private function nullable_string( mixed $value, ?int $max_length = null ): ?string {
		if ( null === $value ) {
			return null;
		}

		$string = trim( (string) $value );

		if ( '' === $string ) {
			return null;
		}

		if ( null !== $max_length && strlen( $string ) > $max_length ) {
			return substr( $string, 0, $max_length );
		}

		return $string;
	}

	private function nullable_country_code( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$code = strtoupper( trim( (string) $value ) );

		return '' !== $code ? substr( $code, 0, 2 ) : null;
	}

	private function nullable_int( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return max( 0, (int) $value );
	}
}

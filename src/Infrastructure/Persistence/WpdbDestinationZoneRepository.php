<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Zone\DestinationZoneRepositoryInterface;

final class WpdbDestinationZoneRepository extends AbstractWpdbRepository implements DestinationZoneRepositoryInterface {

	protected function table_suffix(): string {
		return 'destination_zones';
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
			'internal_code'    => (string) ( $data['internal_code'] ?? '' ),
			'internal_name'    => (string) ( $data['internal_name'] ?? '' ),
			'public_label'     => $this->nullable_string( $data['public_label'] ?? null, 255 ),
			'is_fallback'      => ! empty( $data['is_fallback'] ) ? 1 : 0,
			'remote_area_flag' => ! empty( $data['remote_area_flag'] ) ? 1 : 0,
			'priority'         => (int) ( $data['priority'] ?? 100 ),
			'status'           => (string) ( $data['status'] ?? 'active' ),
			'updated_at'       => $now,
		];

		$formats = [ '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ];

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
		return $this->fetch_list( $criteria, (int) ( $criteria['limit'] ?? 500 ) );
	}

	public function softDelete( int $id ): bool {
		return $this->mark_inactive( $id );
	}

	public function hardDelete( int $id ): bool {
		return $this->delete_row_by_id( $id );
	}

	public function count_all(): int {
		return parent::count_all();
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
}

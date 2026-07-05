<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\LogisticsProfile\LogisticsProfileRepositoryInterface;

final class WpdbLogisticsProfileRepository extends AbstractWpdbRepository implements LogisticsProfileRepositoryInterface {

	protected function table_suffix(): string {
		return 'logistics_profiles';
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
			'internal_code'       => (string) ( $data['internal_code'] ?? '' ),
			'internal_name'       => (string) ( $data['internal_name'] ?? '' ),
			'description'         => $this->nullable_string( $data['description'] ?? null ),
			'parcel_size_class'   => $this->nullable_string( $data['parcel_size_class'] ?? null, 64 ),
			'handling_class'      => $this->nullable_string( $data['handling_class'] ?? null, 64 ),
			'route_eligibility'   => $this->nullable_string( $data['route_eligibility'] ?? null ),
			'consolidation_rule'  => $this->nullable_string( $data['consolidation_rule'] ?? null, 64 ),
			'status'              => (string) ( $data['status'] ?? 'active' ),
			'updated_at'          => $now,
		];

		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		if ( $id > 0 ) {
			if ( ! $this->update_row( $id, $row, $formats ) ) {
				return 0;
			}

			return $id;
		}

		$row['created_at'] = $now;
		$insert_formats    = array_merge( $formats, [ '%s' ] );

		$insert_id = $this->insert_row( $row, $insert_formats );

		if ( $insert_id > 0 ) {
			return $insert_id;
		}

		return 0;
	}

	public function list( array $criteria = [] ): array {
		return $this->fetch_list( $criteria, (int) ( $criteria['limit'] ?? 500 ) );
	}

	public function softDelete( int $id ): bool {
		return $this->mark_inactive( $id );
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

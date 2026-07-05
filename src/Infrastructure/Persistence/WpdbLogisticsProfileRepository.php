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
		return (int) ( $data['id'] ?? 0 );
	}

	public function list( array $criteria = [] ): array {
		return $this->fetch_list( $criteria );
	}

	public function softDelete( int $id ): bool {
		return $this->mark_inactive( $id );
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Pickup\PickupLocationRepositoryInterface;

final class WpdbPickupLocationRepository extends AbstractWpdbRepository implements PickupLocationRepositoryInterface {

	protected function table_suffix(): string {
		return 'pickup_locations';
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
		return $this->fetch_list( $criteria );
	}

	public function softDelete( int $id ): bool {
		return $this->mark_inactive( $id );
	}
}

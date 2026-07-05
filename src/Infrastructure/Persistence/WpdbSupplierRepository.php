<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Supplier\SupplierRepositoryInterface;

final class WpdbSupplierRepository extends AbstractWpdbRepository implements SupplierRepositoryInterface {

	protected function table_suffix(): string {
		return 'suppliers';
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

	public function deactivate( int $id ): bool {
		return $this->mark_inactive( $id );
	}
}

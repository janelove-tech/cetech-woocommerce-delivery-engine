<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Zone;

interface DestinationZoneRepositoryInterface {

	/**
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByCode( string $code ): ?array;

	/**
	 * @param array<string, mixed> $data
	 */
	public function save( array $data ): int;

	/**
	 * @param array<string, mixed> $criteria
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list( array $criteria = [] ): array;

	public function softDelete( int $id ): bool;

	public function count_all(): int;
}

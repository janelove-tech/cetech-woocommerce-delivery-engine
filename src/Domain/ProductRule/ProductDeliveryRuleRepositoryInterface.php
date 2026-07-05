<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\ProductRule;

interface ProductDeliveryRuleRepositoryInterface {

	/**
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * @return list<array<string, mixed>>
	 */
	public function findByTarget( string $target_type, int $target_id ): array;

	/**
	 * @return list<array<string, mixed>>
	 */
	public function findByTargetAndAvailability( string $target_type, int $target_id, string $availability ): array;

	/**
	 * @param array<string, mixed> $filters
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list( array $filters = [] ): array;

	/**
	 * @param array<string, mixed> $data
	 */
	public function save( array $data ): int;

	public function deactivate( int $id ): bool;

	public function count_all(): int;
}

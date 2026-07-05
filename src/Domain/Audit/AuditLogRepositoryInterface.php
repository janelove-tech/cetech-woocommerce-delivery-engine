<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Audit;

interface AuditLogRepositoryInterface {

	/**
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * @param array<string, mixed> $data
	 */
	public function append( array $data ): int;

	/**
	 * @param array<string, mixed> $criteria
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list( array $criteria = [] ): array;
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Zone;

interface DestinationRuleRepositoryInterface {

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listByZoneId( int $zone_id ): array;

	public function deleteByZoneId( int $zone_id ): bool;

	/**
	 * @param list<array<string, mixed>> $rules
	 */
	public function replaceForZone( int $zone_id, array $rules ): bool;

	public function count_all(): int;
}

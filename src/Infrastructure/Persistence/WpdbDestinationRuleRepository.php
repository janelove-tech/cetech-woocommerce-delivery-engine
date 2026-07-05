<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\Zone\DestinationRuleRepositoryInterface;

final class WpdbDestinationRuleRepository extends AbstractWpdbRepository implements DestinationRuleRepositoryInterface {

	protected function table_suffix(): string {
		return 'destination_rules';
	}

	public function listByZoneId( int $zone_id ): array {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "SELECT * FROM `{$table}` WHERE zone_id = %d ORDER BY priority ASC, id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $zone_id ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function deleteByZoneId( int $zone_id ): bool {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "DELETE FROM `{$table}` WHERE zone_id = %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare( $sql, $zone_id ) );

		return false !== $result;
	}

	public function replaceForZone( int $zone_id, array $rules ): void {
		throw new \BadMethodCallException(
			'DestinationRuleRepository::replaceForZone() is not implemented until Phase 2B CRUD.'
		);
	}
}

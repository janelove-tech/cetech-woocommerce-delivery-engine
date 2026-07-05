<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

/**
 * Phase 2A configuration-domain tables.
 */
final class ConfigurationTables {

	/** @var list<string> Table suffixes without WordPress prefix. */
	public const SUFFIXES = [
		'delivery_offers',
		'destination_zones',
		'destination_rules',
		'logistics_profiles',
		'suppliers',
		'origins',
		'pickup_locations',
		'rate_cards',
		'rate_card_rules',
		'audit_log',
	];

	/**
	 * @return list<string> Fully qualified table names for the current site.
	 */
	public static function all(): array {
		$tables = [];

		foreach ( self::SUFFIXES as $suffix ) {
			$tables[] = TableNames::for( $suffix );
		}

		return $tables;
	}

	public static function exists( string $suffix ): bool {
		global $wpdb;

		$table = TableNames::for( $suffix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return $result === $table;
	}

	public static function all_exist(): bool {
		foreach ( self::SUFFIXES as $suffix ) {
			if ( ! self::exists( $suffix ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return list<string> Missing table suffixes.
	 */
	public static function missing(): array {
		$missing = [];

		foreach ( self::SUFFIXES as $suffix ) {
			if ( ! self::exists( $suffix ) ) {
				$missing[] = $suffix;
			}
		}

		return $missing;
	}
}

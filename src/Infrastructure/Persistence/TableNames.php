<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

/**
 * Resolves delivery-engine table names using the site-specific WordPress prefix.
 */
final class TableNames {

	public const PREFIX = 'delivery_engine_';

	public static function for( string $suffix ): string {
		global $wpdb;

		return $wpdb->prefix . self::PREFIX . $suffix;
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Core\Versioning;

/**
 * Persists the plugin schema version in wp_options.
 */
final class SchemaVersion {

	public const OPTION_NAME = 'cetech_de_db_version';

	/**
	 * Foundation schema version. No delivery-domain tables in Phase 1B.
	 */
	public const CURRENT = '0';

	public static function get(): string {
		$stored = get_option( self::OPTION_NAME, null );

		if ( null === $stored ) {
			return self::CURRENT;
		}

		return (string) $stored;
	}

	public static function set( string $version ): void {
		update_option( self::OPTION_NAME, $version, false );
	}

	public static function ensure_initialized(): void {
		if ( null === get_option( self::OPTION_NAME, null ) ) {
			add_option( self::OPTION_NAME, self::CURRENT, '', false );
		}
	}
}

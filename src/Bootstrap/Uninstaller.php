<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Bootstrap;

use CetechDeliveryEngine\Core\Capabilities\Capabilities;
use CetechDeliveryEngine\Core\Versioning\SchemaVersion;

/**
 * Removes plugin options and capabilities when delete-data uninstall is enabled.
 */
final class Uninstaller {

	public const DELETE_DATA_OPTION = 'cetech_de_delete_data_on_uninstall';

	public static function uninstall(): void {
		$delete_data = (bool) (int) get_option( self::DELETE_DATA_OPTION, 0 );

		if ( ! $delete_data ) {
			return;
		}

		self::remove_capabilities();
		self::remove_options();
	}

	private static function remove_capabilities(): void {
		$capabilities = new Capabilities();
		$capabilities->unregister();
	}

	private static function remove_options(): void {
		$feature_flags = new FeatureFlags();

		foreach ( array_keys( $feature_flags->defaults() ) as $flag ) {
			delete_option( $feature_flags->option_name( $flag ) );
		}

		delete_option( SchemaVersion::OPTION_NAME );
		delete_option( self::DELETE_DATA_OPTION );
	}
}

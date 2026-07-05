<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Bootstrap;

use CetechDeliveryEngine\Core\Capabilities\Capabilities;

/**
 * Plugin activation handler.
 */
final class Activator {

	public const DB_VERSION_OPTION = 'cetech_de_db_version';

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( CETECH_DE_FILE ) );

			wp_die(
				esc_html__(
					'CETECH WooCommerce Delivery Engine requires PHP 8.1 or higher.',
					'cetech-woocommerce-delivery-engine'
				),
				esc_html__( 'Plugin Activation Error', 'cetech-woocommerce-delivery-engine' ),
				[ 'back_link' => true ]
			);
		}

		$capabilities = new Capabilities();
		$capabilities->register();

		$feature_flags = new FeatureFlags();
		$feature_flags->ensure_defaults();

		if ( null === get_option( self::DB_VERSION_OPTION, null ) ) {
			add_option( self::DB_VERSION_OPTION, '0', '', false );
		}

		set_transient( 'cetech_de_activation_notice', 1, MINUTE_IN_SECONDS );

		flush_rewrite_rules();
	}
}

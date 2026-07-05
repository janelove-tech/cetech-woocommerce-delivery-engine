<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Core\Capabilities;

/**
 * Registers granular delivery-engine capabilities on WordPress roles.
 */
final class Capabilities {

	/** @var list<string> */
	public const ALL = [
		'manage_delivery_settings',
		'manage_delivery_offers',
		'manage_delivery_rate_cards',
		'manage_delivery_zones',
		'manage_logistics_profiles',
		'manage_private_sources',
		'manage_product_delivery_rules',
		'manage_shipments',
		'update_shipment_status',
		'view_private_delivery_costs',
		'view_private_origins',
		'manage_delivery_integrations',
		'view_delivery_logs',
		'import_delivery_data',
	];

	/** @var list<string> */
	private const ROLES = [
		'administrator',
		'shop_manager',
	];

	public function register(): void {
		foreach ( self::ROLES as $role_slug ) {
			$role = get_role( $role_slug );

			if ( null === $role ) {
				continue;
			}

			foreach ( self::ALL as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}
}

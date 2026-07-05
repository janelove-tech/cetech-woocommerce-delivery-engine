<?php

declare(strict_types=1);

use CetechDeliveryEngine\Core\Versioning\MigrationInterface;
use CetechDeliveryEngine\Core\Versioning\VerifiableMigrationInterface;
use CetechDeliveryEngine\Infrastructure\Persistence\ConfigurationTables;
use CetechDeliveryEngine\Infrastructure\Persistence\TableNames;

return new class implements VerifiableMigrationInterface {

	public function get_id(): string {
		return '20260705170000_create_product_delivery_rules_table';
	}

	public function get_version(): string {
		return '2';
	}

	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = TableNames::for( 'product_delivery_rules' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			target_type varchar(32) NOT NULL,
			target_id bigint(20) unsigned NOT NULL,
			target_label_snapshot varchar(255) DEFAULT NULL,
			fulfilment_availability varchar(64) NOT NULL,
			fulfilment_choice varchar(64) NOT NULL,
			delivery_offer_ids longtext DEFAULT NULL,
			logistics_profile_id bigint(20) unsigned DEFAULT NULL,
			supplier_id bigint(20) unsigned DEFAULT NULL,
			origin_id bigint(20) unsigned DEFAULT NULL,
			priority int(11) NOT NULL DEFAULT 100,
			status varchar(32) NOT NULL DEFAULT 'active',
			internal_notes longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY target_lookup (target_type, target_id),
			KEY fulfilment_availability (fulfilment_availability),
			KEY logistics_profile_id (logistics_profile_id),
			KEY supplier_id (supplier_id),
			KEY origin_id (origin_id),
			KEY status (status),
			KEY priority (priority)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public function verify(): void {
		if ( ! ConfigurationTables::exists( 'product_delivery_rules' ) ) {
			throw new \RuntimeException(
				'Product delivery rules table missing after migration.'
			);
		}
	}
};

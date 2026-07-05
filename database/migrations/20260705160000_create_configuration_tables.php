<?php

declare(strict_types=1);

use CetechDeliveryEngine\Core\Versioning\MigrationInterface;
use CetechDeliveryEngine\Core\Versioning\VerifiableMigrationInterface;
use CetechDeliveryEngine\Infrastructure\Persistence\ConfigurationTables;
use CetechDeliveryEngine\Infrastructure\Persistence\TableNames;

return new class implements VerifiableMigrationInterface {

	public function get_id(): string {
		return '20260705160000_create_configuration_tables';
	}

	public function get_version(): string {
		return '1';
	}

	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$this->create_delivery_offers_table( $charset_collate );
		$this->create_destination_zones_table( $charset_collate );
		$this->create_destination_rules_table( $charset_collate );
		$this->create_logistics_profiles_table( $charset_collate );
		$this->create_suppliers_table( $charset_collate );
		$this->create_origins_table( $charset_collate );
		$this->create_pickup_locations_table( $charset_collate );
		$this->create_rate_cards_table( $charset_collate );
		$this->create_rate_card_rules_table( $charset_collate );
		$this->create_audit_log_table( $charset_collate );
	}

	public function verify(): void {
		$missing = ConfigurationTables::missing_configuration_domain();

		if ( [] === $missing ) {
			return;
		}

		throw new \RuntimeException(
			sprintf(
				'Configuration tables missing after migration: %s',
				implode( ', ', $missing )
			)
		);
	}

	private function create_delivery_offers_table( string $charset_collate ): void {
		$table = TableNames::for( 'delivery_offers' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			internal_code varchar(64) NOT NULL,
			internal_name varchar(255) NOT NULL,
			public_label varchar(255) NOT NULL DEFAULT '',
			route varchar(32) NOT NULL,
			service_level varchar(64) NOT NULL DEFAULT '',
			carrier_visibility varchar(32) NOT NULL DEFAULT 'assigned_by_store',
			carrier_name varchar(255) DEFAULT NULL,
			public_description longtext DEFAULT NULL,
			tax_class varchar(64) NOT NULL DEFAULT '',
			price_basis varchar(32) NOT NULL DEFAULT 'manual',
			default_processing_min int(10) unsigned DEFAULT NULL,
			default_processing_max int(10) unsigned DEFAULT NULL,
			default_transit_min int(10) unsigned DEFAULT NULL,
			default_transit_max int(10) unsigned DEFAULT NULL,
			default_final_mile_min int(10) unsigned DEFAULT NULL,
			default_final_mile_max int(10) unsigned DEFAULT NULL,
			duration_unit varchar(16) NOT NULL DEFAULT 'business_days',
			display_priority int(11) NOT NULL DEFAULT 100,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY status (status),
			KEY route (route)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_destination_zones_table( string $charset_collate ): void {
		$table = TableNames::for( 'destination_zones' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			internal_code varchar(64) NOT NULL,
			internal_name varchar(255) NOT NULL,
			public_label varchar(255) DEFAULT NULL,
			is_fallback tinyint(1) NOT NULL DEFAULT 0,
			remote_area_flag tinyint(1) NOT NULL DEFAULT 0,
			priority int(11) NOT NULL DEFAULT 100,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY status (status),
			KEY priority (priority)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_destination_rules_table( string $charset_collate ): void {
		$table = TableNames::for( 'destination_rules' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			zone_id bigint(20) unsigned NOT NULL,
			rule_type varchar(32) NOT NULL,
			rule_value varchar(255) NOT NULL,
			match_mode varchar(16) NOT NULL DEFAULT 'exact',
			priority int(11) NOT NULL DEFAULT 100,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY zone_id (zone_id),
			KEY rule_type (rule_type),
			KEY priority (priority)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_logistics_profiles_table( string $charset_collate ): void {
		$table = TableNames::for( 'logistics_profiles' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			internal_code varchar(64) NOT NULL,
			internal_name varchar(255) NOT NULL,
			description longtext DEFAULT NULL,
			parcel_size_class varchar(64) DEFAULT NULL,
			handling_class varchar(64) DEFAULT NULL,
			route_eligibility longtext DEFAULT NULL,
			consolidation_rule varchar(64) DEFAULT NULL,
			dispatch_type varchar(64) DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_suppliers_table( string $charset_collate ): void {
		$table = TableNames::for( 'suppliers' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			internal_code varchar(64) NOT NULL,
			internal_name varchar(255) NOT NULL,
			contact_email varchar(255) DEFAULT NULL,
			contact_phone varchar(64) DEFAULT NULL,
			internal_notes longtext DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_origins_table( string $charset_collate ): void {
		$table = TableNames::for( 'origins' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) unsigned NOT NULL,
			internal_code varchar(64) NOT NULL,
			internal_name varchar(255) NOT NULL,
			internal_address longtext DEFAULT NULL,
			country_code char(2) DEFAULT NULL,
			dispatch_lead_days_min int(10) unsigned DEFAULT NULL,
			dispatch_lead_days_max int(10) unsigned DEFAULT NULL,
			internal_notes longtext DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_pickup_locations_table( string $charset_collate ): void {
		$table = TableNames::for( 'pickup_locations' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			internal_code varchar(64) NOT NULL,
			location_name varchar(255) NOT NULL,
			public_address longtext DEFAULT NULL,
			public_opening_hours longtext DEFAULT NULL,
			public_pickup_instructions longtext DEFAULT NULL,
			contact_phone varchar(64) DEFAULT NULL,
			contact_email varchar(255) DEFAULT NULL,
			readiness_estimate varchar(255) DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_rate_cards_table( string $charset_collate ): void {
		$table = TableNames::for( 'rate_cards' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			internal_code varchar(64) NOT NULL,
			delivery_offer_id bigint(20) unsigned NOT NULL,
			destination_zone_id bigint(20) unsigned NOT NULL,
			logistics_profile_id bigint(20) unsigned DEFAULT NULL,
			supplier_id bigint(20) unsigned DEFAULT NULL,
			origin_id bigint(20) unsigned DEFAULT NULL,
			charge_type varchar(32) NOT NULL,
			base_amount decimal(19,4) NOT NULL DEFAULT 0.0000,
			base_currency char(3) NOT NULL DEFAULT '',
			included_weight decimal(10,4) DEFAULT NULL,
			increment_weight decimal(10,4) DEFAULT NULL,
			increment_amount decimal(19,4) DEFAULT NULL,
			per_item_amount decimal(19,4) DEFAULT NULL,
			per_line_amount decimal(19,4) DEFAULT NULL,
			highest_fee_mode varchar(32) DEFAULT NULL,
			remote_surcharge decimal(19,4) DEFAULT NULL,
			free_shipping_threshold decimal(19,4) DEFAULT NULL,
			manual_currency_override_data longtext DEFAULT NULL,
			priority int(11) NOT NULL DEFAULT 100,
			effective_from datetime DEFAULT NULL,
			effective_to datetime DEFAULT NULL,
			status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY internal_code (internal_code),
			KEY delivery_offer_id (delivery_offer_id),
			KEY destination_zone_id (destination_zone_id),
			KEY logistics_profile_id (logistics_profile_id),
			KEY status (status),
			KEY priority (priority)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_rate_card_rules_table( string $charset_collate ): void {
		$table = TableNames::for( 'rate_card_rules' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rate_card_id bigint(20) unsigned NOT NULL,
			rule_key varchar(64) NOT NULL,
			rule_value longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY rate_card_id (rate_card_id),
			KEY rule_key (rule_key)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private function create_audit_log_table( string $charset_collate ): void {
		$table = TableNames::for( 'audit_log' );

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			action varchar(64) NOT NULL,
			entity_type varchar(64) NOT NULL,
			entity_id bigint(20) unsigned DEFAULT NULL,
			previous_value longtext DEFAULT NULL,
			new_value longtext DEFAULT NULL,
			site_context varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
};

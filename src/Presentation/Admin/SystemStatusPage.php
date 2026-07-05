<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Bootstrap\FeatureFlags;
use CetechDeliveryEngine\Core\Capabilities\Capabilities;
use CetechDeliveryEngine\Core\FeaturesCompatibility;
use CetechDeliveryEngine\Core\Requirements;
use CetechDeliveryEngine\Core\Versioning\MigrationStatus;
use CetechDeliveryEngine\Core\Versioning\SchemaVersion;
use CetechDeliveryEngine\Infrastructure\Persistence\ConfigurationTables;
use CetechDeliveryEngine\Domain\DeliveryOffer\DeliveryOfferRepositoryInterface;
use CetechDeliveryEngine\Domain\LogisticsProfile\LogisticsProfileRepositoryInterface;
use CetechDeliveryEngine\Domain\Pickup\PickupLocationRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\SupplierRepositoryInterface;
use CetechDeliveryEngine\Domain\Zone\DestinationRuleRepositoryInterface;
use CetechDeliveryEngine\Domain\Zone\DestinationZoneRepositoryInterface;
use CetechDeliveryEngine\Integrations\Registry\IntegrationRegistry;

/**
 * Read-only system status page with capability re-sync action.
 */
final class SystemStatusPage {

	private const RESYNC_ACTION = 'cetech_de_resync_capabilities';

	private const RESYNC_NOTICE_KEY = 'cetech_de_caps_synced';

	public function __construct(
		private Requirements $requirements,
		private FeatureFlags $feature_flags,
		private IntegrationRegistry $integration_registry,
		private Capabilities $capabilities,
		private LogisticsProfileRepositoryInterface $logistics_profile_repository,
		private DeliveryOfferRepositoryInterface $delivery_offer_repository,
		private DestinationZoneRepositoryInterface $destination_zone_repository,
		private DestinationRuleRepositoryInterface $destination_rule_repository,
		private PickupLocationRepositoryInterface $pickup_location_repository,
		private SupplierRepositoryInterface $supplier_repository,
		private OriginRepositoryInterface $origin_repository
	) {
	}

	public function handle_actions(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_delivery_settings' ) ) {
			return;
		}

		if ( ! isset( $_POST['cetech_de_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( (string) $_POST['cetech_de_action'] ) );

		if ( self::RESYNC_ACTION !== $action ) {
			return;
		}

		check_admin_referer( self::RESYNC_ACTION, 'cetech_de_nonce' );

		$this->capabilities->sync();

		$redirect = add_query_arg(
			[
				'page'                    => AdminMenu::SYSTEM_STATUS_SLUG,
				self::RESYNC_NOTICE_KEY   => '1',
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_delivery_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->render_admin_notices();

		$integrations = $this->integration_registry->get_detection_statuses();
		$display_keys = [ 'woodmart', 'wpml', 'wcml', 'wcfm', 'vitepos' ];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Delivery Engine — System Status', 'cetech-woocommerce-delivery-engine' ) . '</h1>';
		echo '<p>' . esc_html__( 'Read-only system status and configuration health summary.', 'cetech-woocommerce-delivery-engine' ) . '</p>';

		$this->render_configuration_warnings();

		$this->render_table(
			__( 'Environment', 'cetech-woocommerce-delivery-engine' ),
			[
				__( 'Plugin version', 'cetech-woocommerce-delivery-engine' ) => CETECH_DE_VERSION,
				__( 'PHP version', 'cetech-woocommerce-delivery-engine' )    => PHP_VERSION,
				__( 'WordPress version', 'cetech-woocommerce-delivery-engine' ) => get_bloginfo( 'version' ),
				__( 'WooCommerce active', 'cetech-woocommerce-delivery-engine' ) => $this->yes_no( $this->requirements->is_woocommerce_active() ),
				__( 'WooCommerce version', 'cetech-woocommerce-delivery-engine' ) => $this->woocommerce_version(),
				__( 'HPOS compatibility declaration attempted', 'cetech-woocommerce-delivery-engine' ) => $this->yes_no( FeaturesCompatibility::hpos_declaration_attempted() ),
				__( 'Schema up to date', 'cetech-woocommerce-delivery-engine' ) => $this->yes_no( SchemaVersion::is_up_to_date() ),
				__( 'Target schema version', 'cetech-woocommerce-delivery-engine' ) => SchemaVersion::target(),
				__( 'Installed schema version', 'cetech-woocommerce-delivery-engine' ) => SchemaVersion::get(),
				__( 'Configuration tables present', 'cetech-woocommerce-delivery-engine' ) => $this->yes_no( ConfigurationTables::all_exist() ),
				__( 'Missing configuration tables', 'cetech-woocommerce-delivery-engine' ) => $this->format_missing_tables(),
				__( 'Last migration status', 'cetech-woocommerce-delivery-engine' ) => $this->format_migration_status(),
				__( 'Composer autoload present', 'cetech-woocommerce-delivery-engine' ) => $this->yes_no( is_readable( CETECH_DE_PATH . 'vendor/autoload.php' ) ),
			]
		);

		$this->render_table(
			__( 'Configuration records', 'cetech-woocommerce-delivery-engine' ),
			[
				__( 'Logistics profiles', 'cetech-woocommerce-delivery-engine' ) => (string) $this->logistics_profile_repository->count_all(),
				__( 'Delivery offers', 'cetech-woocommerce-delivery-engine' ) => (string) $this->delivery_offer_repository->count_all(),
				__( 'Destination zones', 'cetech-woocommerce-delivery-engine' ) => (string) $this->destination_zone_repository->count_all(),
				__( 'Destination rules', 'cetech-woocommerce-delivery-engine' ) => (string) $this->destination_rule_repository->count_all(),
				__( 'Pickup locations', 'cetech-woocommerce-delivery-engine' ) => (string) $this->pickup_location_repository->count_all(),
				__( 'Suppliers', 'cetech-woocommerce-delivery-engine' ) => (string) $this->supplier_repository->count_all(),
				__( 'Origins', 'cetech-woocommerce-delivery-engine' ) => (string) $this->origin_repository->count_all(),
			]
		);

		$flag_rows = [];

		foreach ( $this->feature_flags->all() as $flag => $enabled ) {
			$flag_rows[ $flag ] = $this->yes_no( $enabled );
		}

		$this->render_table( __( 'Feature flags (read-only)', 'cetech-woocommerce-delivery-engine' ), $flag_rows );

		$integration_rows = [];

		foreach ( $display_keys as $key ) {
			$integration_rows[ $key ] = $this->yes_no( ! empty( $integrations[ $key ] ) );
		}

		$this->render_table( __( 'Optional integrations (detected)', 'cetech-woocommerce-delivery-engine' ), $integration_rows );

		$capability_rows = [];

		foreach ( Capabilities::ALL as $capability ) {
			$capability_rows[ $capability ] = $this->yes_no( current_user_can( $capability ) );
		}

		$this->render_table( __( 'Current user delivery capabilities', 'cetech-woocommerce-delivery-engine' ), $capability_rows );

		$this->render_resync_form();

		echo '</div>';
	}

	/**
	 * @param array<string, string> $rows
	 */
	private function render_table( string $title, array $rows ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:960px;">';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Item', 'cetech-woocommerce-delivery-engine' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Value', 'cetech-woocommerce-delivery-engine' ) . '</th></tr></thead><tbody>';

		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( (string) $label ) . '</th>';
			echo '<td>' . esc_html( $value ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}

	private function render_resync_form(): void {
		echo '<h2>' . esc_html__( 'Maintenance', 'cetech-woocommerce-delivery-engine' ) . '</h2>';
		echo '<form method="post" action="">';
		wp_nonce_field( self::RESYNC_ACTION, 'cetech_de_nonce' );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::RESYNC_ACTION ) . '" />';
		submit_button(
			__( 'Re-sync capabilities', 'cetech-woocommerce-delivery-engine' ),
			'secondary',
			'submit',
			false
		);
		echo '<p class="description">' . esc_html__(
			'Re-applies delivery capabilities to the administrator and shop_manager roles.',
			'cetech-woocommerce-delivery-engine'
		) . '</p>';
		echo '</form>';
	}

	private function render_admin_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::RESYNC_NOTICE_KEY ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = sanitize_key( wp_unslash( (string) $_GET[ self::RESYNC_NOTICE_KEY ] ) );

		if ( '1' !== $status ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'Delivery capabilities were re-synced successfully.', 'cetech-woocommerce-delivery-engine' );
		echo '</p></div>';
	}

	private function yes_no( bool $value ): string {
		return $value
			? __( 'Yes', 'cetech-woocommerce-delivery-engine' )
			: __( 'No', 'cetech-woocommerce-delivery-engine' );
	}

	private function render_configuration_warnings(): void {
		$profile_count = $this->logistics_profile_repository->count_all();
		$offer_count   = $this->delivery_offer_repository->count_all();
		$zone_count    = $this->destination_zone_repository->count_all();
		$pickup_count  = $this->pickup_location_repository->count_all();
		$supplier_count = $this->supplier_repository->count_all();
		$origin_count   = $this->origin_repository->count_all();

		if ( $profile_count > 0 && $offer_count > 0 && $zone_count > 0 && $pickup_count > 0 && $supplier_count > 0 && $origin_count > 0 ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';

		if ( 0 === $profile_count ) {
			echo esc_html__( 'Warning: no logistics profiles are configured yet.', 'cetech-woocommerce-delivery-engine' ) . ' ';
		}

		if ( 0 === $offer_count ) {
			echo esc_html__( 'Warning: no delivery offers are configured yet.', 'cetech-woocommerce-delivery-engine' ) . ' ';
		}

		if ( 0 === $zone_count ) {
			echo esc_html__( 'Warning: no destination zones are configured yet.', 'cetech-woocommerce-delivery-engine' ) . ' ';
		}

		if ( 0 === $pickup_count ) {
			echo esc_html__( 'Warning: no pickup locations are configured yet.', 'cetech-woocommerce-delivery-engine' ) . ' ';
		}

		if ( 0 === $supplier_count ) {
			echo esc_html__( 'Warning: no suppliers are configured yet.', 'cetech-woocommerce-delivery-engine' ) . ' ';
		}

		if ( 0 === $origin_count ) {
			echo esc_html__( 'Warning: no origins are configured yet.', 'cetech-woocommerce-delivery-engine' );
		}

		echo '</p></div>';
	}

	private function format_missing_tables(): string {
		$missing = ConfigurationTables::missing();

		if ( [] === $missing ) {
			return __( 'None', 'cetech-woocommerce-delivery-engine' );
		}

		return implode( ', ', $missing );
	}

	private function format_migration_status(): string {
		$status = MigrationStatus::get();

		if ( null === $status ) {
			return __( 'N/A', 'cetech-woocommerce-delivery-engine' );
		}

		$parts = [];

		if ( isset( $status['status'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: migration outcome (success or failed) */
				__( 'Status: %s', 'cetech-woocommerce-delivery-engine' ),
				(string) $status['status']
			);
		}

		if ( isset( $status['migration_id'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: migration identifier */
				__( 'Migration: %s', 'cetech-woocommerce-delivery-engine' ),
				(string) $status['migration_id']
			);
		}

		if ( isset( $status['to_version'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: schema version number */
				__( 'Version: %s', 'cetech-woocommerce-delivery-engine' ),
				(string) $status['to_version']
			);
		}

		if ( isset( $status['recorded_at'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: UTC timestamp */
				__( 'Recorded: %s UTC', 'cetech-woocommerce-delivery-engine' ),
				(string) $status['recorded_at']
			);
		}

		if ( isset( $status['error'] ) && '' !== (string) $status['error'] ) {
			$parts[] = sprintf(
				/* translators: %s: error message */
				__( 'Error: %s', 'cetech-woocommerce-delivery-engine' ),
				(string) $status['error']
			);
		}

		return implode( ' | ', $parts );
	}

	private function woocommerce_version(): string {
		if ( ! $this->requirements->is_woocommerce_active() || ! defined( 'WC_VERSION' ) ) {
			return __( 'N/A', 'cetech-woocommerce-delivery-engine' );
		}

		return WC_VERSION;
	}
}

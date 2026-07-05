<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Registers Delivery Engine admin menus for Phase 2B1.
 */
final class AdminMenu {

	public const PARENT_SLUG = 'cetech-delivery-engine';

	public const SYSTEM_STATUS_SLUG = 'cetech-delivery-engine-system-status';

	public function __construct(
		private SystemStatusPage $system_status_page,
		private LogisticsProfilesPage $logistics_profiles_page,
		private DeliveryOffersPage $delivery_offers_page
	) {
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menus' ] );
		add_action( 'admin_init', [ $this->system_status_page, 'handle_actions' ] );
		add_action( 'admin_init', [ $this->logistics_profiles_page, 'handle_actions' ] );
		add_action( 'admin_init', [ $this->delivery_offers_page, 'handle_actions' ] );
	}

	public function add_menus(): void {
		if ( ! $this->current_user_has_any_menu_cap() ) {
			return;
		}

		add_menu_page(
			__( 'Delivery Engine', 'cetech-woocommerce-delivery-engine' ),
			__( 'Delivery Engine', 'cetech-woocommerce-delivery-engine' ),
			'manage_delivery_settings',
			self::PARENT_SLUG,
			[ $this->system_status_page, 'render' ],
			'dashicons-store',
			56
		);

		if ( current_user_can( 'manage_delivery_settings' ) ) {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'System Status', 'cetech-woocommerce-delivery-engine' ),
				__( 'System Status', 'cetech-woocommerce-delivery-engine' ),
				'manage_delivery_settings',
				self::SYSTEM_STATUS_SLUG,
				[ $this->system_status_page, 'render' ]
			);
		}

		if ( current_user_can( 'manage_logistics_profiles' ) ) {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Logistics Profiles', 'cetech-woocommerce-delivery-engine' ),
				__( 'Logistics Profiles', 'cetech-woocommerce-delivery-engine' ),
				'manage_logistics_profiles',
				LogisticsProfilesPage::SLUG,
				[ $this->logistics_profiles_page, 'render' ]
			);
		}

		if ( current_user_can( 'manage_delivery_offers' ) ) {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Delivery Offers', 'cetech-woocommerce-delivery-engine' ),
				__( 'Delivery Offers', 'cetech-woocommerce-delivery-engine' ),
				'manage_delivery_offers',
				DeliveryOffersPage::SLUG,
				[ $this->delivery_offers_page, 'render' ]
			);
		}

		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}

	private function current_user_has_any_menu_cap(): bool {
		return current_user_can( 'manage_delivery_settings' )
			|| current_user_can( 'manage_logistics_profiles' )
			|| current_user_can( 'manage_delivery_offers' );
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Registers the minimal Delivery Engine admin menu for Phase 1B.
 */
final class AdminMenu {

	public const PARENT_SLUG = 'cetech-delivery-engine';

	public const SYSTEM_STATUS_SLUG = 'cetech-delivery-engine-system-status';

	public function __construct(
		private SystemStatusPage $system_status_page
	) {
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menus' ] );
		add_action( 'admin_init', [ $this->system_status_page, 'handle_actions' ] );
	}

	public function add_menus(): void {
		if ( ! current_user_can( 'manage_delivery_settings' ) ) {
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

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'System Status', 'cetech-woocommerce-delivery-engine' ),
			__( 'System Status', 'cetech-woocommerce-delivery-engine' ),
			'manage_delivery_settings',
			self::SYSTEM_STATUS_SLUG,
			[ $this->system_status_page, 'render' ]
		);

		// Hide duplicate parent submenu entry WordPress auto-creates for the first page.
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
	}
}

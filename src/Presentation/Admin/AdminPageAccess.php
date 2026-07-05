<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Consistent wp-admin access checks for Delivery Engine pages.
 */
final class AdminPageAccess {

	public static function require_capability( string $capability ): void {
		if ( current_user_can( $capability ) ) {
			return;
		}

		wp_die(
			esc_html__(
				'You do not have permission to access this page.',
				'cetech-woocommerce-delivery-engine'
			)
		);
	}
}

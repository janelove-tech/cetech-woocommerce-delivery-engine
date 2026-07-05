<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Bootstrap;

/**
 * Plugin deactivation handler.
 */
final class Deactivator {

	public static function deactivate(): void {
		delete_transient( 'cetech_de_activation_notice' );
		flush_rewrite_rules();
	}
}

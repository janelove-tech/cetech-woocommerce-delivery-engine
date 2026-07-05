<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Integrations\Registry;

/**
 * Safe no-op integration used when an adapter is unavailable.
 */
final class NullIntegration implements IntegrationInterface {

	public function __construct(
		private string $key = 'null'
	) {
	}

	public function isAvailable(): bool {
		return false;
	}

	public function register(): void {
		// Intentionally empty.
	}

	public function getKey(): string {
		return $this->key;
	}
}

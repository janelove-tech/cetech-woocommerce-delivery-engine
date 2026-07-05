<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Integrations\Registry;

/**
 * Contract for optional third-party integrations.
 */
interface IntegrationInterface {

	public function isAvailable(): bool;

	public function register(): void;

	public function getKey(): string;
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Core\Versioning;

/**
 * Optional contract for migrations that must pass post-up verification before schema version bumps.
 */
interface VerifiableMigrationInterface extends MigrationInterface {

	/**
	 * Assert migration side effects are present (e.g. required tables exist).
	 *
	 * @throws \RuntimeException When verification fails.
	 */
	public function verify(): void;
}

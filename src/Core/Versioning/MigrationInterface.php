<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Core\Versioning;

/**
 * Contract for database schema migrations.
 */
interface MigrationInterface {

	/**
	 * Stable migration identifier (e.g. 20260705120000_foundation).
	 */
	public function get_id(): string;

	/**
	 * Schema version applied when this migration succeeds.
	 */
	public function get_version(): string;

	/**
	 * Apply the migration. Must be safe to run only once per version bump.
	 */
	public function up(): void;
}

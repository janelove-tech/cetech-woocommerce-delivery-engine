<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Core\Versioning;

use CetechDeliveryEngine\Support\Logger;

/**
 * Runs pending schema migrations idempotently.
 */
final class MigrationRunner {

	/** @var list<MigrationInterface> */
	private array $migrations = [];

	public function __construct(
		private Logger $logger
	) {
	}

	/**
	 * @param list<MigrationInterface> $migrations
	 */
	public function set_migrations( array $migrations ): void {
		$this->migrations = $migrations;
	}

	public function run(): void {
		SchemaVersion::ensure_initialized();

		$current = SchemaVersion::get();

		if ( [] === $this->migrations ) {
			$this->logger->info(
				'Migration runner completed: no migrations registered.',
				[ 'schema_version' => $current ]
			);

			return;
		}

		usort(
			$this->migrations,
			static function ( MigrationInterface $a, MigrationInterface $b ): int {
				return version_compare( $a->get_version(), $b->get_version() );
			}
		);

		foreach ( $this->migrations as $migration ) {
			if ( version_compare( $migration->get_version(), $current, '<=' ) ) {
				continue;
			}

			$this->apply_migration( $migration, $current );
			$current = SchemaVersion::get();
		}
	}

	private function apply_migration( MigrationInterface $migration, string $from_version ): void {
		$migration_id = $migration->get_id();
		$to_version   = $migration->get_version();

		$this->logger->info(
			'Applying migration.',
			[
				'migration_id' => $migration_id,
				'from_version' => $from_version,
				'to_version'   => $to_version,
			]
		);

		try {
			$migration->up();

			if ( $migration instanceof VerifiableMigrationInterface ) {
				$migration->verify();
			}

			SchemaVersion::set( $to_version );

			MigrationStatus::record(
				[
					'status'       => 'success',
					'migration_id' => $migration_id,
					'from_version' => $from_version,
					'to_version'   => $to_version,
				]
			);

			$this->logger->info(
				'Migration applied successfully.',
				[
					'migration_id' => $migration_id,
					'schema_version' => $to_version,
				]
			);
		} catch ( \Throwable $exception ) {
			MigrationStatus::record(
				[
					'status'       => 'failed',
					'migration_id' => $migration_id,
					'from_version' => $from_version,
					'to_version'   => $to_version,
					'error'        => $exception->getMessage(),
				]
			);

			$this->logger->error(
				'Migration failed.',
				[
					'migration_id' => $migration_id,
					'from_version' => $from_version,
					'to_version'   => $to_version,
					'error'        => $exception->getMessage(),
				]
			);
		}
	}
}

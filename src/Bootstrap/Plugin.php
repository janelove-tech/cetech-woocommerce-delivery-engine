<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Bootstrap;

use CetechDeliveryEngine\Core\AdminNoticeManager;
use CetechDeliveryEngine\Core\Capabilities\Capabilities;
use CetechDeliveryEngine\Core\FeaturesCompatibility;
use CetechDeliveryEngine\Core\Health\HealthCheckRegistry;
use CetechDeliveryEngine\Core\Requirements;
use CetechDeliveryEngine\Core\Versioning\MigrationDiscovery;
use CetechDeliveryEngine\Core\Versioning\MigrationRunner;
use CetechDeliveryEngine\Domain\Audit\AuditLogRepositoryInterface;
use CetechDeliveryEngine\Domain\DeliveryOffer\DeliveryOfferRepositoryInterface;
use CetechDeliveryEngine\Domain\LogisticsProfile\LogisticsProfileRepositoryInterface;
use CetechDeliveryEngine\Domain\Pickup\PickupLocationRepositoryInterface;
use CetechDeliveryEngine\Domain\RateCard\RateCardRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\SupplierRepositoryInterface;
use CetechDeliveryEngine\Domain\Zone\DestinationZoneRepositoryInterface;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbAuditLogRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbDeliveryOfferRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbDestinationZoneRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbLogisticsProfileRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbOriginRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbPickupLocationRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbRateCardRepository;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbSupplierRepository;
use CetechDeliveryEngine\Integrations\Registry\IntegrationRegistry;
use CetechDeliveryEngine\Presentation\Admin\AdminMenu;
use CetechDeliveryEngine\Presentation\Admin\SystemStatusPage;
use CetechDeliveryEngine\Support\AdminNotice;
use CetechDeliveryEngine\Support\Logger;

/**
 * Main plugin bootstrap.
 */
final class Plugin {

	private static ?self $instance = null;

	private bool $booted = false;

	private ServiceContainer $container;

	private function __construct() {
		$this->container = new ServiceContainer();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		FeaturesCompatibility::register_hpos_declaration( CETECH_DE_FILE );

		$requirements = new Requirements();
		$notices      = new AdminNoticeManager();

		if ( ! $requirements->is_php_version_supported() ) {
			$notices->register(
				new AdminNotice(
					'error',
					$requirements->php_version_notice_message(),
					'cetech-de-php-version',
					false
				)
			);
			$notices->boot();

			return;
		}

		$this->register_services();
		$this->container->get( AdminNoticeManager::class )->boot();

		/** @var MigrationRunner $migration_runner */
		$migration_runner = $this->container->get( MigrationRunner::class );
		$migration_runner->run();

		if ( is_admin() ) {
			$this->container->get( AdminMenu::class )->register();
		}

		if ( ! $requirements->is_woocommerce_active() ) {
			$this->container->get( AdminNoticeManager::class )->register(
				new AdminNotice(
					'error',
					$requirements->woocommerce_missing_notice_message(),
					'cetech-de-woocommerce-missing',
					false
				)
			);

			return;
		}

		/** @var IntegrationRegistry $integrations */
		$integrations = $this->container->get( IntegrationRegistry::class );
		$integrations->detect();

		/** @var HealthCheckRegistry $health */
		$health = $this->container->get( HealthCheckRegistry::class );
		$health->run();

		$this->maybe_show_activation_notice();
	}

	public function container(): ServiceContainer {
		return $this->container;
	}

	private function register_services(): void {
		$this->container->singleton(
			FeatureFlags::class,
			static fn (): FeatureFlags => new FeatureFlags()
		);

		$this->container->singleton(
			Logger::class,
			static fn (): Logger => new Logger()
		);

		$this->container->singleton(
			AdminNoticeManager::class,
			static fn (): AdminNoticeManager => new AdminNoticeManager()
		);

		$this->container->singleton(
			Requirements::class,
			static fn (): Requirements => new Requirements()
		);

		$this->container->singleton(
			Capabilities::class,
			static fn (): Capabilities => new Capabilities()
		);

		$this->container->singleton(
			IntegrationRegistry::class,
			static fn ( ServiceContainer $container ): IntegrationRegistry => new IntegrationRegistry(
				$container->get( Logger::class )
			)
		);

		$this->container->singleton(
			MigrationRunner::class,
			static function ( ServiceContainer $container ): MigrationRunner {
				$runner = new MigrationRunner( $container->get( Logger::class ) );
				$runner->set_migrations(
					MigrationDiscovery::discover(
						CETECH_DE_PATH . 'database/migrations',
						$container->get( Logger::class )
					)
				);

				return $runner;
			}
		);

		$this->register_repository_bindings();

		$this->container->singleton(
			HealthCheckRegistry::class,
			static fn ( ServiceContainer $container ): HealthCheckRegistry => new HealthCheckRegistry(
				$container->get( Requirements::class ),
				$container->get( FeatureFlags::class ),
				$container->get( IntegrationRegistry::class )
			)
		);

		$this->container->singleton(
			SystemStatusPage::class,
			static fn ( ServiceContainer $container ): SystemStatusPage => new SystemStatusPage(
				$container->get( Requirements::class ),
				$container->get( FeatureFlags::class ),
				$container->get( IntegrationRegistry::class ),
				$container->get( Capabilities::class )
			)
		);

		$this->container->singleton(
			AdminMenu::class,
			static fn ( ServiceContainer $container ): AdminMenu => new AdminMenu(
				$container->get( SystemStatusPage::class )
			)
		);
	}

	private function register_repository_bindings(): void {
		$this->container->singleton(
			DeliveryOfferRepositoryInterface::class,
			static fn (): DeliveryOfferRepositoryInterface => new WpdbDeliveryOfferRepository()
		);

		$this->container->singleton(
			DestinationZoneRepositoryInterface::class,
			static fn (): DestinationZoneRepositoryInterface => new WpdbDestinationZoneRepository()
		);

		$this->container->singleton(
			LogisticsProfileRepositoryInterface::class,
			static fn (): LogisticsProfileRepositoryInterface => new WpdbLogisticsProfileRepository()
		);

		$this->container->singleton(
			SupplierRepositoryInterface::class,
			static fn (): SupplierRepositoryInterface => new WpdbSupplierRepository()
		);

		$this->container->singleton(
			OriginRepositoryInterface::class,
			static fn (): OriginRepositoryInterface => new WpdbOriginRepository()
		);

		$this->container->singleton(
			PickupLocationRepositoryInterface::class,
			static fn (): PickupLocationRepositoryInterface => new WpdbPickupLocationRepository()
		);

		$this->container->singleton(
			RateCardRepositoryInterface::class,
			static fn (): RateCardRepositoryInterface => new WpdbRateCardRepository()
		);

		$this->container->singleton(
			AuditLogRepositoryInterface::class,
			static fn (): AuditLogRepositoryInterface => new WpdbAuditLogRepository()
		);
	}

	private function maybe_show_activation_notice(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_transient( 'cetech_de_activation_notice' ) ) {
			return;
		}

		delete_transient( 'cetech_de_activation_notice' );

		$this->container->get( AdminNoticeManager::class )->register(
			new AdminNotice(
				'success',
				__(
					'CETECH WooCommerce Delivery Engine core foundation is active. Delivery features are not enabled yet.',
					'cetech-woocommerce-delivery-engine'
				),
				'cetech-de-activated',
				true
			)
		);
	}
}

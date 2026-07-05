<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Integrations\Registry;

use CetechDeliveryEngine\Support\Logger;

/**
 * Registry for optional integrations. Phase 1A: detection placeholders only.
 */
final class IntegrationRegistry {

	/** @var array<string, IntegrationInterface> */
	private array $integrations = [];

	/** @var array<string, bool>|null */
	private ?array $detection_statuses = null;

	public function __construct(
		private Logger $logger
	) {
		$this->integrations['null'] = new NullIntegration( 'null' );
	}

	public function register( IntegrationInterface $integration ): void {
		$this->integrations[ $integration->getKey() ] = $integration;
		$this->detection_statuses                     = null;
	}

	public function get( string $key ): IntegrationInterface {
		return $this->integrations[ $key ] ?? $this->integrations['null'];
	}

	/**
	 * @return array<string, bool>
	 */
	public function get_detection_statuses(): array {
		if ( null !== $this->detection_statuses ) {
			return $this->detection_statuses;
		}

		return $this->detection_statuses = [
			'wpml'        => defined( 'ICL_SITEPRESS_VERSION' ),
			'wcml'        => defined( 'WCML_VERSION' ) || class_exists( 'woocommerce_wpml' ),
			'woodmart'    => $this->is_woodmart_active(),
			'wcfm'        => class_exists( 'WCFM' ) || defined( 'WCFM_VERSION' ),
			'vitepos'     => defined( 'VITEPOS_VERSION' ) || class_exists( 'Vitepos\Apps\Apps' ),
			'redis'       => defined( 'WP_REDIS_VERSION' ) || class_exists( 'Redis' ),
			'wp_rocket'   => defined( 'WP_ROCKET_VERSION' ),
			'wc_blocks'   => class_exists( '\Automattic\WooCommerce\Blocks\Package' ),
			'woocommerce' => class_exists( 'WooCommerce' ),
		];
	}

	public function detect(): void {
		$statuses = $this->get_detection_statuses();

		$this->logger->info(
			'Optional integration detection completed.',
			[ 'integrations' => $statuses ]
		);

		foreach ( $this->integrations as $integration ) {
			if ( $integration instanceof NullIntegration ) {
				continue;
			}

			if ( $integration->isAvailable() ) {
				$integration->register();
			}
		}
	}

	private function is_woodmart_active(): bool {
		$theme = wp_get_theme();

		if ( 'woodmart' === strtolower( $theme->get_template() ) ) {
			return true;
		}

		return 'woodmart' === strtolower( $theme->get_stylesheet() );
	}
}

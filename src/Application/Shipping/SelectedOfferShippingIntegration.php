<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Shipping;

use CetechDeliveryEngine\Infrastructure\WooCommerce\Shipping\SelectedOfferShippingMethod;

/**
 * Registers the selected-offer WooCommerce shipping method when runtime gates allow.
 */
final class SelectedOfferShippingIntegration {

	public function __construct(
		private ShippingRateCalculationGate $gate
	) {
	}

	public function register(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		add_filter( 'woocommerce_shipping_methods', [ $this, 'register_shipping_method' ] );
	}

	/**
	 * @param array<string, class-string> $methods
	 *
	 * @return array<string, class-string>
	 */
	public function register_shipping_method( array $methods ): array {
		if ( ! $this->gate->is_runtime_active() ) {
			return $methods;
		}

		$methods[ SelectedOfferShippingMethod::METHOD_ID ] = SelectedOfferShippingMethod::class;

		return $methods;
	}
}

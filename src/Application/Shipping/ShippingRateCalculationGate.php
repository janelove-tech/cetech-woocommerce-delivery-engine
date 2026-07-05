<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Shipping;

use CetechDeliveryEngine\Bootstrap\FeatureFlags;
use CetechDeliveryEngine\Core\Requirements;

/**
 * Guards WooCommerce shipping rate calculation behind feature flags.
 */
final class ShippingRateCalculationGate {

	public const SHIPPING_FLAG = 'enable_woocommerce_shipping_rate_calculation';

	public function __construct(
		private FeatureFlags $feature_flags,
		private Requirements $requirements
	) {
	}

	public function is_shipping_flag_enabled(): bool {
		return $this->feature_flags->is_enabled( self::SHIPPING_FLAG );
	}

	public function is_upstream_ready(): bool {
		return $this->feature_flags->is_enabled( 'enable_product_delivery_selector' )
			&& $this->feature_flags->is_enabled( 'enable_cart_delivery_selection_capture' )
			&& $this->feature_flags->is_enabled( 'enable_checkout_delivery_selection_validation' );
	}

	public function is_runtime_active(): bool {
		return $this->is_shipping_flag_enabled()
			&& $this->is_upstream_ready()
			&& $this->requirements->is_woocommerce_active();
	}
}

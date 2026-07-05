<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Order;

use CetechDeliveryEngine\Application\Shipping\ShippingRateCalculationGate;
use CetechDeliveryEngine\Bootstrap\FeatureFlags;
use CetechDeliveryEngine\Core\Requirements;

/**
 * Guards order delivery snapshot persistence behind feature flags.
 */
final class OrderDeliverySnapshotGate {

	public const SNAPSHOT_FLAG = 'enable_order_delivery_snapshot_persistence';

	public function __construct(
		private FeatureFlags $feature_flags,
		private Requirements $requirements,
		private ShippingRateCalculationGate $shipping_gate
	) {
	}

	public function is_snapshot_flag_enabled(): bool {
		return $this->feature_flags->is_enabled( self::SNAPSHOT_FLAG );
	}

	public function is_upstream_ready(): bool {
		return $this->shipping_gate->is_runtime_active();
	}

	public function is_runtime_active(): bool {
		return $this->is_snapshot_flag_enabled()
			&& $this->is_upstream_ready()
			&& $this->requirements->is_woocommerce_active();
	}
}

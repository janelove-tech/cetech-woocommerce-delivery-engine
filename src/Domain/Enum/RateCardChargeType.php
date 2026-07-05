<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum RateCardChargeType: string {

	case FixedPerShipment = 'fixed_per_shipment';
	case FixedPerItem = 'fixed_per_item';
}

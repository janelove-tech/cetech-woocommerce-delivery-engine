<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum FulfilmentAvailability: string {

	case InternationalFulfilment = 'international_fulfilment';
	case InStore = 'in_store';
	case InWarehouse = 'in_warehouse';
}

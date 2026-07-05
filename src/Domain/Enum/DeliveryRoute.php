<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum DeliveryRoute: string {

	case Air = 'air';
	case Sea = 'sea';
	case LocalDelivery = 'local_delivery';
	case StorePickup = 'store_pickup';
}

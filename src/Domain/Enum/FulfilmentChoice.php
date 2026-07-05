<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum FulfilmentChoice: string {

	case Delivery = 'delivery';
	case StorePickup = 'store_pickup';
}

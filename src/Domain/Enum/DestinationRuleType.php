<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum DestinationRuleType: string {

	case Country = 'country';
	case Region = 'region';
	case City = 'city';
	case Postcode = 'postcode';
}

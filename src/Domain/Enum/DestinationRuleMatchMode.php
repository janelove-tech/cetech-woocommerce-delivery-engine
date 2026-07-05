<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum DestinationRuleMatchMode: string {

	case Exact = 'exact';
	case Prefix = 'prefix';
}

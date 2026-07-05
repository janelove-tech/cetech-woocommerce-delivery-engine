<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum ProductTargetType: string {

	case Product = 'product';
	case Variation = 'variation';
	case Category = 'category';
}

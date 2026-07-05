<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum RecordStatus: string {

	case Active = 'active';
	case Inactive = 'inactive';
	case Archived = 'archived';
}

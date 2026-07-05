<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\Enum;

enum CarrierVisibility: string {

	case Named = 'named';
	case AssignedByStore = 'assigned_by_store';
}

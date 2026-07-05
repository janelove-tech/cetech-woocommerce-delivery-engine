<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Diagnostics;

enum DiagnosticSeverity: string {

	case Ok = 'ok';
	case Info = 'info';
	case Warning = 'warning';
	case Error = 'error';
}

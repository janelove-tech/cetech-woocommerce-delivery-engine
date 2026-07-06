<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Result of checking whether an admin record can be permanently deleted.
 */
final class AdminDeleteDependencyResult {

	/**
	 * @param list<string> $blocking_reasons
	 */
	public function __construct(
		public readonly bool $can_delete,
		public readonly array $blocking_reasons = []
	) {
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Support;

/**
 * Immutable admin notice definition.
 */
final class AdminNotice {

	public function __construct(
		private string $type,
		private string $message,
		private string $id,
		private bool $dismissible = true
	) {
	}

	public function type(): string {
		return $this->type;
	}

	public function message(): string {
		return $this->message;
	}

	public function id(): string {
		return $this->id;
	}

	public function is_dismissible(): bool {
		return $this->dismissible;
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Diagnostics;

/**
 * Single admin-only configuration diagnostic result.
 *
 * Must not contain customer/order/payment data or private note text.
 */
final class ConfigurationDiagnostic {

	public function __construct(
		public readonly DiagnosticSeverity $severity,
		public readonly string $code,
		public readonly string $title,
		public readonly string $message,
		public readonly ?string $entity_type = null,
		public readonly ?int $entity_id = null,
		public readonly ?string $details = null
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'severity'    => $this->severity->value,
			'code'        => $this->code,
			'title'       => $this->title,
			'message'     => $this->message,
			'entity_type' => $this->entity_type,
			'entity_id'   => $this->entity_id,
			'details'     => $this->details,
		];
	}
}

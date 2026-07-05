<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\RateQuote;

use CetechDeliveryEngine\Domain\ValueObject\Money;

/**
 * Single priced quote line for admin/test display.
 */
final class RateQuoteLine {

	public function __construct(
		public readonly string $charge_type,
		public readonly Money $amount,
		public readonly int $quantity
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'charge_type' => $this->charge_type,
			'amount'      => $this->amount->amount(),
			'currency'    => $this->amount->currency()->value(),
			'quantity'    => $this->quantity,
		];
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\ValueObject;

/**
 * Immutable monetary amount with currency.
 */
final class Money {

	private string $amount;

	public function __construct(
		string $amount,
		private CurrencyCode $currency
	) {
		if ( ! is_numeric( $amount ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Money amount must be numeric: %s', $amount )
			);
		}

		$this->amount = $amount;
	}

	public function amount(): string {
		return $this->amount;
	}

	public function currency(): CurrencyCode {
		return $this->currency;
	}

	public function equals( self $other ): bool {
		return $this->amount === $other->amount
			&& $this->currency->equals( $other->currency );
	}
}

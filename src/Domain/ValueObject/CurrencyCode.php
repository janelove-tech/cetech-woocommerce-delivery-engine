<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\ValueObject;

/**
 * ISO 4217 currency code wrapper.
 */
final class CurrencyCode {

	private string $code;

	public function __construct( string $code ) {
		$normalized = strtoupper( trim( $code ) );

		if ( ! preg_match( '/^[A-Z]{3}$/', $normalized ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid currency code: %s', $code )
			);
		}

		$this->code = $normalized;
	}

	public function value(): string {
		return $this->code;
	}

	public function equals( self $other ): bool {
		return $this->code === $other->code;
	}
}

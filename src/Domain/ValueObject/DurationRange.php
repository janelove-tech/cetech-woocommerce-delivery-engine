<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Domain\ValueObject;

/**
 * Inclusive min/max duration range.
 */
final class DurationRange {

	public function __construct(
		private int $min,
		private int $max
	) {
		if ( $min < 0 || $max < 0 ) {
			throw new \InvalidArgumentException( 'Duration values must be zero or greater.' );
		}

		if ( $min > $max ) {
			throw new \InvalidArgumentException(
				sprintf( 'Duration min (%d) cannot exceed max (%d).', $min, $max )
			);
		}
	}

	public function min(): int {
		return $this->min;
	}

	public function max(): int {
		return $this->max;
	}

	public function equals( self $other ): bool {
		return $this->min === $other->min && $this->max === $other->max;
	}
}

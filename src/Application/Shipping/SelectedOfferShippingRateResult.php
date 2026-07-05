<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Shipping;

/**
 * Package shipping calculation outcome (server-side only).
 */
final class SelectedOfferShippingRateResult {

	public function __construct(
		public readonly bool $success,
		public readonly ?string $total_amount,
		public readonly ?string $currency,
		public readonly ?string $block_reason
	) {
	}

	public static function quoted( string $total_amount, string $currency ): self {
		return new self( true, $total_amount, $currency, null );
	}

	public static function blocked( string $block_reason ): self {
		return new self( false, null, null, $block_reason );
	}
}

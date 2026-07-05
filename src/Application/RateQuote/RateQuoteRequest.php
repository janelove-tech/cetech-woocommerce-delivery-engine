<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\RateQuote;

use CetechDeliveryEngine\Domain\ValueObject\CurrencyCode;

/**
 * Admin/server-side rate quote input (not customer-facing).
 */
final class RateQuoteRequest {

	public function __construct(
		public readonly int $delivery_offer_id,
		public readonly int $destination_zone_id,
		public readonly int $quantity,
		public readonly CurrencyCode $currency,
		public readonly ?int $product_id = null,
		public readonly ?int $variation_id = null,
		public readonly ?int $rule_id = null,
		public readonly ?int $logistics_profile_id = null,
		public readonly ?int $supplier_id = null,
		public readonly ?int $origin_id = null,
		public readonly ?string $fulfilment_availability = null,
		public readonly ?string $fulfilment_choice = null
	) {
		if ( $delivery_offer_id <= 0 ) {
			throw new \InvalidArgumentException( 'Delivery offer ID must be positive.' );
		}

		if ( $destination_zone_id <= 0 ) {
			throw new \InvalidArgumentException( 'Destination zone ID must be positive.' );
		}

		if ( $quantity <= 0 ) {
			throw new \InvalidArgumentException( 'Quantity must be a positive integer.' );
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self {
		return new self(
			(int) ( $data['delivery_offer_id'] ?? 0 ),
			(int) ( $data['destination_zone_id'] ?? 0 ),
			(int) ( $data['quantity'] ?? 0 ),
			new CurrencyCode( (string) ( $data['currency_code'] ?? '' ) ),
			isset( $data['product_id'] ) && (int) $data['product_id'] > 0 ? (int) $data['product_id'] : null,
			isset( $data['variation_id'] ) && (int) $data['variation_id'] > 0 ? (int) $data['variation_id'] : null,
			isset( $data['rule_id'] ) && (int) $data['rule_id'] > 0 ? (int) $data['rule_id'] : null,
			isset( $data['logistics_profile_id'] ) && (int) $data['logistics_profile_id'] > 0 ? (int) $data['logistics_profile_id'] : null,
			isset( $data['supplier_id'] ) && (int) $data['supplier_id'] > 0 ? (int) $data['supplier_id'] : null,
			isset( $data['origin_id'] ) && (int) $data['origin_id'] > 0 ? (int) $data['origin_id'] : null,
			isset( $data['fulfilment_availability'] ) ? sanitize_key( (string) $data['fulfilment_availability'] ) : null,
			isset( $data['fulfilment_choice'] ) ? sanitize_key( (string) $data['fulfilment_choice'] ) : null
		);
	}
}

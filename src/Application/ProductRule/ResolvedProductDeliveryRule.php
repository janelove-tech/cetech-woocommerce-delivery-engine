<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\ProductRule;

/**
 * Safe admin-facing view of a product delivery rule (no private notes).
 */
final class ResolvedProductDeliveryRule {

	/**
	 * @param list<int> $delivery_offer_ids
	 */
	public function __construct(
		public readonly int $rule_id,
		public readonly string $target_type,
		public readonly int $target_id,
		public readonly ?string $target_label_snapshot,
		public readonly int $target_specificity,
		public readonly string $fulfilment_availability,
		public readonly string $fulfilment_choice,
		public readonly array $delivery_offer_ids,
		public readonly ?int $logistics_profile_id,
		public readonly ?int $supplier_id,
		public readonly ?int $origin_id,
		public readonly int $priority
	) {
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<int>            $delivery_offer_ids
	 */
	public static function from_row( array $row, array $delivery_offer_ids, int $target_specificity ): self {
		return new self(
			(int) ( $row['id'] ?? 0 ),
			(string) ( $row['target_type'] ?? '' ),
			(int) ( $row['target_id'] ?? 0 ),
			isset( $row['target_label_snapshot'] ) ? (string) $row['target_label_snapshot'] : null,
			$target_specificity,
			(string) ( $row['fulfilment_availability'] ?? '' ),
			(string) ( $row['fulfilment_choice'] ?? '' ),
			$delivery_offer_ids,
			self::nullable_int( $row['logistics_profile_id'] ?? null ),
			self::nullable_int( $row['supplier_id'] ?? null ),
			self::nullable_int( $row['origin_id'] ?? null ),
			(int) ( $row['priority'] ?? 100 )
		);
	}

	private static function nullable_int( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$int = (int) $value;

		return $int > 0 ? $int : null;
	}
}

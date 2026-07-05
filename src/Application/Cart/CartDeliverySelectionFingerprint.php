<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Cart;

use CetechDeliveryEngine\Application\Selector\ProductDeliveryOptionsBuilder;
use CetechDeliveryEngine\Application\Selector\ProductDeliverySelectionIntent;

/**
 * Deterministic fingerprint for cart delivery selections (excludes issued_at).
 *
 * Hash format is stable since Phase 2E1; do not change without bumping compatibility docs.
 */
final class CartDeliverySelectionFingerprint {

	/**
	 * @param array<string, mixed> $intent
	 */
	public static function fromIntent( array $intent ): string {
		return hash( 'sha256', implode( '|', self::fingerprintParts( $intent ) ) );
	}

	/**
	 * Normalized parts used for fingerprinting and stale comparison.
	 *
	 * @param array<string, mixed> $intent
	 *
	 * @return list<string>
	 */
	public static function fingerprintParts( array $intent ): array {
		$normalized = self::normalizeIntentFields( $intent );

		return [
			(string) $normalized['product_id'],
			(string) $normalized['variation_id'],
			(string) $normalized['display_key'],
			(string) $normalized['fulfilment_availability'],
			(string) $normalized['fulfilment_choice'],
			(string) $normalized['delivery_offer_id'],
			(string) $normalized['rule_id'],
		];
	}

	/**
	 * @param array<string, mixed> $stored
	 * @param array<string, mixed> $current
	 */
	public static function matches( array $stored, array $current ): bool {
		return self::fromIntent( $stored ) === self::fromIntent( $current );
	}

	/**
	 * @param array<string, mixed> $intent
	 *
	 * @return array{
	 *     product_id: int,
	 *     variation_id: string,
	 *     display_key: string,
	 *     fulfilment_availability: string,
	 *     fulfilment_choice: string,
	 *     delivery_offer_id: string,
	 *     rule_id: string
	 * }
	 */
	public static function normalizeIntentFields( array $intent ): array {
		$variation_id = $intent['variation_id'] ?? null;

		return [
			'product_id'              => max( 0, (int) ( $intent['product_id'] ?? 0 ) ),
			'variation_id'            => ( null !== $variation_id && '' !== $variation_id )
				? (string) max( 0, (int) $variation_id )
				: '',
			'display_key'             => ProductDeliveryOptionsBuilder::normalizeDisplayKey( (string) ( $intent['display_key'] ?? '' ) ),
			'fulfilment_availability' => sanitize_key( (string) ( $intent['fulfilment_availability'] ?? '' ) ),
			'fulfilment_choice'       => sanitize_key( (string) ( $intent['fulfilment_choice'] ?? '' ) ),
			'delivery_offer_id'       => ( null !== ( $intent['delivery_offer_id'] ?? null ) && '' !== ( $intent['delivery_offer_id'] ?? '' ) )
				? (string) max( 0, (int) $intent['delivery_offer_id'] )
				: '',
			'rule_id'                 => ( null !== ( $intent['rule_id'] ?? null ) && '' !== ( $intent['rule_id'] ?? '' ) )
				? (string) max( 0, (int) $intent['rule_id'] )
				: '',
		];
	}

	/**
	 * @param array<string, mixed> $intent
	 */
	public static function isValidIntentShape( array $intent ): bool {
		if ( ProductDeliverySelectionIntent::CONTRACT_VERSION !== (string) ( $intent['contract_version'] ?? '' ) ) {
			return false;
		}

		$fields = self::normalizeIntentFields( $intent );

		if ( $fields['product_id'] <= 0 || '' === $fields['display_key'] ) {
			return false;
		}

		if ( '' === $fields['fulfilment_availability'] || '' === $fields['fulfilment_choice'] ) {
			return false;
		}

		return true;
	}
}

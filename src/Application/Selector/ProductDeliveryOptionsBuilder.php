<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Selector;

use CetechDeliveryEngine\Application\ProductRule\ProductRuleResolutionResult;
use CetechDeliveryEngine\Application\ProductRule\ResolvedProductDeliveryRule;
use CetechDeliveryEngine\Domain\DeliveryOffer\DeliveryOfferRepositoryInterface;
use CetechDeliveryEngine\Domain\Enum\FulfilmentAvailability;
use CetechDeliveryEngine\Domain\Enum\FulfilmentChoice;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;

/**
 * Builds customer-safe delivery options from resolver output and active delivery offers.
 *
 * Display-only; does not persist selections or calculate prices.
 */
final class ProductDeliveryOptionsBuilder {

	public function __construct(
		private DeliveryOfferRepositoryInterface $delivery_offer_repository
	) {
	}

	/**
	 * @return list<ProductDeliveryOption>
	 */
	public function buildFromResolution( ProductRuleResolutionResult $result ): array {
		$options = [];

		foreach ( $result->chosen_rules as $availability => $rule ) {
			if ( ! $rule instanceof ResolvedProductDeliveryRule ) {
				continue;
			}

			$availability_slug = (string) $availability;
			$availability_label = $this->availability_label( $availability_slug );

			if ( null === $availability_label ) {
				continue;
			}

			$choice_slug  = (string) $rule->fulfilment_choice;
			$choice_label = $this->choice_label( $choice_slug );

			if ( null === $choice_label ) {
				continue;
			}

			if ( FulfilmentChoice::StorePickup->value === $choice_slug ) {
				$options[] = $this->store_pickup_option( $availability_slug, $availability_label, $choice_slug, $choice_label );
				continue;
			}

			if ( FulfilmentChoice::Delivery->value !== $choice_slug ) {
				continue;
			}

			$offer_options = $this->delivery_offer_options(
				$availability_slug,
				$availability_label,
				$choice_slug,
				$choice_label,
				$rule->delivery_offer_ids
			);

			if ( [] === $offer_options ) {
				$options[] = $this->unavailable_delivery_option(
					$availability_slug,
					$availability_label,
					$choice_slug,
					$choice_label
				);
				continue;
			}

			foreach ( $offer_options as $option ) {
				$options[] = $option;
			}
		}

		return $options;
	}

	private function store_pickup_option(
		string $availability_slug,
		string $availability_label,
		string $choice_slug,
		string $choice_label
	): ProductDeliveryOption {
		$label = __( 'Store pickup available', 'cetech-woocommerce-delivery-engine' );

		return new ProductDeliveryOption(
			$this->display_key( $availability_slug, $choice_slug, 'pickup' ),
			$availability_slug,
			$availability_label,
			$choice_slug,
			$choice_label,
			null,
			$label,
			null,
			null,
			true,
			null
		);
	}

	/**
	 * @param list<int> $offer_ids
	 *
	 * @return list<ProductDeliveryOption>
	 */
	private function delivery_offer_options(
		string $availability_slug,
		string $availability_label,
		string $choice_slug,
		string $choice_label,
		array $offer_ids
	): array {
		$options = [];

		foreach ( $offer_ids as $offer_id ) {
			$row = $this->delivery_offer_repository->findById( (int) $offer_id );

			if ( null === $row ) {
				continue;
			}

			if ( RecordStatus::Active->value !== (string) ( $row['status'] ?? '' ) ) {
				continue;
			}

			$public_label = trim( (string) ( $row['public_label'] ?? '' ) );

			if ( '' === $public_label ) {
				$public_label = __( 'Delivery option', 'cetech-woocommerce-delivery-engine' );
			}

			$public_description = trim( (string) ( $row['public_description'] ?? '' ) );

			$options[] = new ProductDeliveryOption(
				$this->display_key( $availability_slug, $choice_slug, (string) $offer_id ),
				$availability_slug,
				$availability_label,
				$choice_slug,
				$choice_label,
				(int) $offer_id,
				$public_label,
				'' !== $public_description ? $public_description : null,
				$this->format_estimate_text( $row ),
				true,
				null
			);
		}

		return $options;
	}

	private function unavailable_delivery_option(
		string $availability_slug,
		string $availability_label,
		string $choice_slug,
		string $choice_label
	): ProductDeliveryOption {
		return new ProductDeliveryOption(
			$this->display_key( $availability_slug, $choice_slug, 'unavailable' ),
			$availability_slug,
			$availability_label,
			$choice_slug,
			$choice_label,
			null,
			__( 'Delivery unavailable', 'cetech-woocommerce-delivery-engine' ),
			__(
				'Delivery options for this product are not currently available.',
				'cetech-woocommerce-delivery-engine'
			),
			null,
			false,
			__(
				'No active delivery offers are configured for this product.',
				'cetech-woocommerce-delivery-engine'
			)
		);
	}

	private function display_key( string $availability, string $choice, string $suffix ): string {
		return sanitize_key( $availability ) . ':' . sanitize_key( $choice ) . ':' . sanitize_key( $suffix );
	}

	private function availability_label( string $availability ): ?string {
		foreach ( FulfilmentAvailability::cases() as $case ) {
			if ( $case->value === $availability ) {
				return match ( $case ) {
					FulfilmentAvailability::InternationalFulfilment => __( 'International fulfilment', 'cetech-woocommerce-delivery-engine' ),
					FulfilmentAvailability::InStore               => __( 'In store', 'cetech-woocommerce-delivery-engine' ),
					FulfilmentAvailability::InWarehouse             => __( 'In warehouse', 'cetech-woocommerce-delivery-engine' ),
				};
			}
		}

		return null;
	}

	private function choice_label( string $choice ): ?string {
		foreach ( FulfilmentChoice::cases() as $case ) {
			if ( $case->value === $choice ) {
				return match ( $case ) {
					FulfilmentChoice::Delivery    => __( 'Delivery', 'cetech-woocommerce-delivery-engine' ),
					FulfilmentChoice::StorePickup => __( 'Store pickup', 'cetech-woocommerce-delivery-engine' ),
				};
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $offer
	 */
	private function format_estimate_text( array $offer ): ?string {
		$total_min = 0;
		$total_max = 0;

		foreach ( [ 'default_processing', 'default_transit', 'default_final_mile' ] as $prefix ) {
			$min_key = $prefix . '_min';
			$max_key = $prefix . '_max';
			$min     = isset( $offer[ $min_key ] ) && '' !== $offer[ $min_key ] ? (int) $offer[ $min_key ] : 0;
			$max     = isset( $offer[ $max_key ] ) && '' !== $offer[ $max_key ] ? (int) $offer[ $max_key ] : 0;

			if ( $min > 0 ) {
				$total_min += $min;
			}

			if ( $max > 0 ) {
				$total_max += $max;
			}
		}

		if ( $total_min <= 0 && $total_max <= 0 ) {
			return null;
		}

		$unit = $this->duration_unit_label( (string) ( $offer['duration_unit'] ?? 'business_days' ) );

		if ( $total_min > 0 && $total_max > 0 && $total_min !== $total_max ) {
			return sprintf(
				/* translators: 1: minimum duration, 2: maximum duration, 3: duration unit label */
				__( 'Estimated %1$d–%2$d %3$s', 'cetech-woocommerce-delivery-engine' ),
				$total_min,
				$total_max,
				$unit
			);
		}

		$value = $total_max > 0 ? $total_max : $total_min;

		return sprintf(
			/* translators: 1: duration value, 2: duration unit label */
			__( 'Estimated %1$d %2$s', 'cetech-woocommerce-delivery-engine' ),
			$value,
			$unit
		);
	}

	private function duration_unit_label( string $unit ): string {
		return match ( $unit ) {
			'business_days' => __( 'business days', 'cetech-woocommerce-delivery-engine' ),
			'days'          => __( 'days', 'cetech-woocommerce-delivery-engine' ),
			default         => __( 'days', 'cetech-woocommerce-delivery-engine' ),
		};
	}
}

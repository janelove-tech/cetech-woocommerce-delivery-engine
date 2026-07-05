<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Frontend;

use CetechDeliveryEngine\Application\ProductRule\ProductDeliveryRuleResolver;
use CetechDeliveryEngine\Application\ProductRule\ProductRuleResolutionResult;
use CetechDeliveryEngine\Application\ProductRule\ResolvedProductDeliveryRule;
use CetechDeliveryEngine\Bootstrap\FeatureFlags;
use CetechDeliveryEngine\Core\Requirements;
use CetechDeliveryEngine\Domain\DeliveryOffer\DeliveryOfferRepositoryInterface;
use CetechDeliveryEngine\Domain\Enum\FulfilmentAvailability;
use CetechDeliveryEngine\Domain\Enum\FulfilmentChoice;
use CetechDeliveryEngine\Domain\Enum\ProductTargetType;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use WC_Product;

/**
 * Display-only product-page delivery selector (feature-flagged, off by default).
 *
 * Does not write cart data, checkout fields, order meta, or calculate shipping prices.
 */
final class ProductDeliverySelectorRenderer {

	public function __construct(
		private FeatureFlags $feature_flags,
		private Requirements $requirements,
		private ProductDeliveryRuleResolver $rule_resolver,
		private DeliveryOfferRepositoryInterface $delivery_offer_repository
	) {
	}

	public function register(): void {
		if ( ! $this->feature_flags->is_enabled( 'enable_product_delivery_selector' ) ) {
			return;
		}

		if ( ! $this->requirements->is_woocommerce_active() ) {
			return;
		}

		add_action( 'woocommerce_single_product_summary', [ $this, 'render' ], 25 );
	}

	public function render(): void {
		if ( ! $this->feature_flags->is_enabled( 'enable_product_delivery_selector' ) ) {
			return;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = $this->resolve_product();

		if ( null === $product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			$this->render_block(
				[
					[
						'type'    => 'notice',
						'message' => __(
							'Delivery options may update after selecting a product option.',
							'cetech-woocommerce-delivery-engine'
						),
					],
				]
			);

			return;
		}

		if ( ! $product->is_type( 'simple' ) && ! $product->is_type( 'variation' ) ) {
			return;
		}

		$target_type = $product->is_type( 'variation' )
			? ProductTargetType::Variation->value
			: ProductTargetType::Product->value;
		$target_id = (int) $product->get_id();

		$result = $this->rule_resolver->resolve( $target_type, $target_id );

		if ( ! $result->success ) {
			return;
		}

		$sections = $this->build_sections( $result );

		if ( [] === $sections ) {
			$this->render_block(
				[
					[
						'type'    => 'notice',
						'message' => __(
							'Delivery options are not available for this product.',
							'cetech-woocommerce-delivery-engine'
						),
					],
				]
			);

			return;
		}

		$this->render_block( $sections );
	}

	private function resolve_product(): ?WC_Product {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$post_id = get_the_ID();
		$loaded  = is_int( $post_id ) ? wc_get_product( $post_id ) : false;

		return $loaded instanceof WC_Product ? $loaded : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function build_sections( ProductRuleResolutionResult $result ): array {
		$sections = [];

		foreach ( FulfilmentAvailability::cases() as $availability_case ) {
			$availability = $availability_case->value;
			$rule         = $result->chosen_rules[ $availability ] ?? null;

			if ( ! $rule instanceof ResolvedProductDeliveryRule ) {
				continue;
			}

			$section = [
				'type'         => 'availability',
				'availability' => $this->availability_label( $availability ),
				'choice'       => $this->choice_label( (string) $rule->fulfilment_choice ),
				'items'        => [],
			];

			if ( FulfilmentChoice::StorePickup->value === $rule->fulfilment_choice ) {
				$section['items'][] = [
					'label'       => __( 'Store pickup available', 'cetech-woocommerce-delivery-engine' ),
					'description' => null,
					'estimate'    => null,
				];
			} else {
				$offers = $this->load_active_public_offers( $rule->delivery_offer_ids );

				if ( [] === $offers ) {
					$section['items'][] = [
						'label'       => __( 'Delivery unavailable', 'cetech-woocommerce-delivery-engine' ),
						'description' => __(
							'Delivery options for this product are not currently available.',
							'cetech-woocommerce-delivery-engine'
						),
						'estimate'    => null,
					];
				} else {
					foreach ( $offers as $offer ) {
						$section['items'][] = $offer;
					}
				}
			}

			$sections[] = $section;
		}

		return $sections;
	}

	/**
	 * @param list<int> $offer_ids
	 *
	 * @return list<array{label: string, description: string|null, estimate: string|null}>
	 */
	private function load_active_public_offers( array $offer_ids ): array {
		$offers = [];

		foreach ( $offer_ids as $offer_id ) {
			$row = $this->delivery_offer_repository->findById( (int) $offer_id );

			if ( null === $row ) {
				continue;
			}

			if ( RecordStatus::Active->value !== (string) ( $row['status'] ?? '' ) ) {
				continue;
			}

			$label = trim( (string) ( $row['public_label'] ?? '' ) );

			if ( '' === $label ) {
				$label = __( 'Delivery option', 'cetech-woocommerce-delivery-engine' );
			}

			$description = trim( (string) ( $row['public_description'] ?? '' ) );

			$offers[] = [
				'label'       => $label,
				'description' => '' !== $description ? $description : null,
				'estimate'    => $this->format_estimate_text( $row ),
			];
		}

		return $offers;
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

	private function availability_label( string $availability ): string {
		return match ( $availability ) {
			FulfilmentAvailability::InternationalFulfilment->value => __( 'International fulfilment', 'cetech-woocommerce-delivery-engine' ),
			FulfilmentAvailability::InStore->value                 => __( 'In store', 'cetech-woocommerce-delivery-engine' ),
			FulfilmentAvailability::InWarehouse->value             => __( 'In warehouse', 'cetech-woocommerce-delivery-engine' ),
			default                                                => $availability,
		};
	}

	private function choice_label( string $choice ): string {
		return match ( $choice ) {
			FulfilmentChoice::Delivery->value    => __( 'Delivery', 'cetech-woocommerce-delivery-engine' ),
			FulfilmentChoice::StorePickup->value => __( 'Store pickup', 'cetech-woocommerce-delivery-engine' ),
			default                              => $choice,
		};
	}

	/**
	 * @param list<array<string, mixed>> $sections
	 */
	private function render_block( array $sections ): void {
		echo '<div class="cetech-de-product-delivery-selector">';
		echo '<h3 class="cetech-de-delivery-selector__title">' . esc_html__( 'Delivery options', 'cetech-woocommerce-delivery-engine' ) . '</h3>';

		foreach ( $sections as $section ) {
			if ( 'notice' === ( $section['type'] ?? '' ) ) {
				echo '<p class="cetech-de-delivery-selector__notice">' . esc_html( (string) ( $section['message'] ?? '' ) ) . '</p>';
				continue;
			}

			echo '<div class="cetech-de-delivery-availability">';
			echo '<h4 class="cetech-de-delivery-availability__heading">' . esc_html( (string) ( $section['availability'] ?? '' ) ) . '</h4>';
			echo '<p class="cetech-de-delivery-availability__choice"><em>' . esc_html( (string) ( $section['choice'] ?? '' ) ) . '</em></p>';
			echo '<ul class="cetech-de-delivery-options">';

			foreach ( (array) ( $section['items'] ?? [] ) as $item ) {
				echo '<li class="cetech-de-delivery-option">';
				echo '<span class="cetech-de-delivery-option__label">' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</span>';

				if ( ! empty( $item['description'] ) ) {
					echo '<span class="cetech-de-delivery-option__description"> ' . esc_html( (string) $item['description'] ) . '</span>';
				}

				if ( ! empty( $item['estimate'] ) ) {
					echo '<span class="cetech-de-delivery-option__estimate"> ' . esc_html( (string) $item['estimate'] ) . '</span>';
				}

				echo '</li>';
			}

			echo '</ul>';
			echo '</div>';
		}

		echo '</div>';
	}
}

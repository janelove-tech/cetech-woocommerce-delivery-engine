<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\ProductRule\ProductDeliveryRuleRepositoryInterface;
use CetechDeliveryEngine\Domain\RateCard\RateCardRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;

/**
 * Read-only dependency checks before permanent admin deletes.
 */
final class AdminRecordDependencyChecker {

	public function __construct(
		private RateCardRepositoryInterface $rate_card_repository,
		private OriginRepositoryInterface $origin_repository,
		private ProductDeliveryRuleRepositoryInterface $product_rule_repository
	) {
	}

	public function check_delivery_offer( int $id ): AdminDeleteDependencyResult {
		$reasons = [];
		$count   = $this->rate_card_repository->countByDeliveryOfferId( $id );

		if ( $count > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of rate cards */
				_n(
					'%d rate card still references this delivery offer.',
					'%d rate cards still reference this delivery offer.',
					$count,
					'cetech-woocommerce-delivery-engine'
				),
				$count
			);
		}

		return new AdminDeleteDependencyResult( [] === $reasons, $reasons );
	}

	public function check_destination_zone( int $id ): AdminDeleteDependencyResult {
		$reasons = [];
		$count   = $this->rate_card_repository->countByDestinationZoneId( $id );

		if ( $count > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of rate cards */
				_n(
					'%d rate card still references this destination zone.',
					'%d rate cards still reference this destination zone.',
					$count,
					'cetech-woocommerce-delivery-engine'
				),
				$count
			);
		}

		return new AdminDeleteDependencyResult( [] === $reasons, $reasons );
	}

	public function check_supplier( int $id ): AdminDeleteDependencyResult {
		$reasons = [];
		$origins = $this->origin_repository->countBySupplierId( $id );

		if ( $origins > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of origins */
				_n(
					'%d origin still references this supplier.',
					'%d origins still reference this supplier.',
					$origins,
					'cetech-woocommerce-delivery-engine'
				),
				$origins
			);
		}

		$rules = $this->product_rule_repository->countBySupplierId( $id );

		if ( $rules > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of product rules */
				_n(
					'%d product rule still references this supplier.',
					'%d product rules still reference this supplier.',
					$rules,
					'cetech-woocommerce-delivery-engine'
				),
				$rules
			);
		}

		return new AdminDeleteDependencyResult( [] === $reasons, $reasons );
	}

	public function check_origin( int $id ): AdminDeleteDependencyResult {
		$reasons = [];
		$rules   = $this->product_rule_repository->countByOriginId( $id );

		if ( $rules > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of product rules */
				_n(
					'%d product rule still references this origin.',
					'%d product rules still reference this origin.',
					$rules,
					'cetech-woocommerce-delivery-engine'
				),
				$rules
			);
		}

		return new AdminDeleteDependencyResult( [] === $reasons, $reasons );
	}

	public function check_logistics_profile( int $id ): AdminDeleteDependencyResult {
		$reasons = [];
		$rules   = $this->product_rule_repository->countByLogisticsProfileId( $id );

		if ( $rules > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of product rules */
				_n(
					'%d product rule still references this logistics profile.',
					'%d product rules still reference this logistics profile.',
					$rules,
					'cetech-woocommerce-delivery-engine'
				),
				$rules
			);
		}

		$rate_cards = $this->rate_card_repository->countByLogisticsProfileId( $id );

		if ( $rate_cards > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of rate cards */
				_n(
					'%d rate card still references this logistics profile.',
					'%d rate cards still reference this logistics profile.',
					$rate_cards,
					'cetech-woocommerce-delivery-engine'
				),
				$rate_cards
			);
		}

		return new AdminDeleteDependencyResult( [] === $reasons, $reasons );
	}

	public function check_pickup_location( int $id ): AdminDeleteDependencyResult {
		unset( $id );

		return new AdminDeleteDependencyResult( true );
	}

	public function check_product_rule( int $id ): AdminDeleteDependencyResult {
		unset( $id );

		return new AdminDeleteDependencyResult( true );
	}

	public function check_rate_card( int $id ): AdminDeleteDependencyResult {
		$reasons    = [];
		$snapshots  = $this->rate_card_repository->countOrderSnapshotReferences( $id );

		if ( $snapshots > 0 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of order snapshots */
				_n(
					'%d order delivery snapshot still references this rate card.',
					'%d order delivery snapshots still reference this rate card.',
					$snapshots,
					'cetech-woocommerce-delivery-engine'
				),
				$snapshots
			);
		}

		return new AdminDeleteDependencyResult( [] === $reasons, $reasons );
	}
}

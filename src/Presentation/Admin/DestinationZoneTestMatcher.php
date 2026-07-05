<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\Enum\DestinationRuleMatchMode;
use CetechDeliveryEngine\Domain\Enum\DestinationRuleType;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\Zone\DestinationRuleRepositoryInterface;
use CetechDeliveryEngine\Domain\Zone\DestinationZoneRepositoryInterface;

/**
 * Admin-only read-only destination zone matcher for configuration testing.
 */
final class DestinationZoneTestMatcher {

	public function __construct(
		private DestinationZoneRepositoryInterface $zone_repository,
		private DestinationRuleRepositoryInterface $rule_repository
	) {
	}

	/**
	 * @return array<string, mixed>|null Matched zone row or null.
	 */
	public function match(
		string $country_code,
		string $region,
		string $city,
		string $postcode
	): ?array {
		$country_code = strtoupper( trim( $country_code ) );
		$region       = strtolower( trim( $region ) );
		$city         = strtolower( trim( $city ) );
		$postcode     = strtoupper( trim( $postcode ) );

		$zones = $this->zone_repository->list(
			[
				'status' => RecordStatus::Active->value,
				'limit'  => 500,
			]
		);

		$candidates = [];
		$fallback   = null;

		foreach ( $zones as $zone ) {
			$zone_id = (int) ( $zone['id'] ?? 0 );

			if ( $zone_id <= 0 ) {
				continue;
			}

			if ( ! empty( $zone['is_fallback'] ) ) {
				$fallback = $zone;
			}

			$rules = $this->rule_repository->listByZoneId( $zone_id );

			if ( [] === $rules ) {
				continue;
			}

			if ( $this->zone_matches_address( $rules, $country_code, $region, $city, $postcode ) ) {
				$candidates[] = $zone;
			}
		}

		if ( [] !== $candidates ) {
			usort(
				$candidates,
				static function ( array $left, array $right ): int {
					$left_priority  = (int) ( $left['priority'] ?? 100 );
					$right_priority = (int) ( $right['priority'] ?? 100 );

					if ( $left_priority === $right_priority ) {
						return (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
					}

					return $left_priority <=> $right_priority;
				}
			);

			return $candidates[0];
		}

		return $fallback;
	}

	/**
	 * @param list<array<string, mixed>> $rules
	 */
	private function zone_matches_address(
		array $rules,
		string $country_code,
		string $region,
		string $city,
		string $postcode
	): bool {
		foreach ( $rules as $rule ) {
			if ( ! $this->rule_matches( $rule, $country_code, $region, $city, $postcode ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function rule_matches(
		array $rule,
		string $country_code,
		string $region,
		string $city,
		string $postcode
	): bool {
		$rule_type  = (string) ( $rule['rule_type'] ?? '' );
		$rule_value = trim( (string) ( $rule['rule_value'] ?? '' ) );
		$match_mode = (string) ( $rule['match_mode'] ?? DestinationRuleMatchMode::Exact->value );

		if ( '' === $rule_value ) {
			return true;
		}

		return match ( $rule_type ) {
			DestinationRuleType::Country->value => '' !== $country_code
				&& strtoupper( $rule_value ) === $country_code,
			DestinationRuleType::Region->value => '' !== $region
				&& strtolower( $rule_value ) === $region,
			DestinationRuleType::City->value => '' !== $city
				&& strtolower( $rule_value ) === $city,
			DestinationRuleType::Postcode->value => '' !== $postcode && $this->postcode_matches( $postcode, strtoupper( $rule_value ), $match_mode ),
			default => false,
		};
	}

	private function postcode_matches( string $postcode, string $rule_value, string $match_mode ): bool {
		if ( DestinationRuleMatchMode::Prefix->value === $match_mode ) {
			return str_starts_with( $postcode, $rule_value );
		}

		return $postcode === $rule_value;
	}
}

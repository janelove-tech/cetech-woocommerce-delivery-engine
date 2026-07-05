<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Application\Destination\DestinationZoneMatcher;

/**
 * Admin-only read-only destination zone matcher for configuration testing.
 */
final class DestinationZoneTestMatcher {

	public function __construct(
		private DestinationZoneMatcher $zone_matcher
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
		return $this->zone_matcher->match( $country_code, $region, $city, $postcode );
	}
}

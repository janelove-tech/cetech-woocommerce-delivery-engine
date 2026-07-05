<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin\Validation;

use CetechDeliveryEngine\Domain\Enum\DeliveryRoute;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Presentation\Admin\AdminFormHelper;

final class LogisticsProfileValidator {

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return array<string, string>
	 */
	public function validate( array $input, ?int $existing_id = null ): array {
		$errors = [];

		$code = AdminFormHelper::sanitize_code( (string) ( $input['code'] ?? '' ) );

		if ( '' === $code ) {
			$errors['code'] = __( 'Code is required.', 'cetech-woocommerce-delivery-engine' );
		} elseif ( ! AdminFormHelper::is_valid_code( $code ) ) {
			$errors['code'] = __( 'Code may contain lowercase letters, numbers, underscores, and hyphens only.', 'cetech-woocommerce-delivery-engine' );
		}

		$name = trim( (string) ( $input['name'] ?? '' ) );

		if ( '' === $name ) {
			$errors['name'] = __( 'Name is required.', 'cetech-woocommerce-delivery-engine' );
		}

		$status = (string) ( $input['status'] ?? '' );

		if ( ! $this->is_valid_status( $status ) ) {
			$errors['status'] = __( 'Invalid status selected.', 'cetech-woocommerce-delivery-engine' );
		}

		$consolidation = trim( (string) ( $input['consolidation_policy'] ?? '' ) );

		if ( strlen( $consolidation ) > 64 ) {
			$errors['consolidation_policy'] = __( 'Consolidation policy must be 64 characters or fewer.', 'cetech-woocommerce-delivery-engine' );
		}

		return $errors;
	}

	/**
	 * @param list<string> $routes
	 */
	public function encode_route_eligibility( array $routes ): ?string {
		$allowed = array_map(
			static fn ( DeliveryRoute $route ): string => $route->value,
			DeliveryRoute::cases()
		);

		$filtered = array_values(
			array_intersect(
				array_map( 'strval', $routes ),
				$allowed
			)
		);

		if ( [] === $filtered ) {
			return null;
		}

		$encoded = wp_json_encode( $filtered );

		return false !== $encoded ? $encoded : null;
	}

	/**
	 * @return list<string>
	 */
	public function decode_route_eligibility( ?string $stored ): array {
		if ( null === $stored || '' === $stored ) {
			return [];
		}

		$decoded = json_decode( $stored, true );

		return is_array( $decoded ) ? array_map( 'strval', $decoded ) : [];
	}

	private function is_valid_status( string $status ): bool {
		foreach ( RecordStatus::cases() as $case ) {
			if ( $case->value === $status ) {
				return true;
			}
		}

		return false;
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin\Validation;

use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\Supplier\SupplierRepositoryInterface;
use CetechDeliveryEngine\Presentation\Admin\AdminFormHelper;

final class OriginValidator {

	public function __construct(
		private SupplierRepositoryInterface $supplier_repository
	) {
	}

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return array<string, string>
	 */
	public function validate( array $input, ?int $existing_id = null ): array {
		unset( $existing_id );

		$errors = [];

		$code = AdminFormHelper::sanitize_code( (string) ( $input['code'] ?? '' ) );

		if ( '' === $code ) {
			$errors['code'] = __( 'Code is required.', 'cetech-woocommerce-delivery-engine' );
		} elseif ( ! AdminFormHelper::is_valid_code( $code ) ) {
			$errors['code'] = __( 'Code may contain lowercase letters, numbers, underscores, and hyphens only.', 'cetech-woocommerce-delivery-engine' );
		}

		$name = trim( (string) ( $input['internal_name'] ?? '' ) );

		if ( '' === $name ) {
			$errors['internal_name'] = __( 'Internal name is required.', 'cetech-woocommerce-delivery-engine' );
		}

		$supplier_id = isset( $input['supplier_id'] ) ? (int) $input['supplier_id'] : 0;

		if ( $supplier_id <= 0 ) {
			$errors['supplier_id'] = __( 'Supplier is required.', 'cetech-woocommerce-delivery-engine' );
		} elseif ( null === $this->supplier_repository->findById( $supplier_id ) ) {
			$errors['supplier_id'] = __( 'Selected supplier does not exist.', 'cetech-woocommerce-delivery-engine' );
		}

		$country_code = strtoupper( trim( (string) ( $input['country_code'] ?? '' ) ) );

		if ( '' !== $country_code && ! preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			$errors['country_code'] = __( 'Country code must be a 2-letter ISO code.', 'cetech-woocommerce-delivery-engine' );
		}

		$min_days = $this->nullable_int( $input['dispatch_lead_days_min'] ?? null );
		$max_days = $this->nullable_int( $input['dispatch_lead_days_max'] ?? null );

		if ( null !== $min_days && null !== $max_days && $min_days > $max_days ) {
			$errors['dispatch_lead_days_min'] = __( 'Minimum dispatch lead days cannot exceed maximum.', 'cetech-woocommerce-delivery-engine' );
		}

		$status = (string) ( $input['status'] ?? '' );

		if ( ! $this->is_valid_status( $status ) ) {
			$errors['status'] = __( 'Invalid status selected.', 'cetech-woocommerce-delivery-engine' );
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function encode_internal_address( array $input ): ?string {
		$parts = [
			'summary' => trim( (string) ( $input['address_summary'] ?? '' ) ),
			'region'  => trim( (string) ( $input['region'] ?? '' ) ),
			'city'    => trim( (string) ( $input['city'] ?? '' ) ),
		];

		if ( '' === implode( '', $parts ) ) {
			return null;
		}

		$encoded = wp_json_encode( $parts );

		return false !== $encoded ? $encoded : null;
	}

	/**
	 * @return array{address_summary: string, region: string, city: string}
	 */
	public function decode_internal_address( ?string $stored ): array {
		if ( null === $stored || '' === $stored ) {
			return $this->empty_address();
		}

		$decoded = json_decode( $stored, true );

		if ( ! is_array( $decoded ) ) {
			return [
				'address_summary' => $stored,
				'region'          => '',
				'city'            => '',
			];
		}

		return [
			'address_summary' => (string) ( $decoded['summary'] ?? '' ),
			'region'          => (string) ( $decoded['region'] ?? '' ),
			'city'            => (string) ( $decoded['city'] ?? '' ),
		];
	}

	/**
	 * @return array{address_summary: string, region: string, city: string}
	 */
	private function empty_address(): array {
		return [
			'address_summary' => '',
			'region'          => '',
			'city'            => '',
		];
	}

	private function nullable_int( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return max( 0, (int) $value );
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

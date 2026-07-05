<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin\Validation;

use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Presentation\Admin\AdminFormHelper;

final class SupplierValidator {

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

		$email = trim( (string) ( $input['contact_email'] ?? '' ) );

		if ( '' !== $email && ! is_email( $email ) ) {
			$errors['contact_email'] = __( 'Contact email is not valid.', 'cetech-woocommerce-delivery-engine' );
		}

		$status = (string) ( $input['status'] ?? '' );

		if ( ! $this->is_valid_status( $status ) ) {
			$errors['status'] = __( 'Invalid status selected.', 'cetech-woocommerce-delivery-engine' );
		}

		return $errors;
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

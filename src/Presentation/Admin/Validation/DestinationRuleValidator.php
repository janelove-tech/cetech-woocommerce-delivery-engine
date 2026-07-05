<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin\Validation;

use CetechDeliveryEngine\Domain\Enum\DestinationRuleMatchMode;
use CetechDeliveryEngine\Domain\Enum\DestinationRuleType;

final class DestinationRuleValidator {

	/**
	 * @param list<array<string, mixed>> $rows
	 *
	 * @return array{errors: array<string, string>, rules: list<array<string, mixed>>}
	 */
	public function validate_and_normalize( array $rows ): array {
		$errors = [];
		$rules  = [];

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rule_type  = sanitize_key( (string) ( $row['rule_type'] ?? '' ) );
			$rule_value = trim( (string) ( $row['rule_value'] ?? '' ) );
			$match_mode = sanitize_key( (string) ( $row['match_mode'] ?? DestinationRuleMatchMode::Exact->value ) );
			$priority   = $row['priority'] ?? 100;

			if ( '' === $rule_type && '' === $rule_value ) {
				continue;
			}

			$row_label = sprintf(
				/* translators: %d: 1-based rule row number */
				__( 'Rule row %d', 'cetech-woocommerce-delivery-engine' ),
				$index + 1
			);

			if ( '' === $rule_type ) {
				$errors[ 'rule_' . $index . '_type' ] = $row_label . ': ' . __( 'Rule type is required.', 'cetech-woocommerce-delivery-engine' );
				continue;
			}

			if ( ! $this->is_valid_rule_type( $rule_type ) ) {
				$errors[ 'rule_' . $index . '_type' ] = $row_label . ': ' . __( 'Invalid rule type.', 'cetech-woocommerce-delivery-engine' );
				continue;
			}

			if ( '' === $rule_value ) {
				$errors[ 'rule_' . $index . '_value' ] = $row_label . ': ' . __( 'Rule value is required.', 'cetech-woocommerce-delivery-engine' );
				continue;
			}

			if ( ! $this->is_valid_match_mode( $match_mode ) ) {
				$errors[ 'rule_' . $index . '_mode' ] = $row_label . ': ' . __( 'Invalid match mode.', 'cetech-woocommerce-delivery-engine' );
				continue;
			}

			if ( DestinationRuleType::Postcode->value !== $rule_type && DestinationRuleMatchMode::Prefix->value === $match_mode ) {
				$errors[ 'rule_' . $index . '_mode' ] = $row_label . ': ' . __( 'Prefix match mode is only valid for postcodes.', 'cetech-woocommerce-delivery-engine' );
				continue;
			}

			if ( DestinationRuleType::Country->value === $rule_type ) {
				$rule_value = strtoupper( $rule_value );

				if ( ! preg_match( '/^[A-Z]{2}$/', $rule_value ) ) {
					$errors[ 'rule_' . $index . '_value' ] = $row_label . ': ' . __( 'Country code must be a 2-letter ISO code.', 'cetech-woocommerce-delivery-engine' );
					continue;
				}
			}

			if ( '' !== $priority && ! is_numeric( $priority ) ) {
				$errors[ 'rule_' . $index . '_priority' ] = $row_label . ': ' . __( 'Priority must be an integer.', 'cetech-woocommerce-delivery-engine' );
				continue;
			}

			$rules[] = [
				'rule_type'  => $rule_type,
				'rule_value' => substr( $rule_value, 0, 255 ),
				'match_mode' => $match_mode,
				'priority'   => (int) $priority,
			];
		}

		return [
			'errors' => $errors,
			'rules'  => $rules,
		];
	}

	private function is_valid_rule_type( string $rule_type ): bool {
		foreach ( DestinationRuleType::cases() as $case ) {
			if ( $case->value === $rule_type ) {
				return true;
			}
		}

		return false;
	}

	private function is_valid_match_mode( string $match_mode ): bool {
		foreach ( DestinationRuleMatchMode::cases() as $case ) {
			if ( $case->value === $match_mode ) {
				return true;
			}
		}

		return false;
	}
}

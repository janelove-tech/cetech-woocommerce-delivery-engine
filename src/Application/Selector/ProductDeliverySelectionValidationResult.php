<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Selector;

/**
 * Outcome of validating a display_key against current product delivery options.
 */
final class ProductDeliverySelectionValidationResult {

	/**
	 * @param array<string, mixed>|null       $matched_option Safe ProductDeliveryOption array
	 * @param array<string, mixed>|null       $intent         Safe ProductDeliverySelectionIntent array when valid
	 * @param list<string>                    $warnings
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly ?string $error_code,
		public readonly ?string $error_message,
		public readonly ?array $matched_option,
		public readonly ?array $intent,
		public readonly array $warnings
	) {
	}

	public static function invalid( string $code, string $message, array $warnings = [] ): self {
		return new self( false, $code, $message, null, null, $warnings );
	}

	/**
	 * @param list<string> $warnings
	 */
	public static function valid( ProductDeliveryOption $option, ProductDeliverySelectionIntent $intent, array $warnings = [] ): self {
		return new self(
			true,
			null,
			null,
			$option->toArray(),
			$intent->toArray(),
			$warnings
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'valid'          => $this->valid,
			'error_code'     => $this->error_code,
			'error_message'  => $this->error_message,
			'matched_option' => $this->matched_option,
			'intent'         => $this->intent,
			'warnings'       => $this->warnings,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self {
		$warnings = [];

		if ( isset( $data['warnings'] ) && is_array( $data['warnings'] ) ) {
			foreach ( $data['warnings'] as $warning ) {
				$warnings[] = (string) $warning;
			}
		}

		return new self(
			! empty( $data['valid'] ),
			isset( $data['error_code'] ) ? (string) $data['error_code'] : null,
			isset( $data['error_message'] ) ? (string) $data['error_message'] : null,
			is_array( $data['matched_option'] ?? null ) ? $data['matched_option'] : null,
			is_array( $data['intent'] ?? null ) ? $data['intent'] : null,
			$warnings
		);
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\ProductRule;

/**
 * Admin-only product rule resolution outcome.
 */
final class ProductRuleResolutionResult {

	/**
	 * @param list<array{target_type: string, target_id: int, label: string|null, order: int}> $candidate_hierarchy
	 * @param list<ResolvedProductDeliveryRule>                                                $matched_rules
	 * @param array<string, ResolvedProductDeliveryRule>                                     $chosen_rules keyed by fulfilment_availability
	 * @param list<array{rule_id: int, reason: string}>                                      $skipped_rules
	 * @param list<string>                                                                   $warnings
	 */
	public function __construct(
		public readonly bool $success,
		public readonly ?string $error,
		public readonly string $input_target_type,
		public readonly int $input_target_id,
		public readonly ?string $input_target_label,
		public readonly array $candidate_hierarchy,
		public readonly array $matched_rules,
		public readonly array $chosen_rules,
		public readonly array $skipped_rules,
		public readonly array $warnings,
		public readonly ?string $no_match_message
	) {
	}

	public static function failure( string $target_type, int $target_id, string $error ): self {
		return new self(
			false,
			$error,
			$target_type,
			$target_id,
			null,
			[],
			[],
			[],
			[],
			[],
			null
		);
	}
}

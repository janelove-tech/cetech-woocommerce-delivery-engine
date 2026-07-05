<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\ProductRule;

/**
 * Admin-only product rule resolution outcome.
 */
final class ProductRuleResolutionResult {

	public const CONTRACT_VERSION = '1';

	/**
	 * @param list<array{target_type: string, target_id: int, label: string|null, order: int}> $candidate_hierarchy
	 * @param list<ResolvedProductDeliveryRule>                                                $matched_rules
	 * @param array<string, ResolvedProductDeliveryRule>                                     $chosen_rules keyed by fulfilment_availability
	 * @param array<string, string>                                                          $chosen_explanations keyed by fulfilment_availability
	 * @param list<array{rule_id: int, reason: string, code?: string}>                      $skipped_rules
	 * @param list<string>                                                                   $warnings
	 */
	public function __construct(
		public readonly bool $success,
		public readonly ?string $error,
		public readonly string $input_target_type,
		public readonly int $input_target_id,
		public readonly ?string $input_target_label,
		public readonly array $candidate_hierarchy,
		public readonly string $hierarchy_explanation,
		public readonly array $matched_rules,
		public readonly array $chosen_rules,
		public readonly array $chosen_explanations,
		public readonly array $skipped_rules,
		public readonly array $warnings,
		public readonly ?string $no_match_message,
		public readonly string $contract_version = self::CONTRACT_VERSION
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
			'',
			[],
			[],
			[],
			[],
			[],
			null
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		$matched = [];

		foreach ( $this->matched_rules as $rule ) {
			$matched[] = $rule instanceof ResolvedProductDeliveryRule
				? $rule->toArray()
				: ( is_array( $rule ) ? $rule : [] );
		}

		$chosen = [];

		foreach ( $this->chosen_rules as $availability => $rule ) {
			$chosen[ $availability ] = $rule instanceof ResolvedProductDeliveryRule
				? $rule->toArray()
				: ( is_array( $rule ) ? $rule : [] );
		}

		return [
			'contract_version'      => $this->contract_version,
			'success'               => $this->success,
			'error'                 => $this->error,
			'input_target_type'     => $this->input_target_type,
			'input_target_id'       => $this->input_target_id,
			'input_target_label'    => $this->input_target_label,
			'candidate_hierarchy'   => $this->candidate_hierarchy,
			'hierarchy_explanation' => $this->hierarchy_explanation,
			'matched_rules'         => $matched,
			'chosen_rules'          => $chosen,
			'chosen_explanations'   => $this->chosen_explanations,
			'skipped_rules'         => $this->skipped_rules,
			'warnings'              => $this->warnings,
			'no_match_message'      => $this->no_match_message,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self {
		$matched = [];

		if ( isset( $data['matched_rules'] ) && is_array( $data['matched_rules'] ) ) {
			foreach ( $data['matched_rules'] as $row ) {
				if ( is_array( $row ) ) {
					$matched[] = ResolvedProductDeliveryRule::fromArray( $row );
				}
			}
		}

		$chosen = [];

		if ( isset( $data['chosen_rules'] ) && is_array( $data['chosen_rules'] ) ) {
			foreach ( $data['chosen_rules'] as $availability => $row ) {
				if ( is_array( $row ) ) {
					$chosen[ (string) $availability ] = ResolvedProductDeliveryRule::fromArray( $row );
				}
			}
		}

		$skipped = [];

		if ( isset( $data['skipped_rules'] ) && is_array( $data['skipped_rules'] ) ) {
			foreach ( $data['skipped_rules'] as $row ) {
				if ( is_array( $row ) ) {
					$skipped[] = [
						'rule_id' => (int) ( $row['rule_id'] ?? 0 ),
						'reason'  => (string) ( $row['reason'] ?? '' ),
						'code'    => isset( $row['code'] ) ? (string) $row['code'] : '',
					];
				}
			}
		}

		$warnings = [];

		if ( isset( $data['warnings'] ) && is_array( $data['warnings'] ) ) {
			foreach ( $data['warnings'] as $warning ) {
				$warnings[] = (string) $warning;
			}
		}

		$chosen_explanations = [];

		if ( isset( $data['chosen_explanations'] ) && is_array( $data['chosen_explanations'] ) ) {
			foreach ( $data['chosen_explanations'] as $availability => $explanation ) {
				$chosen_explanations[ (string) $availability ] = (string) $explanation;
			}
		}

		return new self(
			! empty( $data['success'] ),
			isset( $data['error'] ) ? (string) $data['error'] : null,
			(string) ( $data['input_target_type'] ?? '' ),
			(int) ( $data['input_target_id'] ?? 0 ),
			isset( $data['input_target_label'] ) ? (string) $data['input_target_label'] : null,
			is_array( $data['candidate_hierarchy'] ?? null ) ? $data['candidate_hierarchy'] : [],
			(string) ( $data['hierarchy_explanation'] ?? '' ),
			$matched,
			$chosen,
			$chosen_explanations,
			$skipped,
			$warnings,
			isset( $data['no_match_message'] ) ? (string) $data['no_match_message'] : null,
			(string) ( $data['contract_version'] ?? self::CONTRACT_VERSION )
		);
	}
}

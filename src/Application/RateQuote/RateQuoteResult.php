<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\RateQuote;

use CetechDeliveryEngine\Domain\ValueObject\Money;

/**
 * Rate quote outcome for admin/server use only.
 */
final class RateQuoteResult {

	public function __construct(
		public readonly bool $success,
		public readonly ?Money $amount,
		public readonly ?RateQuoteLine $line,
		public readonly ?int $matched_rate_card_id,
		public readonly ?string $matched_rate_card_code,
		public readonly ?string $charge_type,
		public readonly string $message,
		public readonly ?string $error_code
	) {
	}

	public static function success(
		Money $amount,
		RateQuoteLine $line,
		int $matched_rate_card_id,
		string $matched_rate_card_code,
		string $charge_type,
		string $message
	): self {
		return new self(
			true,
			$amount,
			$line,
			$matched_rate_card_id,
			$matched_rate_card_code,
			$charge_type,
			$message,
			null
		);
	}

	public static function failure( string $error_code, string $message ): self {
		return new self(
			false,
			null,
			null,
			null,
			null,
			null,
			$message,
			$error_code
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'success'                => $this->success,
			'amount'                 => $this->amount?->amount(),
			'currency'               => $this->amount?->currency()->value(),
			'line'                   => $this->line?->toArray(),
			'matched_rate_card_id'   => $this->matched_rate_card_id,
			'matched_rate_card_code' => $this->matched_rate_card_code,
			'charge_type'            => $this->charge_type,
			'message'                => $this->message,
			'error_code'             => $this->error_code,
		];
	}
}

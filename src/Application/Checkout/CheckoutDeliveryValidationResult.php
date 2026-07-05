<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Application\Checkout;

/**
 * Outcome of checkout preflight delivery selection validation (no private customer data).
 */
final class CheckoutDeliveryValidationResult {

	/**
	 * @param list<string>        $messages
	 * @param list<string>        $affected_cart_item_keys
	 * @param array<string, int>  $status_counts
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly array $messages,
		public readonly array $affected_cart_item_keys,
		public readonly array $status_counts
	) {
	}

	/**
	 * @param list<string> $messages
	 * @param list<string> $affected_cart_item_keys
	 * @param array<string, int> $status_counts
	 */
	public static function valid( array $messages = [], array $affected_cart_item_keys = [], array $status_counts = [] ): self {
		return new self( true, $messages, $affected_cart_item_keys, $status_counts );
	}

	/**
	 * @param list<string> $messages
	 * @param list<string> $affected_cart_item_keys
	 * @param array<string, int> $status_counts
	 */
	public static function invalid( array $messages, array $affected_cart_item_keys = [], array $status_counts = [] ): self {
		return new self( false, $messages, $affected_cart_item_keys, $status_counts );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'valid'                    => $this->valid,
			'messages'                 => $this->messages,
			'affected_cart_item_keys'  => $this->affected_cart_item_keys,
			'status_counts'            => $this->status_counts,
		];
	}
}

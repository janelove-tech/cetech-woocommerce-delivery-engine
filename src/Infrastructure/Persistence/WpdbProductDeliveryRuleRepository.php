<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Infrastructure\Persistence;

use CetechDeliveryEngine\Domain\ProductRule\ProductDeliveryRuleRepositoryInterface;

/**
 * Product delivery rule persistence for admin configuration only.
 *
 * Not consumed by cart, checkout, product pages, or customer-facing selectors
 * until later phases explicitly wire runtime behaviour.
 */
final class WpdbProductDeliveryRuleRepository extends AbstractWpdbRepository implements ProductDeliveryRuleRepositoryInterface {

	protected function table_suffix(): string {
		return 'product_delivery_rules';
	}

	public function findById( int $id ): ?array {
		return $this->fetch_row_by_id( $id );
	}

	public function findByTarget( string $target_type, int $target_id ): array {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "SELECT * FROM `{$table}` WHERE target_type = %s AND target_id = %d ORDER BY priority ASC, id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $target_type, $target_id ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function findByTargetAndAvailability( string $target_type, int $target_id, string $availability ): array {
		global $wpdb;

		$table = $this->table_name();
		$sql   = "SELECT * FROM `{$table}` WHERE target_type = %s AND target_id = %d AND fulfilment_availability = %s ORDER BY priority ASC, id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $target_type, $target_id, $availability ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function list( array $filters = [] ): array {
		global $wpdb;

		$table = $this->table_name();
		$where = '1=1';
		$args  = [];

		if ( isset( $filters['status'] ) ) {
			$where .= ' AND status = %s';
			$args[] = (string) $filters['status'];
		}

		if ( isset( $filters['target_type'] ) ) {
			$where .= ' AND target_type = %s';
			$args[] = (string) $filters['target_type'];
		}

		$limit = max( 1, min( 500, (int) ( $filters['limit'] ?? 500 ) ) );

		$sql    = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id ASC LIMIT %d";
		$args[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function save( array $data ): int {
		$now = gmdate( 'Y-m-d H:i:s' );
		$id  = isset( $data['id'] ) ? (int) $data['id'] : 0;

		$row = [
			'target_type'              => (string) ( $data['target_type'] ?? '' ),
			'target_id'                => (int) ( $data['target_id'] ?? 0 ),
			'target_label_snapshot'    => $this->nullable_string( $data['target_label_snapshot'] ?? null, 255 ),
			'fulfilment_availability'  => (string) ( $data['fulfilment_availability'] ?? '' ),
			'fulfilment_choice'        => (string) ( $data['fulfilment_choice'] ?? '' ),
			'delivery_offer_ids'       => $this->encode_offer_ids( $data['delivery_offer_ids'] ?? null ),
			'logistics_profile_id'     => $this->nullable_positive_int( $data['logistics_profile_id'] ?? null ),
			'supplier_id'              => $this->nullable_positive_int( $data['supplier_id'] ?? null ),
			'origin_id'                => $this->nullable_positive_int( $data['origin_id'] ?? null ),
			'priority'                 => (int) ( $data['priority'] ?? 100 ),
			'status'                   => (string) ( $data['status'] ?? 'active' ),
			'internal_notes'           => $this->nullable_string( $data['internal_notes'] ?? null ),
			'updated_at'               => $now,
		];

		$formats = [ '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ];

		if ( $id > 0 ) {
			if ( ! $this->update_row( $id, $row, $formats ) ) {
				return 0;
			}

			return $id;
		}

		$row['created_at'] = $now;
		$insert_formats    = array_merge( $formats, [ '%s' ] );

		$insert_id = $this->insert_row( $row, $insert_formats );

		return $insert_id > 0 ? $insert_id : 0;
	}

	public function deactivate( int $id ): bool {
		return $this->mark_inactive( $id );
	}

	public function count_all(): int {
		return parent::count_all();
	}

	/**
	 * @return list<int>
	 */
	public function decode_offer_ids( mixed $stored ): array {
		if ( null === $stored || '' === $stored ) {
			return [];
		}

		$decoded = json_decode( (string) $stored, true );

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$ids = [];

		foreach ( $decoded as $value ) {
			$int = (int) $value;

			if ( $int > 0 ) {
				$ids[] = $int;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param list<int>|null $offer_ids
	 */
	private function encode_offer_ids( mixed $offer_ids ): ?string {
		if ( null === $offer_ids || [] === $offer_ids ) {
			return null;
		}

		if ( ! is_array( $offer_ids ) ) {
			return null;
		}

		$normalized = [];

		foreach ( $offer_ids as $value ) {
			$int = (int) $value;

			if ( $int > 0 ) {
				$normalized[] = $int;
			}
		}

		if ( [] === $normalized ) {
			return null;
		}

		$encoded = wp_json_encode( array_values( array_unique( $normalized ) ) );

		return false !== $encoded ? $encoded : null;
	}

	private function nullable_positive_int( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$int = (int) $value;

		return $int > 0 ? $int : null;
	}

	private function nullable_string( mixed $value, ?int $max_length = null ): ?string {
		if ( null === $value ) {
			return null;
		}

		$string = trim( (string) $value );

		if ( '' === $string ) {
			return null;
		}

		if ( null !== $max_length && strlen( $string ) > $max_length ) {
			return substr( $string, 0, $max_length );
		}

		return $string;
	}
}

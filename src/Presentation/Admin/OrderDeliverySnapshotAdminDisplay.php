<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Application\Order\OrderDeliveryLineReadResult;
use CetechDeliveryEngine\Application\Order\OrderDeliveryLineSnapshot;
use CetechDeliveryEngine\Application\Order\OrderDeliveryPackageReadResult;
use CetechDeliveryEngine\Application\Order\OrderDeliveryPackageSnapshot;
use CetechDeliveryEngine\Application\Order\OrderDeliverySnapshot;
use CetechDeliveryEngine\Application\Order\OrderDeliverySnapshotIntegrity;
use CetechDeliveryEngine\Application\Order\OrderDeliverySnapshotReader;
use CetechDeliveryEngine\Domain\Enum\FulfilmentAvailability;
use WC_Order;
use WC_Order_Item_Product;
use WP_Post;

/**
 * Read-only WooCommerce order admin display for protected delivery snapshots.
 */
final class OrderDeliverySnapshotAdminDisplay {

	private const META_BOX_ID = 'cetech_de_order_delivery_snapshot';

	public function __construct(
		private OrderDeliverySnapshotReader $reader,
		private OrderDeliverySnapshotIntegrity $integrity
	) {
	}

	public function register(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 30, 2 );
	}

	/**
	 * @param string  $post_type
	 * @param WP_Post $post
	 */
	public function register_meta_box( string $post_type, $post ): void {
		unset( $post_type, $post );

		$screen = function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			self::META_BOX_ID,
			__( 'Delivery Engine — Order Snapshots', 'cetech-woocommerce-delivery-engine' ),
			[ $this, 'render_meta_box' ],
			$screen,
			'normal',
			'default'
		);
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order
	 */
	public function render_meta_box( $post_or_order ): void {
		$order = $this->resolve_order( $post_or_order );

		if ( null === $order || ! $this->user_can_view_order( $order ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to view delivery snapshots for this order.', 'cetech-woocommerce-delivery-engine' ) . '</p>';

			return;
		}

		$line_reads    = $this->collect_line_reads( $order );
		$package_read  = $this->reader->read_package( $order );
		$lines_status  = $this->integrity->classify_order_lines( $line_reads );
		$package_status = $this->integrity->classify_package( $package_read );

		echo '<p class="description">';
		echo esc_html__( 'Read-only view of protected delivery snapshots captured at checkout. No data is modified.', 'cetech-woocommerce-delivery-engine' );
		echo '</p>';

		$this->render_summary_table( $lines_status, $package_status, $package_read );

		if ( OrderDeliverySnapshotIntegrity::STATUS_MISSING === $lines_status && OrderDeliverySnapshotIntegrity::STATUS_MISSING === $package_status ) {
			echo '<p>' . esc_html__( 'No delivery snapshot meta found on this order.', 'cetech-woocommerce-delivery-engine' ) . '</p>';

			return;
		}

		$this->render_line_items( $order, $line_reads );

		if ( OrderDeliveryPackageReadResult::ERROR_MISSING !== $package_read->error ) {
			$this->render_package_snapshot( $package_read, $package_status );
		}
	}

	/**
	 * @param list<OrderDeliveryLineReadResult> $line_reads
	 */
	private function render_summary_table(
		string $lines_status,
		string $package_status,
		OrderDeliveryPackageReadResult $package_read
	): void {
		echo '<table class="widefat striped" style="margin-top:12px;margin-bottom:12px;">';
		echo '<tbody>';
		$this->render_row( __( 'Line snapshots status', 'cetech-woocommerce-delivery-engine' ), $this->format_status_label( $lines_status ) );
		$this->render_row( __( 'Package snapshot status', 'cetech-woocommerce-delivery-engine' ), $this->format_status_label( $package_status ) );
		$this->render_row(
			__( 'Snapshot version', 'cetech-woocommerce-delivery-engine' ),
			esc_html( OrderDeliverySnapshot::VERSION )
		);

		if ( null !== $package_read->stored_version ) {
			$this->render_row(
				__( 'Stored package version', 'cetech-woocommerce-delivery-engine' ),
				esc_html( $package_read->stored_version )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * @param WC_Order                          $order
	 * @param list<OrderDeliveryLineReadResult> $line_reads
	 */
	private function render_line_items( WC_Order $order, array $line_reads ): void {
		echo '<h4>' . esc_html__( 'Line item snapshots', 'cetech-woocommerce-delivery-engine' ) . '</h4>';

		$index = 0;

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$read   = $line_reads[ $index ] ?? $this->reader->read_line( $item );
			$status = $this->integrity->classify_line( $read );
			++$index;

			echo '<div style="margin-bottom:16px;padding:12px;border:1px solid #ccd0d4;background:#fff;">';
			echo '<p><strong>' . esc_html( $item->get_name() ) . '</strong>';
			echo ' <span style="color:#646970;">(' . esc_html( sprintf(
				/* translators: %d: WooCommerce order item ID */
				__( 'Item #%d', 'cetech-woocommerce-delivery-engine' ),
				(int) $item_id
			) ) . ')</span></p>';

			echo '<table class="widefat striped"><tbody>';
			$this->render_row( __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->format_status_label( $status ) );
			echo '</tbody></table>';

			if ( null === $read->snapshot ) {
				echo '<p><em>' . esc_html__( 'Snapshot could not be parsed safely.', 'cetech-woocommerce-delivery-engine' ) . '</em></p>';
				echo '</div>';

				continue;
			}

			$this->render_line_snapshot_details( $read->snapshot, $read );
			echo '</div>';
		}
	}

	private function render_line_snapshot_details( OrderDeliveryLineSnapshot $snapshot, OrderDeliveryLineReadResult $read ): void {
		echo '<table class="widefat striped"><tbody>';
		$this->render_row( __( 'Fulfilment availability', 'cetech-woocommerce-delivery-engine' ), esc_html( $this->format_fulfilment_availability( $snapshot->fulfilment_availability ) ) );
		$this->render_row( __( 'Fulfilment choice', 'cetech-woocommerce-delivery-engine' ), esc_html( $this->format_fulfilment_choice( $snapshot->fulfilment_choice ) ) );
		$this->render_row( __( 'Delivery offer', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->delivery_offer_public_label ?? '—' ) );

		if ( null !== $snapshot->delivery_offer_public_description && '' !== $snapshot->delivery_offer_public_description ) {
			$this->render_row( __( 'Offer description', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->delivery_offer_public_description ) );
		}

		if ( null !== $snapshot->estimate_text && '' !== $snapshot->estimate_text ) {
			$this->render_row( __( 'Estimate', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->estimate_text ) );
		}

		$this->render_row( __( 'Quantity', 'cetech-woocommerce-delivery-engine' ), esc_html( (string) $snapshot->quantity ) );
		$this->render_row( __( 'Quote status', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->quote_status ) );
		$this->render_row(
			__( 'Quoted amount', 'cetech-woocommerce-delivery-engine' ),
			esc_html( $this->format_amount( $snapshot->quoted_amount, $snapshot->currency_code ) )
		);
		$this->render_row( __( 'Snapshotted at', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->snapshotted_at ) );

		if ( null !== $read->stored_version ) {
			$this->render_row( __( 'Stored line version', 'cetech-woocommerce-delivery-engine' ), esc_html( $read->stored_version ) );
		}

		echo '</tbody></table>';

		$this->render_internal_ids(
			[
				__( 'Product ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->product_id ? (string) $snapshot->product_id : null,
				__( 'Variation ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->variation_id ? (string) $snapshot->variation_id : null,
				__( 'Delivery offer ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->delivery_offer_id ? (string) $snapshot->delivery_offer_id : null,
				__( 'Rule ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->rule_id ? (string) $snapshot->rule_id : null,
				__( 'Destination zone ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->destination_zone_id ? (string) $snapshot->destination_zone_id : null,
				__( 'Rate card ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->rate_card_id ? (string) $snapshot->rate_card_id : null,
				__( 'Rate card code', 'cetech-woocommerce-delivery-engine' ) => $snapshot->rate_card_code,
			]
		);
	}

	private function render_package_snapshot( OrderDeliveryPackageReadResult $read, string $status ): void {
		echo '<h4>' . esc_html__( 'Package / shipping snapshot', 'cetech-woocommerce-delivery-engine' ) . '</h4>';

		if ( null === $read->snapshot ) {
			echo '<p><em>' . esc_html__( 'Package snapshot could not be parsed safely.', 'cetech-woocommerce-delivery-engine' ) . '</em></p>';

			return;
		}

		$snapshot = $read->snapshot;

		echo '<table class="widefat striped"><tbody>';
		$this->render_row( __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->format_status_label( $status ) );
		$this->render_row( __( 'Shipping method', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->shipping_method_label ?? '—' ) );
		$this->render_row(
			__( 'Package delivery amount', 'cetech-woocommerce-delivery-engine' ),
			esc_html( $this->format_amount( $snapshot->package_total_delivery_amount, $snapshot->currency_code ) )
		);
		$this->render_row( __( 'Quote status', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->quote_status ) );
		$this->render_row( __( 'Snapshotted at', 'cetech-woocommerce-delivery-engine' ), esc_html( $snapshot->snapshotted_at ) );
		echo '</tbody></table>';

		$this->render_internal_ids(
			[
				__( 'Shipping method ID', 'cetech-woocommerce-delivery-engine' ) => $snapshot->shipping_method_id,
				__( 'Destination zone ID', 'cetech-woocommerce-delivery-engine' ) => null !== $snapshot->destination_zone_id ? (string) $snapshot->destination_zone_id : null,
			]
		);
	}

	/**
	 * @param array<string, string|null> $ids
	 */
	private function render_internal_ids( array $ids ): void {
		$rows = array_filter(
			$ids,
			static fn ( ?string $value ): bool => null !== $value && '' !== trim( $value )
		);

		if ( [] === $rows ) {
			return;
		}

		echo '<details style="margin-top:8px;"><summary><em>';
		echo esc_html__( 'Internal IDs (admin only)', 'cetech-woocommerce-delivery-engine' );
		echo '</em></summary>';
		echo '<table class="widefat striped" style="margin-top:8px;"><tbody>';

		foreach ( $rows as $label => $value ) {
			$this->render_row( $label, esc_html( $value ) );
		}

		echo '</tbody></table></details>';
	}

	private function render_row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:220px;">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>';
	}

	/**
	 * @return list<OrderDeliveryLineReadResult>
	 */
	private function collect_line_reads( WC_Order $order ): array {
		$reads = [];

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$reads[] = $this->reader->read_line( $item );
		}

		return $reads;
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order
	 */
	private function resolve_order( $post_or_order ): ?WC_Order {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		if ( ! $post_or_order instanceof WP_Post ) {
			return null;
		}

		$order = wc_get_order( $post_or_order->ID );

		return $order instanceof WC_Order ? $order : null;
	}

	private function user_can_view_order( WC_Order $order ): bool {
		$order_id = $order->get_id();

		if ( $order_id <= 0 ) {
			return false;
		}

		return current_user_can( 'edit_shop_order', $order_id )
			|| current_user_can( 'read_shop_order', $order_id )
			|| current_user_can( 'manage_woocommerce' );
	}

	private function format_status_label( string $status ): string {
		$labels = [
			OrderDeliverySnapshotIntegrity::STATUS_PRESENT_VALID => __( 'Present and valid', 'cetech-woocommerce-delivery-engine' ),
			OrderDeliverySnapshotIntegrity::STATUS_MISSING       => __( 'Missing', 'cetech-woocommerce-delivery-engine' ),
			OrderDeliverySnapshotIntegrity::STATUS_MALFORMED       => __( 'Malformed', 'cetech-woocommerce-delivery-engine' ),
			OrderDeliverySnapshotIntegrity::STATUS_VERSION_MISMATCH => __( 'Version mismatch', 'cetech-woocommerce-delivery-engine' ),
			OrderDeliverySnapshotIntegrity::STATUS_PARTIAL         => __( 'Partial', 'cetech-woocommerce-delivery-engine' ),
			OrderDeliverySnapshotIntegrity::STATUS_QUOTE_MISSING   => __( 'Quote missing', 'cetech-woocommerce-delivery-engine' ),
			OrderDeliverySnapshotIntegrity::STATUS_SELECTION_ONLY  => __( 'Selection only', 'cetech-woocommerce-delivery-engine' ),
		];

		return esc_html( $labels[ $status ] ?? $status );
	}

	private function format_fulfilment_availability( string $value ): string {
		foreach ( FulfilmentAvailability::cases() as $case ) {
			if ( $case->value === $value ) {
				return ucwords( str_replace( '_', ' ', $value ) );
			}
		}

		return $value;
	}

	private function format_fulfilment_choice( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	private function format_amount( ?string $amount, string $currency_code ): string {
		if ( null === $amount || '' === trim( $amount ) ) {
			return '—';
		}

		if ( function_exists( 'wc_price' ) ) {
			$formatted = wc_price( $amount, [ 'currency' => $currency_code ] );

			return wp_strip_all_tags( (string) $formatted );
		}

		return trim( $amount . ' ' . $currency_code );
	}
}

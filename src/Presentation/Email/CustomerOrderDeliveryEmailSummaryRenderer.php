<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Email;

use CetechDeliveryEngine\Application\Order\CustomerOrderDeliveryLineSummary;
use CetechDeliveryEngine\Application\Order\CustomerOrderDeliveryPackageSummary;
use CetechDeliveryEngine\Application\Order\CustomerOrderDeliverySummary;
use CetechDeliveryEngine\Application\Order\CustomerOrderDeliverySummaryBuilder;
use CetechDeliveryEngine\Bootstrap\FeatureFlags;
use CetechDeliveryEngine\Core\Requirements;
use WC_Email;
use WC_Order;

/**
 * Customer-safe read-only delivery summary in WooCommerce customer order emails.
 */
final class CustomerOrderDeliveryEmailSummaryRenderer {

	public const EMAIL_SUMMARY_FLAG = 'enable_customer_email_delivery_summary';

	public function __construct(
		private FeatureFlags $feature_flags,
		private Requirements $requirements,
		private CustomerOrderDeliverySummaryBuilder $summary_builder
	) {
	}

	public function register(): void {
		if ( ! $this->feature_flags->is_enabled( self::EMAIL_SUMMARY_FLAG ) ) {
			return;
		}

		if ( ! $this->requirements->is_woocommerce_active() ) {
			return;
		}

		add_action( 'woocommerce_email_after_order_table', [ $this, 'render' ], 15, 4 );
	}

	/**
	 * @param WC_Order              $order
	 * @param bool                  $sent_to_admin
	 * @param bool                  $plain_text
	 * @param WC_Email|false|null   $email
	 */
	public function render( WC_Order $order, bool $sent_to_admin, bool $plain_text, $email = false ): void {
		if ( ! $this->feature_flags->is_enabled( self::EMAIL_SUMMARY_FLAG ) ) {
			return;
		}

		if ( $sent_to_admin || ! $this->is_customer_email_context( $email ) ) {
			return;
		}

		if ( ! $order instanceof WC_Order || $order->get_id() <= 0 ) {
			return;
		}

		$summary = $this->summary_builder->build( $order );

		if ( null === $summary ) {
			return;
		}

		if ( $plain_text ) {
			echo $this->render_plain_text_summary( $summary );

			return;
		}

		echo $this->render_html_summary( $summary );
	}

	/**
	 * @param WC_Email|false|null $email
	 */
	private function is_customer_email_context( $email ): bool {
		if ( $email instanceof WC_Email && method_exists( $email, 'is_customer_email' ) ) {
			return (bool) $email->is_customer_email();
		}

		return true;
	}

	private function render_html_summary( CustomerOrderDeliverySummary $summary ): string {
		$output = '<div style="margin-bottom:40px;">';
		$output .= '<h2>' . esc_html__( 'Delivery details', 'cetech-woocommerce-delivery-engine' ) . '</h2>';

		if ( [] !== $summary->lines ) {
			$output .= '<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;" border="1">';
			$output .= '<thead><tr>';
			$output .= '<th scope="col" style="text-align:left;">' . esc_html__( 'Product', 'cetech-woocommerce-delivery-engine' ) . '</th>';
			$output .= '<th scope="col" style="text-align:left;">' . esc_html__( 'Delivery option', 'cetech-woocommerce-delivery-engine' ) . '</th>';
			$output .= '</tr></thead><tbody>';

			foreach ( $summary->lines as $line ) {
				$output .= '<tr>';
				$output .= '<td>' . esc_html( $line->product_name );
				$output .= $this->render_html_line_details( $line );
				$output .= '</td>';
				$output .= '<td>' . esc_html( $line->delivery_option_label ) . '</td>';
				$output .= '</tr>';
			}

			$output .= '</tbody></table>';
		}

		if ( null !== $summary->package ) {
			$output .= $this->render_html_package_block( $summary->package );
		}

		$output .= '</div>';

		return $output;
	}

	private function render_html_line_details( CustomerOrderDeliveryLineSummary $line ): string {
		$rows = $this->collect_line_detail_rows( $line );

		if ( [] === $rows ) {
			return '';
		}

		$output = '<ul style="margin:8px 0 0;padding-left:18px;">';

		foreach ( $rows as $row ) {
			$output .= '<li><strong>' . esc_html( $row['label'] ) . '</strong> ' . esc_html( $row['value'] ) . '</li>';
		}

		$output .= '</ul>';

		return $output;
	}

	private function render_html_package_block( CustomerOrderDeliveryPackageSummary $package ): string {
		$output = '<h3 style="margin-top:24px;">' . esc_html__( 'Shipping summary', 'cetech-woocommerce-delivery-engine' ) . '</h3>';
		$output .= '<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;" border="1"><tbody>';
		$output .= '<tr><th scope="row" style="text-align:left;">' . esc_html__( 'Shipping method', 'cetech-woocommerce-delivery-engine' ) . '</th>';
		$output .= '<td>' . esc_html( $package->shipping_method_label ) . '</td></tr>';

		if ( null !== $package->package_amount_display && '' !== trim( $package->package_amount_display ) ) {
			$output .= '<tr><th scope="row" style="text-align:left;">' . esc_html__( 'Delivery total', 'cetech-woocommerce-delivery-engine' ) . '</th>';
			$output .= '<td>' . esc_html( $package->package_amount_display ) . '</td></tr>';
		}

		$output .= '</tbody></table>';

		return $output;
	}

	private function render_plain_text_summary( CustomerOrderDeliverySummary $summary ): string {
		$lines   = [];
		$lines[] = __( 'Delivery details', 'cetech-woocommerce-delivery-engine' );
		$lines[] = str_repeat( '-', 40 );

		foreach ( $summary->lines as $line ) {
			$lines[] = $line->product_name;
			$lines[] = __( 'Delivery option:', 'cetech-woocommerce-delivery-engine' ) . ' ' . $line->delivery_option_label;

			foreach ( $this->collect_line_detail_rows( $line ) as $row ) {
				$lines[] = $row['label'] . ' ' . $row['value'];
			}

			$lines[] = '';
		}

		if ( null !== $summary->package ) {
			$lines[] = __( 'Shipping summary', 'cetech-woocommerce-delivery-engine' );
			$lines[] = __( 'Shipping method:', 'cetech-woocommerce-delivery-engine' ) . ' ' . $summary->package->shipping_method_label;

			if ( null !== $summary->package->package_amount_display && '' !== trim( $summary->package->package_amount_display ) ) {
				$lines[] = __( 'Delivery total:', 'cetech-woocommerce-delivery-engine' ) . ' ' . $summary->package->package_amount_display;
			}
		}

		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * @return list<array{label: string, value: string}>
	 */
	private function collect_line_detail_rows( CustomerOrderDeliveryLineSummary $line ): array {
		$rows = [];

		if ( '' !== $line->fulfilment_availability_label ) {
			$rows[] = [
				'label' => __( 'Availability:', 'cetech-woocommerce-delivery-engine' ),
				'value' => $line->fulfilment_availability_label,
			];
		}

		if ( '' !== $line->fulfilment_choice_label ) {
			$rows[] = [
				'label' => __( 'Choice:', 'cetech-woocommerce-delivery-engine' ),
				'value' => $line->fulfilment_choice_label,
			];
		}

		if ( null !== $line->delivery_option_description && '' !== trim( $line->delivery_option_description ) ) {
			$rows[] = [
				'label' => __( 'Details:', 'cetech-woocommerce-delivery-engine' ),
				'value' => $line->delivery_option_description,
			];
		}

		if ( null !== $line->estimate_text && '' !== trim( $line->estimate_text ) ) {
			$rows[] = [
				'label' => __( 'Estimate:', 'cetech-woocommerce-delivery-engine' ),
				'value' => $line->estimate_text,
			];
		}

		if ( null !== $line->quote_status_label && '' !== trim( $line->quote_status_label ) ) {
			$rows[] = [
				'label' => __( 'Status:', 'cetech-woocommerce-delivery-engine' ),
				'value' => $line->quote_status_label,
			];
		}

		if ( null !== $line->quoted_amount_display && '' !== trim( $line->quoted_amount_display ) ) {
			$rows[] = [
				'label' => __( 'Delivery amount:', 'cetech-woocommerce-delivery-engine' ),
				'value' => $line->quoted_amount_display,
			];
		}

		return $rows;
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Application\Diagnostics\DiagnosticSeverity;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;

/**
 * Small admin UI helpers (HTML fragments for wp-admin only).
 */
final class AdminUiHelper {

	public static function record_status_badge( string $status ): string {
		$normalized = strtolower( trim( $status ) );
		$class      = RecordStatus::Active->value === $normalized
			? 'cetech-de-badge--ready'
			: 'cetech-de-badge--not_active';
		$label      = self::record_status_label( $normalized );

		return sprintf(
			'<span class="cetech-de-badge %1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	public static function record_status_label( string $status ): string {
		$normalized = strtolower( trim( $status ) );

		return match ( $normalized ) {
			RecordStatus::Active->value => __( 'Active', 'cetech-woocommerce-delivery-engine' ),
			RecordStatus::Inactive->value => __( 'Inactive', 'cetech-woocommerce-delivery-engine' ),
			default => ucfirst( $normalized ),
		};
	}

	public static function rate_card_coverage_badge( int $count ): string {
		if ( $count > 0 ) {
			return sprintf(
				'<span class="cetech-de-badge cetech-de-badge--ready">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: number of rate cards */
						_n( '%d rate card', '%d rate cards', $count, 'cetech-woocommerce-delivery-engine' ),
						$count
					)
				)
			);
		}

		return sprintf(
			'<span class="cetech-de-badge cetech-de-badge--attention">%s</span>',
			esc_html__( 'No pricing set', 'cetech-woocommerce-delivery-engine' )
		);
	}

	public static function format_money( string $amount, string $currency ): string {
		$amount   = trim( $amount );
		$currency = strtoupper( trim( $currency ) );

		if ( '' === $amount ) {
			return '—';
		}

		if ( '' === $currency ) {
			return $amount;
		}

		return sprintf( '%s %s', $currency, $amount );
	}

	public static function diagnostic_severity_badge( DiagnosticSeverity $severity ): string {
		$class = match ( $severity ) {
			DiagnosticSeverity::Error => 'cetech-de-severity-error',
			DiagnosticSeverity::Warning => 'cetech-de-severity-warning',
			DiagnosticSeverity::Info => 'cetech-de-severity-info',
			DiagnosticSeverity::Ok => 'cetech-de-severity-ok',
		};

		return sprintf(
			'<span class="cetech-de-severity %1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( ucfirst( $severity->value ) )
		);
	}
}

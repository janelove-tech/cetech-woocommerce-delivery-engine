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
			? 'cetech-de-status-active'
			: 'cetech-de-status-inactive';

		return sprintf(
			'<span class="cetech-de-status %1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( $normalized )
		);
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

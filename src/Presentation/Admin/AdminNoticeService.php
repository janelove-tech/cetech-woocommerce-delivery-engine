<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Flash admin notices across redirect-after-POST.
 */
final class AdminNoticeService {

	private const TRANSIENT_PREFIX = 'cetech_de_admin_notice_';

	private const DRAFT_PREFIX = 'cetech_de_admin_draft_';

	public function flash_success( string $message ): void {
		$this->flash( 'success', $message );
	}

	public function flash_error( string $message ): void {
		$this->flash( 'error', $message );
	}

	public function flash_warning( string $message ): void {
		$this->flash( 'warning', $message );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function stash_form_draft( string $page_slug, array $input ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		set_transient(
			self::DRAFT_PREFIX . get_current_user_id() . '_' . sanitize_key( $page_slug ),
			$input,
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function consume_form_draft( string $page_slug ): ?array {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$key    = self::DRAFT_PREFIX . get_current_user_id() . '_' . sanitize_key( $page_slug );
		$stored = get_transient( $key );

		delete_transient( $key );

		return is_array( $stored ) ? $stored : null;
	}

	public function render_notices(): void {
		if ( ! is_admin() || ! is_user_logged_in() ) {
			return;
		}

		$key    = self::TRANSIENT_PREFIX . get_current_user_id();
		$stored = get_transient( $key );

		if ( ! is_array( $stored ) ) {
			return;
		}

		delete_transient( $key );

		$type    = sanitize_key( (string) ( $stored['type'] ?? 'info' ) );
		$message = (string) ( $stored['message'] ?? '' );

		if ( '' === $message ) {
			return;
		}

		$class = 'notice-info';

		if ( 'success' === $type ) {
			$class = 'notice-success';
		} elseif ( 'error' === $type ) {
			$class = 'notice-error';
		} elseif ( 'warning' === $type ) {
			$class = 'notice-warning';
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	private function flash( string $type, string $message ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		set_transient(
			self::TRANSIENT_PREFIX . get_current_user_id(),
			[
				'type'    => $type,
				'message' => $message,
			],
			MINUTE_IN_SECONDS
		);
	}
}

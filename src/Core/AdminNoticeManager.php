<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Core;

use CetechDeliveryEngine\Support\AdminNotice;

/**
 * Registers and renders admin notices for the plugin.
 */
final class AdminNoticeManager {

	/** @var array<string, AdminNotice> */
	private array $notices = [];

	private bool $hooked = false;

	public function register( AdminNotice $notice ): void {
		$this->notices[ $notice->id() ] = $notice;
	}

	public function boot(): void {
		if ( $this->hooked ) {
			return;
		}

		$this->hooked = true;

		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'wp_ajax_cetech_de_dismiss_notice', [ $this, 'dismiss_notice' ] );
	}

	public function render(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $this->notices as $notice ) {
			if ( $notice->is_dismissible() && $this->is_dismissed( $notice->id() ) ) {
				continue;
			}

			$classes = [
				'notice',
				'notice-' . $notice->type(),
			];

			if ( $notice->is_dismissible() ) {
				$classes[] = 'is-dismissible';
				$classes[] = 'cetech-de-notice';
			}

			printf(
				'<div class="%1$s" data-cetech-de-notice-id="%2$s"><p>%3$s</p></div>',
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( $notice->id() ),
				esc_html( $notice->message() )
			);
		}

		$this->enqueue_dismiss_script();
	}

	public function dismiss_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		check_ajax_referer( 'cetech_de_dismiss_notice', 'nonce' );

		$notice_id = isset( $_POST['notice_id'] )
			? sanitize_key( wp_unslash( (string) $_POST['notice_id'] ) )
			: '';

		if ( '' === $notice_id ) {
			wp_send_json_error( null, 400 );
		}

		$dismissed   = get_user_meta( get_current_user_id(), 'cetech_de_dismissed_notices', true );
		$dismissed   = is_array( $dismissed ) ? $dismissed : [];
		$dismissed[] = $notice_id;
		$dismissed   = array_values( array_unique( $dismissed ) );

		update_user_meta( get_current_user_id(), 'cetech_de_dismissed_notices', $dismissed );

		wp_send_json_success();
	}

	private function is_dismissed( string $notice_id ): bool {
		$dismissed = get_user_meta( get_current_user_id(), 'cetech_de_dismissed_notices', true );

		return is_array( $dismissed ) && in_array( $notice_id, $dismissed, true );
	}

	private function enqueue_dismiss_script(): void {
		if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
			return;
		}

		$script = <<<'JS'
jQuery(function ($) {
	$(document).on('click', '.cetech-de-notice .notice-dismiss', function () {
		var $notice = $(this).closest('.cetech-de-notice');
		var noticeId = $notice.data('cetech-de-notice-id');

		if (!noticeId) {
			return;
		}

		$.post(ajaxurl, {
			action: 'cetech_de_dismiss_notice',
			notice_id: noticeId,
			nonce: '%s'
		});
	});
});
JS;

		wp_add_inline_script(
			'jquery',
			sprintf( $script, esc_js( wp_create_nonce( 'cetech_de_dismiss_notice' ) ) )
		);
	}
}

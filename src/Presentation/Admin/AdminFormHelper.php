<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Shared admin form helpers.
 */
final class AdminFormHelper {

	public static function nonce_field( string $action ): void {
		wp_nonce_field( $action, 'cetech_de_nonce' );
	}

	public static function verify_nonce( string $action ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['cetech_de_nonce'] ) ) {
			return false;
		}

		return (bool) wp_verify_nonce(
			sanitize_text_field( wp_unslash( (string) $_POST['cetech_de_nonce'] ) ),
			$action
		);
	}

	public static function text_field(
		string $name,
		string $label,
		string $value = '',
		bool $required = false,
		string $description = ''
	): void {
		echo '<tr><th scope="row">';
		echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label );
		if ( $required ) {
			echo ' <span class="description">' . esc_html__( '(required)', 'cetech-woocommerce-delivery-engine' ) . '</span>';
		}
		echo '</label></th><td>';
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s" %3$s />',
			esc_attr( $name ),
			esc_attr( $value ),
			$required ? 'required' : ''
		);
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function textarea_field(
		string $name,
		string $label,
		string $value = '',
		int $rows = 4,
		string $description = ''
	): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf(
			'<textarea class="large-text" id="%1$s" name="%1$s" rows="%2$d">%3$s</textarea>',
			esc_attr( $name ),
			$rows,
			esc_textarea( $value )
		);
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function number_field(
		string $name,
		string $label,
		?int $value = null,
		int $min = 0,
		string $description = ''
	): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf(
			'<input type="number" class="small-text" id="%1$s" name="%1$s" value="%2$s" min="%3$d" step="1" />',
			esc_attr( $name ),
			null === $value ? '' : esc_attr( (string) $value ),
			$min
		);
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * @param array<string, string> $options
	 */
	public static function select_field(
		string $name,
		string $label,
		array $options,
		string $selected = '',
		string $description = ''
	): void {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
		foreach ( $options as $value => $option_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $option_label )
			);
		}
		echo '</select>';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * @param array<string, string> $options
	 * @param list<string>          $selected
	 */
	public static function checkbox_group_field(
		string $name,
		string $label,
		array $options,
		array $selected = [],
		string $description = ''
	): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		foreach ( $options as $value => $option_label ) {
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( in_array( $value, $selected, true ), true, false ),
				esc_html( $option_label )
			);
		}
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function sanitize_code( string $code ): string {
		$code = strtolower( trim( $code ) );

		return (string) preg_replace( '/[^a-z0-9_-]/', '', $code );
	}

	public static function is_valid_code( string $code ): bool {
		return (bool) preg_match( '/^[a-z0-9_-]+$/', $code );
	}
}

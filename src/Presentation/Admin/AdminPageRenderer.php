<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

/**
 * Simple admin page layout helpers.
 */
final class AdminPageRenderer {

	public static function open_wrap( string $title ): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
	}

	public static function close_wrap(): void {
		echo '</div>';
	}

	/**
	 * @param list<array<string, string>> $rows
	 * @param list<string>                $headers
	 */
	public static function render_table( array $headers, array $rows, bool $styled = false ): void {
		if ( $styled ) {
			echo '<div class="cetech-de-admin-table-wrap">';
		}

		echo '<table class="widefat striped cetech-de-admin-table">';
		echo '<thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th scope="col">' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			printf(
				'<tr><td colspan="%d">%s</td></tr>',
				count( $headers ),
				esc_html__( 'No records found.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				foreach ( $row as $cell ) {
					echo '<td>' . self::table_cell_html( $cell ) . '</td>';
				}
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $styled ) {
			echo '</div>';
		}
	}

	public static function add_new_button( string $page_slug, string $label ): void {
		$url = add_query_arg(
			[
				'page'   => $page_slug,
				'action' => 'add',
			],
			admin_url( 'admin.php' )
		);
		printf(
			'<p><a href="%1$s" class="page-title-action">%2$s</a></p>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	public static function edit_url( string $page_slug, int $id ): string {
		return add_query_arg(
			[
				'page'   => $page_slug,
				'action' => 'edit',
				'id'     => $id,
			],
			admin_url( 'admin.php' )
		);
	}

	public static function list_url( string $page_slug ): string {
		return add_query_arg( 'page', $page_slug, admin_url( 'admin.php' ) );
	}

	/**
	 * Sanitize admin table cell HTML while preserving inline action forms.
	 */
	private static function table_cell_html( string $cell ): string {
		return wp_kses(
			$cell,
			[
				'a'      => [
					'href'       => true,
					'class'      => true,
					'title'      => true,
					'aria-label' => true,
					'target'     => true,
					'rel'        => true,
				],
				'form'   => [
					'method'   => true,
					'action'   => true,
					'style'    => true,
					'class'    => true,
					'onsubmit' => true,
				],
				'input'  => [
					'type'  => true,
					'name'  => true,
					'value' => true,
					'id'    => true,
					'class' => true,
				],
				'button' => [
					'type'    => true,
					'class'   => true,
					'name'    => true,
					'value'   => true,
					'onclick' => true,
				],
				'span'   => [
					'class'       => true,
					'aria-hidden' => true,
				],
				'strong' => [],
				'em'     => [],
				'br'     => [],
			]
		);
	}
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\Enum\DestinationRuleMatchMode;
use CetechDeliveryEngine\Domain\Enum\DestinationRuleType;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\Zone\DestinationRuleRepositoryInterface;
use CetechDeliveryEngine\Domain\Zone\DestinationZoneRepositoryInterface;
use CetechDeliveryEngine\Presentation\Admin\Validation\DestinationRuleValidator;
use CetechDeliveryEngine\Presentation\Admin\Validation\DestinationZoneValidator;

final class DestinationZonesPage {

	public const SLUG = 'cetech-delivery-engine-destination-zones';

	private const ACTION_SAVE = 'cetech_de_save_destination_zone';

	private const ACTION_DEACTIVATE = 'cetech_de_deactivate_destination_zone';

	private const ACTION_TEST = 'cetech_de_test_destination_zone';

	private const RULE_FORM_ROWS = 8;

	public function __construct(
		private DestinationZoneRepositoryInterface $zone_repository,
		private DestinationRuleRepositoryInterface $rule_repository,
		private DestinationZoneValidator $zone_validator,
		private DestinationRuleValidator $rule_validator,
		private DestinationZoneTestMatcher $test_matcher,
		private AdminActionHandler $action_handler,
		private ConfigurationAuditLogger $audit_logger
	) {
	}

	public function handle_actions(): void {
		if ( $this->action_handler->verify_post( self::ACTION_SAVE, self::ACTION_SAVE, 'manage_delivery_zones', self::SLUG ) ) {
			$this->handle_save();
		}

		if ( $this->action_handler->verify_post( self::ACTION_DEACTIVATE, self::ACTION_DEACTIVATE, 'manage_delivery_zones', self::SLUG ) ) {
			$this->handle_deactivate();
		}

		if ( $this->action_handler->verify_post( self::ACTION_TEST, self::ACTION_TEST, 'manage_delivery_zones', self::SLUG ) ) {
			$this->handle_test_match();
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_delivery_zones' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->notices()->render_notices();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : 'list';

		if ( 'add' === $action || 'edit' === $action ) {
			$this->render_form( 'edit' === $action );
			return;
		}

		$this->render_list();
	}

	private function render_list(): void {
		AdminPageRenderer::open_wrap( __( 'Destination Zones', 'cetech-woocommerce-delivery-engine' ) );
		AdminPageRenderer::add_new_button( self::SLUG, __( 'Add New', 'cetech-woocommerce-delivery-engine' ) );

		$zones = $this->zone_repository->list( [ 'limit' => 500 ] );
		$rows  = [];

		foreach ( $zones as $zone ) {
			$zone_id = (int) ( $zone['id'] ?? 0 );
			$rules   = $this->rule_repository->listByZoneId( $zone_id );
			$summary = $this->summarize_rules( $rules );

			$rows[] = [
				(string) $zone_id,
				esc_html( (string) ( $zone['internal_code'] ?? '' ) ),
				esc_html( (string) ( $zone['internal_name'] ?? '' ) ),
				esc_html( $summary['country'] ),
				esc_html( $summary['region'] ),
				esc_html( $summary['city'] ),
				esc_html( (string) ( $zone['priority'] ?? '' ) ),
				esc_html( ! empty( $zone['is_fallback'] ) ? __( 'Yes', 'cetech-woocommerce-delivery-engine' ) : __( 'No', 'cetech-woocommerce-delivery-engine' ) ),
				esc_html( ! empty( $zone['remote_area_flag'] ) ? __( 'Yes', 'cetech-woocommerce-delivery-engine' ) : __( 'No', 'cetech-woocommerce-delivery-engine' ) ),
				esc_html( (string) ( $zone['status'] ?? '' ) ),
				esc_html( (string) ( $zone['updated_at'] ?? '' ) ),
				$this->render_actions( $zone_id ),
			];
		}

		AdminPageRenderer::render_table(
			[
				__( 'ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Code', 'cetech-woocommerce-delivery-engine' ),
				__( 'Name', 'cetech-woocommerce-delivery-engine' ),
				__( 'Country', 'cetech-woocommerce-delivery-engine' ),
				__( 'Region/state', 'cetech-woocommerce-delivery-engine' ),
				__( 'City/metro', 'cetech-woocommerce-delivery-engine' ),
				__( 'Priority', 'cetech-woocommerce-delivery-engine' ),
				__( 'Fallback', 'cetech-woocommerce-delivery-engine' ),
				__( 'Remote area', 'cetech-woocommerce-delivery-engine' ),
				__( 'Status', 'cetech-woocommerce-delivery-engine' ),
				__( 'Updated at', 'cetech-woocommerce-delivery-engine' ),
				__( 'Actions', 'cetech-woocommerce-delivery-engine' ),
			],
			$rows
		);

		$this->render_test_tool();
		AdminPageRenderer::close_wrap();
	}

	private function render_test_tool(): void {
		$draft = $this->action_handler->notices()->consume_form_draft( self::SLUG . '_test' );

		echo '<h2>' . esc_html__( 'Test destination match', 'cetech-woocommerce-delivery-engine' ) . '</h2>';
		echo '<p class="description">' . esc_html__(
			'Read-only admin test against stored zones and rules. Does not change data or calculate prices.',
			'cetech-woocommerce-delivery-engine'
		) . '</p>';

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_TEST );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_TEST ) . '" />';
		echo '<table class="form-table" role="presentation"><tbody>';
		AdminFormHelper::text_field(
			'test_country_code',
			__( 'Country code', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $draft['test_country_code'] ?? '' )
		);
		AdminFormHelper::text_field(
			'test_region',
			__( 'Region', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $draft['test_region'] ?? '' )
		);
		AdminFormHelper::text_field(
			'test_city',
			__( 'City', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $draft['test_city'] ?? '' )
		);
		AdminFormHelper::text_field(
			'test_postcode',
			__( 'Postcode', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $draft['test_postcode'] ?? '' )
		);
		echo '</tbody></table>';
		submit_button( __( 'Run test', 'cetech-woocommerce-delivery-engine' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( is_array( $draft ) && isset( $draft['test_result'] ) ) {
			echo '<p><strong>' . esc_html__( 'Result:', 'cetech-woocommerce-delivery-engine' ) . '</strong> ';
			echo esc_html( (string) $draft['test_result'] );
			echo '</p>';
		}
	}

	private function render_form( bool $is_edit ): void {
		$draft = $this->action_handler->notices()->consume_form_draft( self::SLUG );

		if ( null !== $draft ) {
			$record = $this->form_record_from_draft( $draft );
			$rules  = isset( $draft['destination_rules'] ) && is_array( $draft['destination_rules'] )
				? $draft['destination_rules']
				: [];
		} else {
			$record = $this->load_record_for_form( $is_edit );
			$rules  = $is_edit && ! empty( $record['id'] )
				? $this->rule_repository->listByZoneId( (int) $record['id'] )
				: [];
		}

		$title = $is_edit
			? __( 'Edit Destination Zone', 'cetech-woocommerce-delivery-engine' )
			: __( 'Add Destination Zone', 'cetech-woocommerce-delivery-engine' );

		AdminPageRenderer::open_wrap( $title );

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';

		if ( $is_edit && ! empty( $record['id'] ) ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $record['id'] ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		AdminFormHelper::text_field( 'code', __( 'Code', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['code'] ?? '' ), true );
		AdminFormHelper::text_field( 'name', __( 'Name', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['name'] ?? '' ), true );
		AdminFormHelper::text_field(
			'public_label',
			__( 'Public label', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['public_label'] ?? '' ),
			false,
			__( 'Optional customer-safe label stored in public_label.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::number_field( 'priority', __( 'Priority', 'cetech-woocommerce-delivery-engine' ), isset( $record['priority'] ) ? (int) $record['priority'] : 100 );
		AdminFormHelper::checkbox_field(
			'is_fallback',
			__( 'Fallback zone', 'cetech-woocommerce-delivery-engine' ),
			! empty( $record['is_fallback'] ),
			__( 'Used when no other zone matches during admin testing.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::checkbox_field(
			'is_remote_area',
			__( 'Remote area', 'cetech-woocommerce-delivery-engine' ),
			! empty( $record['is_remote_area'] )
		);
		AdminFormHelper::select_field( 'status', __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->status_options(), (string) ( $record['status'] ?? RecordStatus::Active->value ) );
		echo '</tbody></table>';

		$this->render_rules_section( $rules );

		submit_button( $is_edit ? __( 'Update Zone', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Zone', 'cetech-woocommerce-delivery-engine' ) );
		echo ' <a class="button" href="' . esc_url( AdminPageRenderer::list_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		echo '</form>';
		AdminPageRenderer::close_wrap();
	}

	/**
	 * @param list<array<string, mixed>> $rules
	 */
	private function render_rules_section( array $rules ): void {
		echo '<h2>' . esc_html__( 'Destination Rules', 'cetech-woocommerce-delivery-engine' ) . '</h2>';
		echo '<p class="description">' . esc_html__(
			'Geographic matching is configured through rules. All rules for a zone must match (AND). Empty rows are ignored.',
			'cetech-woocommerce-delivery-engine'
		) . '</p>';

		$form_rows = max( self::RULE_FORM_ROWS, count( $rules ) + 2 );
		$rows      = [];

		for ( $index = 0; $index < $form_rows; $index++ ) {
			$rows[] = $rules[ $index ] ?? [];
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Rule type', 'cetech-woocommerce-delivery-engine' ) . '</th>';
		echo '<th>' . esc_html__( 'Rule value', 'cetech-woocommerce-delivery-engine' ) . '</th>';
		echo '<th>' . esc_html__( 'Match mode', 'cetech-woocommerce-delivery-engine' ) . '</th>';
		echo '<th>' . esc_html__( 'Priority', 'cetech-woocommerce-delivery-engine' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $index => $rule ) {
			echo '<tr>';
			echo '<td><select name="destination_rules[' . esc_attr( (string) $index ) . '][rule_type]">';
			echo '<option value="">' . esc_html__( '— Select —', 'cetech-woocommerce-delivery-engine' ) . '</option>';
			foreach ( $this->rule_type_options() as $value => $label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $value ),
					selected( (string) ( $rule['rule_type'] ?? '' ), $value, false ),
					esc_html( $label )
				);
			}
			echo '</select></td>';
			printf(
				'<td><input type="text" class="regular-text" name="destination_rules[%1$d][rule_value]" value="%2$s" /></td>',
				$index,
				esc_attr( (string) ( $rule['rule_value'] ?? '' ) )
			);
			echo '<td><select name="destination_rules[' . esc_attr( (string) $index ) . '][match_mode]">';
			foreach ( $this->match_mode_options() as $value => $label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $value ),
					selected( (string) ( $rule['match_mode'] ?? DestinationRuleMatchMode::Exact->value ), $value, false ),
					esc_html( $label )
				);
			}
			echo '</select></td>';
			printf(
				'<td><input type="number" class="small-text" name="destination_rules[%1$d][priority]" value="%2$s" min="0" step="1" /></td>',
				$index,
				esc_attr( (string) ( $rule['priority'] ?? 100 ) )
			);
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function handle_save(): void {
		$input      = $this->read_form_input();
		$zone_errors = $this->zone_validator->validate( $input, isset( $input['id'] ) ? (int) $input['id'] : null );
		$rule_result = $this->rule_validator->validate_and_normalize(
			isset( $input['destination_rules'] ) && is_array( $input['destination_rules'] )
				? array_values( $input['destination_rules'] )
				: []
		);

		$errors = array_merge( $zone_errors, $rule_result['errors'] );

		if ( [] !== $errors ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( implode( ' ', $errors ) );
			$this->redirect_to_form( $input );
		}

		$code = AdminFormHelper::sanitize_code( (string) $input['code'] );
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$existing_by_code = $this->zone_repository->findByCode( $code );

		if ( null !== $existing_by_code && (int) ( $existing_by_code['id'] ?? 0 ) !== $id ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( __( 'A destination zone with this code already exists.', 'cetech-woocommerce-delivery-engine' ) );
			$this->redirect_to_form( $input );
		}

		$previous = $id > 0 ? $this->zone_repository->findById( $id ) : null;
		$previous_rules = $id > 0 ? $this->rule_repository->listByZoneId( $id ) : [];

		$payload = [
			'id'               => $id,
			'internal_code'    => $code,
			'internal_name'    => trim( (string) $input['name'] ),
			'public_label'     => trim( (string) ( $input['public_label'] ?? '' ) ),
			'is_fallback'      => ! empty( $input['is_fallback'] ),
			'remote_area_flag' => ! empty( $input['is_remote_area'] ),
			'priority'         => (int) ( $input['priority'] ?? 100 ),
			'status'           => (string) $input['status'],
		];

		$saved_id = $this->zone_repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to save destination zone.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( ! $this->rule_repository->replaceForZone( $saved_id, $rule_result['rules'] ) ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, array_merge( $input, [ 'id' => $saved_id ] ) );
			$this->action_handler->notices()->flash_error( __( 'Destination zone saved, but destination rules could not be updated.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG, [ 'action' => 'edit', 'id' => $saved_id ] );
		}

		$zone_audit = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'destination_zone',
			$saved_id,
			$previous,
			$this->zone_repository->findById( $saved_id )
		);

		$rules_audit = $this->audit_logger->log(
			'replaced',
			'destination_rules',
			$saved_id,
			[ 'rules' => $previous_rules ],
			[ 'rules' => $this->rule_repository->listByZoneId( $saved_id ) ]
		);

		if ( $zone_audit && $rules_audit ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Destination zone updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Destination zone created.', 'cetech-woocommerce-delivery-engine' )
			);
		} elseif ( $zone_audit ) {
			$this->action_handler->notices()->flash_warning( __( 'Destination zone saved, but audit logging failed for rules.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Destination zone saved, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid destination zone.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->zone_repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Destination zone not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->zone_repository->softDelete( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate destination zone.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Destination zone is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'destination_zone',
			$id,
			$previous,
			$this->zone_repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Destination zone deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Destination zone deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_test_match(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input = [
			'test_country_code' => isset( $_POST['test_country_code'] ) ? wp_unslash( (string) $_POST['test_country_code'] ) : '',
			'test_region'       => isset( $_POST['test_region'] ) ? wp_unslash( (string) $_POST['test_region'] ) : '',
			'test_city'         => isset( $_POST['test_city'] ) ? wp_unslash( (string) $_POST['test_city'] ) : '',
			'test_postcode'     => isset( $_POST['test_postcode'] ) ? wp_unslash( (string) $_POST['test_postcode'] ) : '',
		];

		$matched = $this->test_matcher->match(
			$input['test_country_code'],
			$input['test_region'],
			$input['test_city'],
			$input['test_postcode']
		);

		if ( null === $matched ) {
			$input['test_result'] = __( 'No matching zone.', 'cetech-woocommerce-delivery-engine' );
		} else {
			$input['test_result'] = sprintf(
				/* translators: 1: zone code, 2: zone name, 3: zone ID */
				__( 'Matched zone: %1$s (%2$s) [ID %3$d]', 'cetech-woocommerce-delivery-engine' ),
				(string) ( $matched['internal_code'] ?? '' ),
				(string) ( $matched['internal_name'] ?? '' ),
				(int) ( $matched['id'] ?? 0 )
			);
		}

		$this->action_handler->notices()->stash_form_draft( self::SLUG . '_test', $input );
		$this->action_handler->redirect( self::SLUG );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function redirect_to_form( array $input ): never {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$this->action_handler->redirect(
			self::SLUG,
			$id > 0 ? [ 'action' => 'edit', 'id' => $id ] : [ 'action' => 'add' ]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_record_for_form( bool $is_edit ): array {
		if ( ! $is_edit ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( $id <= 0 ) {
			return [];
		}

		$row = $this->zone_repository->findById( $id );

		if ( null === $row ) {
			return [];
		}

		return [
			'id'             => (int) ( $row['id'] ?? 0 ),
			'code'           => (string) ( $row['internal_code'] ?? '' ),
			'name'           => (string) ( $row['internal_name'] ?? '' ),
			'public_label'   => (string) ( $row['public_label'] ?? '' ),
			'is_fallback'    => ! empty( $row['is_fallback'] ),
			'is_remote_area' => ! empty( $row['remote_area_flag'] ),
			'priority'       => (int) ( $row['priority'] ?? 100 ),
			'status'         => (string) ( $row['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @param array<string, mixed> $draft
	 *
	 * @return array<string, mixed>
	 */
	private function form_record_from_draft( array $draft ): array {
		return [
			'id'             => isset( $draft['id'] ) ? (int) $draft['id'] : 0,
			'code'           => (string) ( $draft['code'] ?? '' ),
			'name'           => (string) ( $draft['name'] ?? '' ),
			'public_label'   => (string) ( $draft['public_label'] ?? '' ),
			'is_fallback'    => ! empty( $draft['is_fallback'] ),
			'is_remote_area' => ! empty( $draft['is_remote_area'] ),
			'priority'       => isset( $draft['priority'] ) ? (int) $draft['priority'] : 100,
			'status'         => (string) ( $draft['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_form_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return [
			'id'                => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'code'              => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '',
			'name'              => isset( $_POST['name'] ) ? wp_unslash( (string) $_POST['name'] ) : '',
			'public_label'      => isset( $_POST['public_label'] ) ? wp_unslash( (string) $_POST['public_label'] ) : '',
			'is_fallback'       => isset( $_POST['is_fallback'] ) ? 1 : 0,
			'is_remote_area'    => isset( $_POST['is_remote_area'] ) ? 1 : 0,
			'priority'          => $_POST['priority'] ?? 100,
			'status'            => isset( $_POST['status'] ) ? wp_unslash( (string) $_POST['status'] ) : '',
			'destination_rules' => isset( $_POST['destination_rules'] ) && is_array( $_POST['destination_rules'] )
				? wp_unslash( $_POST['destination_rules'] )
				: [],
		];
	}

	/**
	 * @param list<array<string, mixed>> $rules
	 *
	 * @return array{country: string, region: string, city: string}
	 */
	private function summarize_rules( array $rules ): array {
		$summary = [
			'country' => '—',
			'region'  => '—',
			'city'    => '—',
		];

		foreach ( $rules as $rule ) {
			$type  = (string) ( $rule['rule_type'] ?? '' );
			$value = (string) ( $rule['rule_value'] ?? '' );

			if ( '' === $value ) {
				continue;
			}

			if ( DestinationRuleType::Country->value === $type ) {
				$summary['country'] = $value;
			} elseif ( DestinationRuleType::Region->value === $type ) {
				$summary['region'] = $value;
			} elseif ( DestinationRuleType::City->value === $type ) {
				$summary['city'] = $value;
			}
		}

		return $summary;
	}

	private function render_actions( int $id ): string {
		$edit_url = esc_url( AdminPageRenderer::edit_url( self::SLUG, $id ) );
		$edit     = '<a href="' . $edit_url . '">' . esc_html__( 'Edit', 'cetech-woocommerce-delivery-engine' ) . '</a>';

		$deactivate = '<form method="post" style="display:inline;margin-left:8px;">';
		$deactivate .= wp_nonce_field( self::ACTION_DEACTIVATE, 'cetech_de_nonce', true, false );
		$deactivate .= '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_DEACTIVATE ) . '" />';
		$deactivate .= '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		$deactivate .= '<button type="submit" class="button-link delete" onclick="return confirm(\'' . esc_js( __( 'Deactivate this destination zone?', 'cetech-woocommerce-delivery-engine' ) ) . '\');">';
		$deactivate .= esc_html__( 'Deactivate', 'cetech-woocommerce-delivery-engine' );
		$deactivate .= '</button></form>';

		return $edit . $deactivate;
	}

	/**
	 * @return array<string, string>
	 */
	private function status_options(): array {
		$options = [];

		foreach ( RecordStatus::cases() as $status ) {
			$options[ $status->value ] = $status->value;
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function rule_type_options(): array {
		$options = [];

		foreach ( DestinationRuleType::cases() as $type ) {
			$options[ $type->value ] = $type->value;
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function match_mode_options(): array {
		$options = [];

		foreach ( DestinationRuleMatchMode::cases() as $mode ) {
			$options[ $mode->value ] = $mode->value;
		}

		return $options;
	}
}

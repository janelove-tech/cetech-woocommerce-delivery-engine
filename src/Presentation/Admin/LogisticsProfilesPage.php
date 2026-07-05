<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\Enum\DeliveryRoute;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\LogisticsProfile\LogisticsProfileRepositoryInterface;
use CetechDeliveryEngine\Presentation\Admin\Validation\LogisticsProfileValidator;

final class LogisticsProfilesPage {

	public const SLUG = 'cetech-delivery-engine-logistics-profiles';

	private const ACTION_SAVE = 'cetech_de_save_logistics_profile';

	private const ACTION_DEACTIVATE = 'cetech_de_deactivate_logistics_profile';

	public function __construct(
		private LogisticsProfileRepositoryInterface $repository,
		private LogisticsProfileValidator $validator,
		private AdminActionHandler $action_handler,
		private ConfigurationAuditLogger $audit_logger
	) {
	}

	public function handle_actions(): void {
		if ( $this->action_handler->verify_post( self::ACTION_SAVE, self::ACTION_SAVE, 'manage_logistics_profiles', self::SLUG ) ) {
			$this->handle_save();
		}

		if ( $this->action_handler->verify_post( self::ACTION_DEACTIVATE, self::ACTION_DEACTIVATE, 'manage_logistics_profiles', self::SLUG ) ) {
			$this->handle_deactivate();
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_logistics_profiles' ) ) {
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
		AdminPageRenderer::open_wrap( __( 'Logistics Profiles', 'cetech-woocommerce-delivery-engine' ) );
		AdminPageRenderer::add_new_button( self::SLUG, __( 'Add New', 'cetech-woocommerce-delivery-engine' ) );

		$records = $this->repository->list( [ 'limit' => 500 ] );
		$rows    = [];

		foreach ( $records as $record ) {
			$id = (int) ( $record['id'] ?? 0 );
			$rows[] = [
				(string) $id,
				esc_html( (string) ( $record['internal_code'] ?? '' ) ),
				esc_html( (string) ( $record['internal_name'] ?? '' ) ),
				esc_html( (string) ( $record['parcel_size_class'] ?? '' ) ),
				esc_html( (string) ( $record['handling_class'] ?? '' ) ),
				esc_html( $this->format_route_eligibility( (string) ( $record['route_eligibility'] ?? '' ) ) ),
				esc_html( (string) ( $record['consolidation_rule'] ?? '' ) ),
				esc_html( (string) ( $record['status'] ?? '' ) ),
				esc_html( (string) ( $record['updated_at'] ?? '' ) ),
				$this->render_actions( $id ),
			];
		}

		AdminPageRenderer::render_table(
			[
				__( 'ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Code', 'cetech-woocommerce-delivery-engine' ),
				__( 'Name', 'cetech-woocommerce-delivery-engine' ),
				__( 'Parcel size class', 'cetech-woocommerce-delivery-engine' ),
				__( 'Handling type', 'cetech-woocommerce-delivery-engine' ),
				__( 'Route eligibility', 'cetech-woocommerce-delivery-engine' ),
				__( 'Consolidation policy', 'cetech-woocommerce-delivery-engine' ),
				__( 'Status', 'cetech-woocommerce-delivery-engine' ),
				__( 'Updated at', 'cetech-woocommerce-delivery-engine' ),
				__( 'Actions', 'cetech-woocommerce-delivery-engine' ),
			],
			$rows
		);

		AdminPageRenderer::close_wrap();
	}

	private function render_form( bool $is_edit ): void {
		$draft = $this->action_handler->notices()->consume_form_draft( self::SLUG );

		if ( null !== $draft ) {
			$record = $this->form_record_from_draft( $draft );
		} else {
			$record = $this->load_record_for_form( $is_edit );
		}

		$title  = $is_edit
			? __( 'Edit Logistics Profile', 'cetech-woocommerce-delivery-engine' )
			: __( 'Add Logistics Profile', 'cetech-woocommerce-delivery-engine' );

		AdminPageRenderer::open_wrap( $title );

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';

		if ( $is_edit && null !== $record ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) ( $record['id'] ?? 0 ) ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		AdminFormHelper::text_field(
			'code',
			__( 'Code', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['code'] ?? '' ),
			true,
			__( 'Lowercase letters, numbers, underscores, and hyphens only.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::text_field(
			'name',
			__( 'Name', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['name'] ?? '' ),
			true
		);
		AdminFormHelper::textarea_field(
			'description',
			__( 'Description', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['description'] ?? '' )
		);
		AdminFormHelper::text_field(
			'parcel_size_class',
			__( 'Parcel size class', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['parcel_size_class'] ?? '' )
		);
		AdminFormHelper::text_field(
			'handling_type',
			__( 'Handling type', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['handling_type'] ?? '' )
		);
		AdminFormHelper::checkbox_group_field(
			'route_eligibility',
			__( 'Route eligibility', 'cetech-woocommerce-delivery-engine' ),
			$this->route_options(),
			(array) ( $record['route_eligibility'] ?? [] ),
			__( 'Select eligible delivery routes for this profile.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::text_field(
			'consolidation_policy',
			__( 'Consolidation policy', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['consolidation_policy'] ?? '' )
		);
		AdminFormHelper::select_field(
			'status',
			__( 'Status', 'cetech-woocommerce-delivery-engine' ),
			$this->status_options(),
			(string) ( $record['status'] ?? RecordStatus::Active->value )
		);

		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Update Profile', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Profile', 'cetech-woocommerce-delivery-engine' ) );
		echo ' <a class="button" href="' . esc_url( AdminPageRenderer::list_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		echo '</form>';
		AdminPageRenderer::close_wrap();
	}

	private function handle_save(): void {
		$input = $this->read_form_input();
		$errors = $this->validator->validate( $input, isset( $input['id'] ) ? (int) $input['id'] : null );

		if ( [] !== $errors ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( implode( ' ', $errors ) );
			$this->action_handler->redirect(
				self::SLUG,
				[
					'action' => isset( $input['id'] ) && (int) $input['id'] > 0 ? 'edit' : 'add',
					'id'     => isset( $input['id'] ) ? (int) $input['id'] : 0,
				]
			);
		}

		$code = AdminFormHelper::sanitize_code( (string) $input['code'] );
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$existing_by_code = $this->repository->findByCode( $code );

		if ( null !== $existing_by_code && (int) ( $existing_by_code['id'] ?? 0 ) !== $id ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( __( 'A logistics profile with this code already exists.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG, $id > 0 ? [ 'action' => 'edit', 'id' => $id ] : [ 'action' => 'add' ] );
		}

		$previous = $id > 0 ? $this->repository->findById( $id ) : null;
		$routes   = isset( $input['route_eligibility'] ) && is_array( $input['route_eligibility'] )
			? array_map( 'strval', $input['route_eligibility'] )
			: [];

		$payload = [
			'id'                 => $id,
			'internal_code'      => $code,
			'internal_name'      => trim( (string) $input['name'] ),
			'description'        => trim( (string) ( $input['description'] ?? '' ) ),
			'parcel_size_class'  => trim( (string) ( $input['parcel_size_class'] ?? '' ) ),
			'handling_class'     => trim( (string) ( $input['handling_type'] ?? '' ) ),
			'route_eligibility'  => $this->validator->encode_route_eligibility( $routes ),
			'consolidation_rule' => trim( (string) ( $input['consolidation_policy'] ?? '' ) ),
			'status'             => (string) $input['status'],
		];

		$saved_id = $this->repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to save logistics profile.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'logistics_profile',
			$saved_id,
			$previous,
			$this->repository->findById( $saved_id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Logistics profile updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Logistics profile created.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			$this->action_handler->notices()->flash_warning(
				$id > 0
					? __( 'Logistics profile updated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Logistics profile created, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
			);
		}
		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid logistics profile.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Logistics profile not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->repository->softDelete( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate logistics profile.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Logistics profile is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'logistics_profile',
			$id,
			$previous,
			$this->repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Logistics profile deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Logistics profile deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}
		$this->action_handler->redirect( self::SLUG );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function load_record_for_form( bool $is_edit ): ?array {
		if ( ! $is_edit ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( $id <= 0 ) {
			return [];
		}

		$row = $this->repository->findById( $id );

		if ( null === $row ) {
			return [];
		}

		return [
			'id'                   => (int) ( $row['id'] ?? 0 ),
			'code'                 => (string) ( $row['internal_code'] ?? '' ),
			'name'                 => (string) ( $row['internal_name'] ?? '' ),
			'description'          => (string) ( $row['description'] ?? '' ),
			'parcel_size_class'    => (string) ( $row['parcel_size_class'] ?? '' ),
			'handling_type'        => (string) ( $row['handling_class'] ?? '' ),
			'route_eligibility'    => $this->validator->decode_route_eligibility( (string) ( $row['route_eligibility'] ?? '' ) ),
			'consolidation_policy' => (string) ( $row['consolidation_rule'] ?? '' ),
			'status'               => (string) ( $row['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_form_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return [
			'id'                   => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'code'                 => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '',
			'name'                 => isset( $_POST['name'] ) ? wp_unslash( (string) $_POST['name'] ) : '',
			'description'          => isset( $_POST['description'] ) ? wp_unslash( (string) $_POST['description'] ) : '',
			'parcel_size_class'    => isset( $_POST['parcel_size_class'] ) ? wp_unslash( (string) $_POST['parcel_size_class'] ) : '',
			'handling_type'        => isset( $_POST['handling_type'] ) ? wp_unslash( (string) $_POST['handling_type'] ) : '',
			'route_eligibility'    => isset( $_POST['route_eligibility'] ) ? array_map( 'wp_unslash', (array) $_POST['route_eligibility'] ) : [],
			'consolidation_policy' => isset( $_POST['consolidation_policy'] ) ? wp_unslash( (string) $_POST['consolidation_policy'] ) : '',
			'status'               => isset( $_POST['status'] ) ? wp_unslash( (string) $_POST['status'] ) : '',
		];
	}

	/**
	 * @param array<string, mixed> $draft
	 *
	 * @return array<string, mixed>
	 */
	private function form_record_from_draft( array $draft ): array {
		return [
			'id'                   => isset( $draft['id'] ) ? (int) $draft['id'] : 0,
			'code'                 => (string) ( $draft['code'] ?? '' ),
			'name'                 => (string) ( $draft['name'] ?? '' ),
			'description'          => (string) ( $draft['description'] ?? '' ),
			'parcel_size_class'    => (string) ( $draft['parcel_size_class'] ?? '' ),
			'handling_type'        => (string) ( $draft['handling_type'] ?? '' ),
			'route_eligibility'    => isset( $draft['route_eligibility'] ) && is_array( $draft['route_eligibility'] )
				? array_map( 'strval', $draft['route_eligibility'] )
				: [],
			'consolidation_policy' => (string) ( $draft['consolidation_policy'] ?? '' ),
			'status'               => (string) ( $draft['status'] ?? RecordStatus::Active->value ),
		];
	}

	private function render_actions( int $id ): string {
		$edit_url = esc_url( AdminPageRenderer::edit_url( self::SLUG, $id ) );
		$edit     = '<a href="' . $edit_url . '">' . esc_html__( 'Edit', 'cetech-woocommerce-delivery-engine' ) . '</a>';

		$deactivate = '<form method="post" style="display:inline;margin-left:8px;">';
		$deactivate .= wp_nonce_field( self::ACTION_DEACTIVATE, 'cetech_de_nonce', true, false );
		$deactivate .= '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_DEACTIVATE ) . '" />';
		$deactivate .= '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		$deactivate .= '<button type="submit" class="button-link delete" onclick="return confirm(\'' . esc_js( __( 'Deactivate this logistics profile?', 'cetech-woocommerce-delivery-engine' ) ) . '\');">';
		$deactivate .= esc_html__( 'Deactivate', 'cetech-woocommerce-delivery-engine' );
		$deactivate .= '</button></form>';

		return $edit . $deactivate;
	}

	private function format_route_eligibility( string $stored ): string {
		$routes = $this->validator->decode_route_eligibility( $stored );

		return [] === $routes ? '—' : implode( ', ', $routes );
	}

	/**
	 * @return array<string, string>
	 */
	private function route_options(): array {
		$options = [];

		foreach ( DeliveryRoute::cases() as $route ) {
			$options[ $route->value ] = $route->value;
		}

		return $options;
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
}

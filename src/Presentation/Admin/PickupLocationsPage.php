<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\Pickup\PickupLocationRepositoryInterface;
use CetechDeliveryEngine\Presentation\Admin\Validation\PickupLocationValidator;

final class PickupLocationsPage {

	public const SLUG = 'cetech-delivery-engine-pickup-locations';

	private const ACTION_SAVE = 'cetech_de_save_pickup_location';

	private const ACTION_DEACTIVATE = 'cetech_de_deactivate_pickup_location';

	public function __construct(
		private PickupLocationRepositoryInterface $repository,
		private PickupLocationValidator $validator,
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
	}

	public function render(): void {
		AdminPageAccess::require_capability( 'manage_delivery_zones' );

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
		AdminPageRenderer::open_wrap( __( 'Pickup Locations', 'cetech-woocommerce-delivery-engine' ) );
		AdminPageRenderer::add_new_button( self::SLUG, __( 'Add New', 'cetech-woocommerce-delivery-engine' ) );

		$records = $this->repository->list( [ 'limit' => 500 ] );
		$rows    = [];

		foreach ( $records as $record ) {
			$id      = (int) ( $record['id'] ?? 0 );
			$address = $this->validator->decode_public_address( isset( $record['public_address'] ) ? (string) $record['public_address'] : null );

			$rows[] = [
				(string) $id,
				esc_html( (string) ( $record['internal_code'] ?? '' ) ),
				esc_html( (string) ( $record['location_name'] ?? '' ) ),
				esc_html( $this->validator->address_summary( isset( $record['public_address'] ) ? (string) $record['public_address'] : null ) ),
				esc_html( $address['city'] ?: '—' ),
				esc_html( $address['region'] ?: '—' ),
				esc_html( $address['country_code'] ?: '—' ),
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
				__( 'Address summary', 'cetech-woocommerce-delivery-engine' ),
				__( 'City', 'cetech-woocommerce-delivery-engine' ),
				__( 'Region', 'cetech-woocommerce-delivery-engine' ),
				__( 'Country', 'cetech-woocommerce-delivery-engine' ),
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

		$title = $is_edit
			? __( 'Edit Pickup Location', 'cetech-woocommerce-delivery-engine' )
			: __( 'Add Pickup Location', 'cetech-woocommerce-delivery-engine' );

		AdminPageRenderer::open_wrap( $title );

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';

		if ( $is_edit && ! empty( $record['id'] ) ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $record['id'] ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		AdminFormHelper::text_field( 'code', __( 'Code', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['code'] ?? '' ), true );
		AdminFormHelper::text_field( 'location_name', __( 'Location name', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['location_name'] ?? '' ), true );
		AdminFormHelper::text_field( 'address_line_1', __( 'Address line 1', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['address_line_1'] ?? '' ) );
		AdminFormHelper::text_field( 'address_line_2', __( 'Address line 2', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['address_line_2'] ?? '' ) );
		AdminFormHelper::text_field( 'city', __( 'City', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['city'] ?? '' ) );
		AdminFormHelper::text_field( 'region', __( 'Region', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['region'] ?? '' ) );
		AdminFormHelper::text_field(
			'country_code',
			__( 'Country code', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['country_code'] ?? '' ),
			false,
			__( '2-letter ISO code.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::text_field( 'postcode', __( 'Postcode', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['postcode'] ?? '' ) );
		AdminFormHelper::text_field( 'contact_phone', __( 'Phone', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['contact_phone'] ?? '' ) );
		AdminFormHelper::text_field( 'contact_email', __( 'Email', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['contact_email'] ?? '' ) );
		AdminFormHelper::textarea_field(
			'public_opening_hours',
			__( 'Opening hours', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['public_opening_hours'] ?? '' )
		);
		AdminFormHelper::textarea_field(
			'public_pickup_instructions',
			__( 'Pickup instructions', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['public_pickup_instructions'] ?? '' )
		);
		AdminFormHelper::select_field( 'status', __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->status_options(), (string) ( $record['status'] ?? RecordStatus::Active->value ) );
		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Update Location', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Location', 'cetech-woocommerce-delivery-engine' ) );
		echo ' <a class="button" href="' . esc_url( AdminPageRenderer::list_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		echo '</form>';
		AdminPageRenderer::close_wrap();
	}

	private function handle_save(): void {
		$input  = $this->read_form_input();
		$errors = $this->validator->validate( $input, isset( $input['id'] ) ? (int) $input['id'] : null );

		if ( [] !== $errors ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( implode( ' ', $errors ) );
			$this->redirect_to_form( $input );
		}

		$code = AdminFormHelper::sanitize_code( (string) $input['code'] );
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$existing_by_code = $this->repository->findByCode( $code );

		if ( null !== $existing_by_code && (int) ( $existing_by_code['id'] ?? 0 ) !== $id ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( __( 'A pickup location with this code already exists.', 'cetech-woocommerce-delivery-engine' ) );
			$this->redirect_to_form( $input );
		}

		$previous = $id > 0 ? $this->repository->findById( $id ) : null;

		$payload = [
			'id'                         => $id,
			'internal_code'              => $code,
			'location_name'              => trim( (string) $input['location_name'] ),
			'public_address'             => $this->validator->encode_public_address( $input ),
			'public_opening_hours'       => trim( (string) ( $input['public_opening_hours'] ?? '' ) ),
			'public_pickup_instructions' => trim( (string) ( $input['public_pickup_instructions'] ?? '' ) ),
			'contact_phone'              => trim( (string) ( $input['contact_phone'] ?? '' ) ),
			'contact_email'              => trim( (string) ( $input['contact_email'] ?? '' ) ),
			'status'                     => (string) $input['status'],
		];

		$saved_id = $this->repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to save pickup location.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'pickup_location',
			$saved_id,
			$previous,
			$this->repository->findById( $saved_id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Pickup location updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Pickup location created.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			$this->action_handler->notices()->flash_warning(
				$id > 0
					? __( 'Pickup location updated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Pickup location created, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
			);
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid pickup location.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Pickup location not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->repository->softDelete( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate pickup location.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Pickup location is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'pickup_location',
			$id,
			$previous,
			$this->repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Pickup location deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Pickup location deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}

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

		$row = $this->repository->findById( $id );

		if ( null === $row ) {
			return [];
		}

		$address = $this->validator->decode_public_address( isset( $row['public_address'] ) ? (string) $row['public_address'] : null );

		return array_merge(
			[
				'id'                         => (int) ( $row['id'] ?? 0 ),
				'code'                       => (string) ( $row['internal_code'] ?? '' ),
				'location_name'              => (string) ( $row['location_name'] ?? '' ),
				'contact_phone'              => (string) ( $row['contact_phone'] ?? '' ),
				'contact_email'              => (string) ( $row['contact_email'] ?? '' ),
				'public_opening_hours'       => (string) ( $row['public_opening_hours'] ?? '' ),
				'public_pickup_instructions' => (string) ( $row['public_pickup_instructions'] ?? '' ),
				'status'                     => (string) ( $row['status'] ?? RecordStatus::Active->value ),
			],
			$address
		);
	}

	/**
	 * @param array<string, mixed> $draft
	 *
	 * @return array<string, mixed>
	 */
	private function form_record_from_draft( array $draft ): array {
		return [
			'id'                         => isset( $draft['id'] ) ? (int) $draft['id'] : 0,
			'code'                       => (string) ( $draft['code'] ?? '' ),
			'location_name'              => (string) ( $draft['location_name'] ?? '' ),
			'address_line_1'             => (string) ( $draft['address_line_1'] ?? '' ),
			'address_line_2'             => (string) ( $draft['address_line_2'] ?? '' ),
			'city'                       => (string) ( $draft['city'] ?? '' ),
			'region'                     => (string) ( $draft['region'] ?? '' ),
			'country_code'               => (string) ( $draft['country_code'] ?? '' ),
			'postcode'                   => (string) ( $draft['postcode'] ?? '' ),
			'contact_phone'              => (string) ( $draft['contact_phone'] ?? '' ),
			'contact_email'              => (string) ( $draft['contact_email'] ?? '' ),
			'public_opening_hours'       => (string) ( $draft['public_opening_hours'] ?? '' ),
			'public_pickup_instructions' => (string) ( $draft['public_pickup_instructions'] ?? '' ),
			'status'                     => (string) ( $draft['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_form_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return [
			'id'                         => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'code'                       => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '',
			'location_name'              => isset( $_POST['location_name'] ) ? wp_unslash( (string) $_POST['location_name'] ) : '',
			'address_line_1'             => isset( $_POST['address_line_1'] ) ? wp_unslash( (string) $_POST['address_line_1'] ) : '',
			'address_line_2'             => isset( $_POST['address_line_2'] ) ? wp_unslash( (string) $_POST['address_line_2'] ) : '',
			'city'                       => isset( $_POST['city'] ) ? wp_unslash( (string) $_POST['city'] ) : '',
			'region'                     => isset( $_POST['region'] ) ? wp_unslash( (string) $_POST['region'] ) : '',
			'country_code'               => isset( $_POST['country_code'] ) ? wp_unslash( (string) $_POST['country_code'] ) : '',
			'postcode'                   => isset( $_POST['postcode'] ) ? wp_unslash( (string) $_POST['postcode'] ) : '',
			'contact_phone'              => isset( $_POST['contact_phone'] ) ? wp_unslash( (string) $_POST['contact_phone'] ) : '',
			'contact_email'              => isset( $_POST['contact_email'] ) ? wp_unslash( (string) $_POST['contact_email'] ) : '',
			'public_opening_hours'       => isset( $_POST['public_opening_hours'] ) ? wp_unslash( (string) $_POST['public_opening_hours'] ) : '',
			'public_pickup_instructions' => isset( $_POST['public_pickup_instructions'] ) ? wp_unslash( (string) $_POST['public_pickup_instructions'] ) : '',
			'status'                     => isset( $_POST['status'] ) ? wp_unslash( (string) $_POST['status'] ) : '',
		];
	}

	private function render_actions( int $id ): string {
		$edit_url = esc_url( AdminPageRenderer::edit_url( self::SLUG, $id ) );
		$edit     = '<a href="' . $edit_url . '">' . esc_html__( 'Edit', 'cetech-woocommerce-delivery-engine' ) . '</a>';

		$deactivate = '<form method="post" style="display:inline;margin-left:8px;">';
		$deactivate .= wp_nonce_field( self::ACTION_DEACTIVATE, 'cetech_de_nonce', true, false );
		$deactivate .= '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_DEACTIVATE ) . '" />';
		$deactivate .= '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		$deactivate .= '<button type="submit" class="button-link delete" onclick="return confirm(\'' . esc_js( __( 'Deactivate this pickup location?', 'cetech-woocommerce-delivery-engine' ) ) . '\');">';
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
}

<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\Enum\CarrierVisibility;
use CetechDeliveryEngine\Domain\Enum\DeliveryRoute;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\DeliveryOffer\DeliveryOfferRepositoryInterface;
use CetechDeliveryEngine\Presentation\Admin\Validation\DeliveryOfferValidator;

final class DeliveryOffersPage {

	public const SLUG = 'cetech-delivery-engine-delivery-offers';

	private const ACTION_SAVE = 'cetech_de_save_delivery_offer';

	private const ACTION_DEACTIVATE = 'cetech_de_deactivate_delivery_offer';

	public function __construct(
		private DeliveryOfferRepositoryInterface $repository,
		private DeliveryOfferValidator $validator,
		private AdminActionHandler $action_handler,
		private ConfigurationAuditLogger $audit_logger
	) {
	}

	public function handle_actions(): void {
		if ( $this->action_handler->verify_post( self::ACTION_SAVE, self::ACTION_SAVE, 'manage_delivery_offers', self::SLUG ) ) {
			$this->handle_save();
		}

		if ( $this->action_handler->verify_post( self::ACTION_DEACTIVATE, self::ACTION_DEACTIVATE, 'manage_delivery_offers', self::SLUG ) ) {
			$this->handle_deactivate();
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_delivery_offers' ) ) {
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
		AdminPageRenderer::open_wrap( __( 'Delivery Offers', 'cetech-woocommerce-delivery-engine' ) );
		AdminPageRenderer::add_new_button( self::SLUG, __( 'Add New', 'cetech-woocommerce-delivery-engine' ) );

		$records = $this->repository->list( [ 'limit' => 500 ] );
		$rows    = [];

		foreach ( $records as $record ) {
			$id = (int) ( $record['id'] ?? 0 );
			$rows[] = [
				(string) $id,
				esc_html( (string) ( $record['internal_code'] ?? '' ) ),
				esc_html( (string) ( $record['public_label'] ?? '' ) ),
				esc_html( (string) ( $record['route'] ?? '' ) ),
				esc_html( (string) ( $record['service_level'] ?? '' ) ),
				esc_html( (string) ( $record['carrier_visibility'] ?? '' ) ),
				esc_html( (string) ( $record['carrier_name'] ?? '' ) ),
				esc_html( (string) ( $record['status'] ?? '' ) ),
				esc_html( (string) ( $record['display_priority'] ?? '' ) ),
				esc_html( (string) ( $record['updated_at'] ?? '' ) ),
				$this->render_actions( $id ),
			];
		}

		AdminPageRenderer::render_table(
			[
				__( 'ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Code', 'cetech-woocommerce-delivery-engine' ),
				__( 'Public label', 'cetech-woocommerce-delivery-engine' ),
				__( 'Route', 'cetech-woocommerce-delivery-engine' ),
				__( 'Service level', 'cetech-woocommerce-delivery-engine' ),
				__( 'Carrier visibility', 'cetech-woocommerce-delivery-engine' ),
				__( 'Carrier display name', 'cetech-woocommerce-delivery-engine' ),
				__( 'Status', 'cetech-woocommerce-delivery-engine' ),
				__( 'Display priority', 'cetech-woocommerce-delivery-engine' ),
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
			? __( 'Edit Delivery Offer', 'cetech-woocommerce-delivery-engine' )
			: __( 'Add Delivery Offer', 'cetech-woocommerce-delivery-engine' );

		AdminPageRenderer::open_wrap( $title );

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';

		if ( $is_edit && null !== $record && ! empty( $record['id'] ) ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $record['id'] ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		AdminFormHelper::text_field( 'code', __( 'Code', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['code'] ?? '' ), true );
		AdminFormHelper::text_field( 'public_label', __( 'Public label', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['public_label'] ?? '' ), true );
		AdminFormHelper::textarea_field( 'description', __( 'Description', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['description'] ?? '' ) );
		AdminFormHelper::select_field( 'route', __( 'Route', 'cetech-woocommerce-delivery-engine' ), $this->route_options(), (string) ( $record['route'] ?? '' ) );
		AdminFormHelper::text_field( 'service_level', __( 'Service level', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['service_level'] ?? '' ) );
		AdminFormHelper::select_field(
			'carrier_visibility',
			__( 'Carrier visibility', 'cetech-woocommerce-delivery-engine' ),
			$this->carrier_visibility_options(),
			(string) ( $record['carrier_visibility'] ?? CarrierVisibility::AssignedByStore->value )
		);
		AdminFormHelper::text_field(
			'carrier_display_name',
			__( 'Carrier display name', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['carrier_display_name'] ?? '' ),
			false,
			__( 'Required when carrier visibility is Named.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::number_field( 'processing_min_days', __( 'Processing min days', 'cetech-woocommerce-delivery-engine' ), $record['processing_min_days'] ?? null );
		AdminFormHelper::number_field( 'processing_max_days', __( 'Processing max days', 'cetech-woocommerce-delivery-engine' ), $record['processing_max_days'] ?? null );
		AdminFormHelper::number_field( 'transit_min_days', __( 'Transit min days', 'cetech-woocommerce-delivery-engine' ), $record['transit_min_days'] ?? null );
		AdminFormHelper::number_field( 'transit_max_days', __( 'Transit max days', 'cetech-woocommerce-delivery-engine' ), $record['transit_max_days'] ?? null );
		AdminFormHelper::number_field( 'final_mile_min_days', __( 'Final mile min days', 'cetech-woocommerce-delivery-engine' ), $record['final_mile_min_days'] ?? null );
		AdminFormHelper::number_field( 'final_mile_max_days', __( 'Final mile max days', 'cetech-woocommerce-delivery-engine' ), $record['final_mile_max_days'] ?? null );
		AdminFormHelper::number_field( 'display_priority', __( 'Display priority', 'cetech-woocommerce-delivery-engine' ), $record['display_priority'] ?? 100 );
		AdminFormHelper::select_field( 'status', __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->status_options(), (string) ( $record['status'] ?? RecordStatus::Active->value ) );

		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Update Offer', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Offer', 'cetech-woocommerce-delivery-engine' ) );
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
			$this->action_handler->notices()->flash_error( __( 'A delivery offer with this code already exists.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG, $id > 0 ? [ 'action' => 'edit', 'id' => $id ] : [ 'action' => 'add' ] );
		}

		$public_label = trim( (string) $input['public_label'] );
		$carrier_visibility = (string) $input['carrier_visibility'];
		$carrier_name = CarrierVisibility::Named->value === $carrier_visibility
			? trim( (string) ( $input['carrier_display_name'] ?? '' ) )
			: null;

		$previous = $id > 0 ? $this->repository->findById( $id ) : null;

		$payload = [
			'id'                     => $id,
			'internal_code'          => $code,
			'internal_name'          => $public_label,
			'public_label'           => $public_label,
			'public_description'     => trim( (string) ( $input['description'] ?? '' ) ),
			'route'                  => (string) $input['route'],
			'service_level'          => trim( (string) ( $input['service_level'] ?? '' ) ),
			'carrier_visibility'     => $carrier_visibility,
			'carrier_name'           => $carrier_name,
			'default_processing_min' => DeliveryOfferValidator::nullable_int( $input['processing_min_days'] ?? null ),
			'default_processing_max' => DeliveryOfferValidator::nullable_int( $input['processing_max_days'] ?? null ),
			'default_transit_min'    => DeliveryOfferValidator::nullable_int( $input['transit_min_days'] ?? null ),
			'default_transit_max'    => DeliveryOfferValidator::nullable_int( $input['transit_max_days'] ?? null ),
			'default_final_mile_min' => DeliveryOfferValidator::nullable_int( $input['final_mile_min_days'] ?? null ),
			'default_final_mile_max' => DeliveryOfferValidator::nullable_int( $input['final_mile_max_days'] ?? null ),
			'display_priority'       => (int) ( $input['display_priority'] ?? 100 ),
			'status'                 => (string) $input['status'],
		];

		$saved_id = $this->repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to save delivery offer.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'delivery_offer',
			$saved_id,
			$previous,
			$this->repository->findById( $saved_id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Delivery offer updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Delivery offer created.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			$this->action_handler->notices()->flash_warning(
				$id > 0
					? __( 'Delivery offer updated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Delivery offer created, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
			);
		}
		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid delivery offer.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Delivery offer not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->repository->softDelete( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate delivery offer.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Delivery offer is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'delivery_offer',
			$id,
			$previous,
			$this->repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Delivery offer deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Delivery offer deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}
		$this->action_handler->redirect( self::SLUG );
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

		return [
			'id'                   => (int) ( $row['id'] ?? 0 ),
			'code'                 => (string) ( $row['internal_code'] ?? '' ),
			'public_label'         => (string) ( $row['public_label'] ?? '' ),
			'description'          => (string) ( $row['public_description'] ?? '' ),
			'route'                => (string) ( $row['route'] ?? '' ),
			'service_level'        => (string) ( $row['service_level'] ?? '' ),
			'carrier_visibility'   => (string) ( $row['carrier_visibility'] ?? '' ),
			'carrier_display_name' => (string) ( $row['carrier_name'] ?? '' ),
			'processing_min_days'  => isset( $row['default_processing_min'] ) ? (int) $row['default_processing_min'] : null,
			'processing_max_days'  => isset( $row['default_processing_max'] ) ? (int) $row['default_processing_max'] : null,
			'transit_min_days'     => isset( $row['default_transit_min'] ) ? (int) $row['default_transit_min'] : null,
			'transit_max_days'     => isset( $row['default_transit_max'] ) ? (int) $row['default_transit_max'] : null,
			'final_mile_min_days'  => isset( $row['default_final_mile_min'] ) ? (int) $row['default_final_mile_min'] : null,
			'final_mile_max_days'  => isset( $row['default_final_mile_max'] ) ? (int) $row['default_final_mile_max'] : null,
			'display_priority'     => (int) ( $row['display_priority'] ?? 100 ),
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
			'public_label'         => isset( $_POST['public_label'] ) ? wp_unslash( (string) $_POST['public_label'] ) : '',
			'description'          => isset( $_POST['description'] ) ? wp_unslash( (string) $_POST['description'] ) : '',
			'route'                => isset( $_POST['route'] ) ? wp_unslash( (string) $_POST['route'] ) : '',
			'service_level'        => isset( $_POST['service_level'] ) ? wp_unslash( (string) $_POST['service_level'] ) : '',
			'carrier_visibility'   => isset( $_POST['carrier_visibility'] ) ? wp_unslash( (string) $_POST['carrier_visibility'] ) : '',
			'carrier_display_name' => isset( $_POST['carrier_display_name'] ) ? wp_unslash( (string) $_POST['carrier_display_name'] ) : '',
			'processing_min_days'  => $_POST['processing_min_days'] ?? '',
			'processing_max_days'  => $_POST['processing_max_days'] ?? '',
			'transit_min_days'     => $_POST['transit_min_days'] ?? '',
			'transit_max_days'     => $_POST['transit_max_days'] ?? '',
			'final_mile_min_days'  => $_POST['final_mile_min_days'] ?? '',
			'final_mile_max_days'  => $_POST['final_mile_max_days'] ?? '',
			'display_priority'     => $_POST['display_priority'] ?? 100,
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
			'public_label'         => (string) ( $draft['public_label'] ?? '' ),
			'description'          => (string) ( $draft['description'] ?? '' ),
			'route'                => (string) ( $draft['route'] ?? '' ),
			'service_level'        => (string) ( $draft['service_level'] ?? '' ),
			'carrier_visibility'   => (string) ( $draft['carrier_visibility'] ?? CarrierVisibility::AssignedByStore->value ),
			'carrier_display_name' => (string) ( $draft['carrier_display_name'] ?? '' ),
			'processing_min_days'  => $draft['processing_min_days'] ?? null,
			'processing_max_days'  => $draft['processing_max_days'] ?? null,
			'transit_min_days'     => $draft['transit_min_days'] ?? null,
			'transit_max_days'     => $draft['transit_max_days'] ?? null,
			'final_mile_min_days'  => $draft['final_mile_min_days'] ?? null,
			'final_mile_max_days'  => $draft['final_mile_max_days'] ?? null,
			'display_priority'     => isset( $draft['display_priority'] ) ? (int) $draft['display_priority'] : 100,
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
		$deactivate .= '<button type="submit" class="button-link delete" onclick="return confirm(\'' . esc_js( __( 'Deactivate this delivery offer?', 'cetech-woocommerce-delivery-engine' ) ) . '\');">';
		$deactivate .= esc_html__( 'Deactivate', 'cetech-woocommerce-delivery-engine' );
		$deactivate .= '</button></form>';

		return $edit . $deactivate;
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
	private function carrier_visibility_options(): array {
		$options = [];

		foreach ( CarrierVisibility::cases() as $visibility ) {
			$options[ $visibility->value ] = $visibility->value;
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

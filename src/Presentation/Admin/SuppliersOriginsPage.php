<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\SupplierRepositoryInterface;
use CetechDeliveryEngine\Presentation\Admin\Validation\OriginValidator;
use CetechDeliveryEngine\Presentation\Admin\Validation\SupplierValidator;

/**
 * Private operational supplier and origin admin CRUD.
 *
 * wp-admin only. These records must never be exposed on the storefront,
 * in customer emails, REST/Store API, or checkout flows.
 */
final class SuppliersOriginsPage {

	public const SLUG = 'cetech-delivery-engine-suppliers-origins';

	private const ACTION_SAVE_SUPPLIER = 'cetech_de_save_supplier';

	private const ACTION_DEACTIVATE_SUPPLIER = 'cetech_de_deactivate_supplier';

	private const ACTION_SAVE_ORIGIN = 'cetech_de_save_origin';

	private const ACTION_DEACTIVATE_ORIGIN = 'cetech_de_deactivate_origin';

	private const DRAFT_SUPPLIER = 'supplier';

	private const DRAFT_ORIGIN = 'origin';

	public function __construct(
		private SupplierRepositoryInterface $supplier_repository,
		private OriginRepositoryInterface $origin_repository,
		private SupplierValidator $supplier_validator,
		private OriginValidator $origin_validator,
		private AdminActionHandler $action_handler,
		private ConfigurationAuditLogger $audit_logger
	) {
	}

	public function handle_actions(): void {
		if ( $this->action_handler->verify_post( self::ACTION_SAVE_SUPPLIER, self::ACTION_SAVE_SUPPLIER, 'manage_private_sources', self::SLUG ) ) {
			$this->handle_save_supplier();
		}

		if ( $this->action_handler->verify_post( self::ACTION_DEACTIVATE_SUPPLIER, self::ACTION_DEACTIVATE_SUPPLIER, 'manage_private_sources', self::SLUG ) ) {
			$this->handle_deactivate_supplier();
		}

		if ( $this->action_handler->verify_post( self::ACTION_SAVE_ORIGIN, self::ACTION_SAVE_ORIGIN, 'manage_private_sources', self::SLUG ) ) {
			$this->handle_save_origin();
		}

		if ( $this->action_handler->verify_post( self::ACTION_DEACTIVATE_ORIGIN, self::ACTION_DEACTIVATE_ORIGIN, 'manage_private_sources', self::SLUG ) ) {
			$this->handle_deactivate_origin();
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_private_sources' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->notices()->render_notices();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entity = isset( $_GET['entity'] ) ? sanitize_key( wp_unslash( (string) $_GET['entity'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : 'list';

		if ( 'supplier' === $entity && ( 'add' === $action || 'edit' === $action ) ) {
			$this->render_supplier_form( 'edit' === $action );
			return;
		}

		if ( 'origin' === $entity && ( 'add' === $action || 'edit' === $action ) ) {
			$this->render_origin_form( 'edit' === $action );
			return;
		}

		$this->render_lists();
	}

	private function render_lists(): void {
		AdminPageRenderer::open_wrap( __( 'Suppliers & Origins', 'cetech-woocommerce-delivery-engine' ) );
		echo '<p class="description">' . esc_html__(
			'Private operational records for admin use only. Never shown to customers.',
			'cetech-woocommerce-delivery-engine'
		) . '</p>';

		$this->render_supplier_list();
		$this->render_origin_list();

		AdminPageRenderer::close_wrap();
	}

	private function render_supplier_list(): void {
		echo '<h2>' . esc_html__( 'Suppliers', 'cetech-woocommerce-delivery-engine' ) . '</h2>';
		printf(
			'<p><a href="%1$s" class="page-title-action">%2$s</a></p>',
			esc_url( $this->form_url( 'supplier', 'add' ) ),
			esc_html__( 'Add Supplier', 'cetech-woocommerce-delivery-engine' )
		);

		$suppliers = $this->supplier_repository->list( [ 'limit' => 500 ] );
		$rows      = [];

		foreach ( $suppliers as $supplier ) {
			$id = (int) ( $supplier['id'] ?? 0 );
			$rows[] = [
				(string) $id,
				esc_html( (string) ( $supplier['internal_code'] ?? '' ) ),
				esc_html( (string) ( $supplier['internal_name'] ?? '' ) ),
				'—',
				'—',
				esc_html( (string) ( $supplier['contact_email'] ?? '' ) ?: '—' ),
				esc_html( (string) ( $supplier['contact_phone'] ?? '' ) ?: '—' ),
				'—',
				esc_html( (string) ( $supplier['status'] ?? '' ) ),
				esc_html( (string) ( $supplier['updated_at'] ?? '' ) ),
				$this->render_supplier_actions( $id ),
			];
		}

		AdminPageRenderer::render_table(
			[
				__( 'ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Code', 'cetech-woocommerce-delivery-engine' ),
				__( 'Internal name', 'cetech-woocommerce-delivery-engine' ),
				__( 'Supplier type', 'cetech-woocommerce-delivery-engine' ),
				__( 'Contact name', 'cetech-woocommerce-delivery-engine' ),
				__( 'Contact email', 'cetech-woocommerce-delivery-engine' ),
				__( 'Contact phone', 'cetech-woocommerce-delivery-engine' ),
				__( 'Country', 'cetech-woocommerce-delivery-engine' ),
				__( 'Status', 'cetech-woocommerce-delivery-engine' ),
				__( 'Updated at', 'cetech-woocommerce-delivery-engine' ),
				__( 'Actions', 'cetech-woocommerce-delivery-engine' ),
			],
			$rows
		);
	}

	private function render_origin_list(): void {
		echo '<h2>' . esc_html__( 'Origins', 'cetech-woocommerce-delivery-engine' ) . '</h2>';
		printf(
			'<p><a href="%1$s" class="page-title-action">%2$s</a></p>',
			esc_url( $this->form_url( 'origin', 'add' ) ),
			esc_html__( 'Add Origin', 'cetech-woocommerce-delivery-engine' )
		);

		$origins    = $this->origin_repository->list( [ 'limit' => 500 ] );
		$suppliers  = $this->supplier_name_map();
		$rows       = [];

		foreach ( $origins as $origin ) {
			$id          = (int) ( $origin['id'] ?? 0 );
			$supplier_id = (int) ( $origin['supplier_id'] ?? 0 );
			$address     = $this->origin_validator->decode_internal_address(
				isset( $origin['internal_address'] ) ? (string) $origin['internal_address'] : null
			);

			$rows[] = [
				(string) $id,
				esc_html( (string) ( $origin['internal_code'] ?? '' ) ),
				esc_html( (string) ( $origin['internal_name'] ?? '' ) ),
				esc_html( $suppliers[ $supplier_id ] ?? '—' ),
				'—',
				esc_html( (string) ( $origin['country_code'] ?? '' ) ?: '—' ),
				esc_html( $address['region'] ?: '—' ),
				esc_html( $address['city'] ?: '—' ),
				esc_html( (string) ( $origin['status'] ?? '' ) ),
				esc_html( (string) ( $origin['updated_at'] ?? '' ) ),
				$this->render_origin_actions( $id ),
			];
		}

		AdminPageRenderer::render_table(
			[
				__( 'ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Code', 'cetech-woocommerce-delivery-engine' ),
				__( 'Internal name', 'cetech-woocommerce-delivery-engine' ),
				__( 'Supplier', 'cetech-woocommerce-delivery-engine' ),
				__( 'Origin type', 'cetech-woocommerce-delivery-engine' ),
				__( 'Country', 'cetech-woocommerce-delivery-engine' ),
				__( 'Region', 'cetech-woocommerce-delivery-engine' ),
				__( 'City', 'cetech-woocommerce-delivery-engine' ),
				__( 'Status', 'cetech-woocommerce-delivery-engine' ),
				__( 'Updated at', 'cetech-woocommerce-delivery-engine' ),
				__( 'Actions', 'cetech-woocommerce-delivery-engine' ),
			],
			$rows
		);
	}

	private function render_supplier_form( bool $is_edit ): void {
		$draft = $this->action_handler->notices()->consume_form_draft( $this->draft_key( self::DRAFT_SUPPLIER ) );
		$record = null !== $draft ? $this->supplier_from_draft( $draft ) : $this->load_supplier_for_form( $is_edit );

		AdminPageRenderer::open_wrap(
			$is_edit
				? __( 'Edit Supplier', 'cetech-woocommerce-delivery-engine' )
				: __( 'Add Supplier', 'cetech-woocommerce-delivery-engine' )
		);

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE_SUPPLIER );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE_SUPPLIER ) . '" />';

		if ( $is_edit && ! empty( $record['id'] ) ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $record['id'] ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		AdminFormHelper::text_field( 'code', __( 'Code', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['code'] ?? '' ), true );
		AdminFormHelper::text_field( 'internal_name', __( 'Internal name', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['internal_name'] ?? '' ), true );
		AdminFormHelper::text_field( 'contact_email', __( 'Contact email', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['contact_email'] ?? '' ) );
		AdminFormHelper::text_field( 'contact_phone', __( 'Contact phone', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['contact_phone'] ?? '' ) );
		AdminFormHelper::textarea_field( 'internal_notes', __( 'Internal notes', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['internal_notes'] ?? '' ), 4, __( 'Private admin notes only.', 'cetech-woocommerce-delivery-engine' ) );
		AdminFormHelper::select_field( 'status', __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->status_options(), (string) ( $record['status'] ?? RecordStatus::Active->value ) );
		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Update Supplier', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Supplier', 'cetech-woocommerce-delivery-engine' ) );
		echo ' <a class="button" href="' . esc_url( AdminPageRenderer::list_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		echo '</form>';
		AdminPageRenderer::close_wrap();
	}

	private function render_origin_form( bool $is_edit ): void {
		$draft = $this->action_handler->notices()->consume_form_draft( $this->draft_key( self::DRAFT_ORIGIN ) );
		$record = null !== $draft ? $this->origin_from_draft( $draft ) : $this->load_origin_for_form( $is_edit );

		AdminPageRenderer::open_wrap(
			$is_edit
				? __( 'Edit Origin', 'cetech-woocommerce-delivery-engine' )
				: __( 'Add Origin', 'cetech-woocommerce-delivery-engine' )
		);

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE_ORIGIN );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE_ORIGIN ) . '" />';

		if ( $is_edit && ! empty( $record['id'] ) ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $record['id'] ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		AdminFormHelper::text_field( 'code', __( 'Code', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['code'] ?? '' ), true );
		AdminFormHelper::text_field( 'internal_name', __( 'Internal name', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['internal_name'] ?? '' ), true );
		AdminFormHelper::select_field(
			'supplier_id',
			__( 'Supplier', 'cetech-woocommerce-delivery-engine' ),
			$this->supplier_options(),
			(string) ( $record['supplier_id'] ?? '' ),
			__( 'Required. Links this origin to a supplier.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::text_field(
			'country_code',
			__( 'Country code', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['country_code'] ?? '' ),
			false,
			__( '2-letter ISO code.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::text_field( 'region', __( 'Region', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['region'] ?? '' ) );
		AdminFormHelper::text_field( 'city', __( 'City', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['city'] ?? '' ) );
		AdminFormHelper::textarea_field( 'address_summary', __( 'Address summary', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['address_summary'] ?? '' ) );
		AdminFormHelper::number_field( 'dispatch_lead_days_min', __( 'Dispatch lead min days', 'cetech-woocommerce-delivery-engine' ), $record['dispatch_lead_days_min'] ?? null );
		AdminFormHelper::number_field( 'dispatch_lead_days_max', __( 'Dispatch lead max days', 'cetech-woocommerce-delivery-engine' ), $record['dispatch_lead_days_max'] ?? null );
		AdminFormHelper::textarea_field( 'internal_notes', __( 'Internal notes', 'cetech-woocommerce-delivery-engine' ), (string) ( $record['internal_notes'] ?? '' ), 4, __( 'Private admin notes only.', 'cetech-woocommerce-delivery-engine' ) );
		AdminFormHelper::select_field( 'status', __( 'Status', 'cetech-woocommerce-delivery-engine' ), $this->status_options(), (string) ( $record['status'] ?? RecordStatus::Active->value ) );
		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Update Origin', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Origin', 'cetech-woocommerce-delivery-engine' ) );
		echo ' <a class="button" href="' . esc_url( AdminPageRenderer::list_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		echo '</form>';
		AdminPageRenderer::close_wrap();
	}

	private function handle_save_supplier(): void {
		$input  = $this->read_supplier_input();
		$errors = $this->supplier_validator->validate( $input, isset( $input['id'] ) ? (int) $input['id'] : null );

		if ( [] !== $errors ) {
			$this->stash_supplier_draft( $input );
			$this->action_handler->notices()->flash_error( implode( ' ', $errors ) );
			$this->redirect_supplier_form( $input );
		}

		$code = AdminFormHelper::sanitize_code( (string) $input['code'] );
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$existing = $this->supplier_repository->findByCode( $code );

		if ( null !== $existing && (int) ( $existing['id'] ?? 0 ) !== $id ) {
			$this->stash_supplier_draft( $input );
			$this->action_handler->notices()->flash_error( __( 'A supplier with this code already exists.', 'cetech-woocommerce-delivery-engine' ) );
			$this->redirect_supplier_form( $input );
		}

		$previous = $id > 0 ? $this->supplier_repository->findById( $id ) : null;
		$payload  = [
			'id'             => $id,
			'internal_code'  => $code,
			'internal_name'  => trim( (string) $input['internal_name'] ),
			'contact_email'  => trim( (string) ( $input['contact_email'] ?? '' ) ),
			'contact_phone'  => trim( (string) ( $input['contact_phone'] ?? '' ) ),
			'internal_notes' => trim( (string) ( $input['internal_notes'] ?? '' ) ),
			'status'         => (string) $input['status'],
		];

		$saved_id = $this->supplier_repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to save supplier.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'supplier',
			$saved_id,
			$previous,
			$this->supplier_repository->findById( $saved_id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Supplier updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Supplier created.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			$this->action_handler->notices()->flash_warning(
				$id > 0
					? __( 'Supplier updated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Supplier created, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
			);
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate_supplier(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid supplier.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->supplier_repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Supplier not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->supplier_repository->deactivate( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate supplier.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Supplier is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'supplier',
			$id,
			$previous,
			$this->supplier_repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Supplier deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Supplier deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_save_origin(): void {
		$input  = $this->read_origin_input();
		$errors = $this->origin_validator->validate( $input, isset( $input['id'] ) ? (int) $input['id'] : null );

		if ( [] !== $errors ) {
			$this->stash_origin_draft( $input );
			$this->action_handler->notices()->flash_error( implode( ' ', $errors ) );
			$this->redirect_origin_form( $input );
		}

		$code = AdminFormHelper::sanitize_code( (string) $input['code'] );
		$id   = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$existing = $this->origin_repository->findByCode( $code );

		if ( null !== $existing && (int) ( $existing['id'] ?? 0 ) !== $id ) {
			$this->stash_origin_draft( $input );
			$this->action_handler->notices()->flash_error( __( 'An origin with this code already exists.', 'cetech-woocommerce-delivery-engine' ) );
			$this->redirect_origin_form( $input );
		}

		$previous = $id > 0 ? $this->origin_repository->findById( $id ) : null;
		$payload  = [
			'id'                     => $id,
			'supplier_id'            => (int) $input['supplier_id'],
			'internal_code'          => $code,
			'internal_name'          => trim( (string) $input['internal_name'] ),
			'internal_address'       => $this->origin_validator->encode_internal_address( $input ),
			'country_code'           => strtoupper( trim( (string) ( $input['country_code'] ?? '' ) ) ),
			'dispatch_lead_days_min' => $this->nullable_int( $input['dispatch_lead_days_min'] ?? null ),
			'dispatch_lead_days_max' => $this->nullable_int( $input['dispatch_lead_days_max'] ?? null ),
			'internal_notes'         => trim( (string) ( $input['internal_notes'] ?? '' ) ),
			'status'                 => (string) $input['status'],
		];

		$saved_id = $this->origin_repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to save origin.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'origin',
			$saved_id,
			$previous,
			$this->origin_repository->findById( $saved_id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Origin updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Origin created.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			$this->action_handler->notices()->flash_warning(
				$id > 0
					? __( 'Origin updated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Origin created, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
			);
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate_origin(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid origin.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->origin_repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Origin not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->origin_repository->deactivate( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate origin.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Origin is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'origin',
			$id,
			$previous,
			$this->origin_repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Origin deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Origin deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->redirect( self::SLUG );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_supplier_for_form( bool $is_edit ): array {
		if ( ! $is_edit ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( $id <= 0 ) {
			return [];
		}

		$row = $this->supplier_repository->findById( $id );

		if ( null === $row ) {
			return [];
		}

		return [
			'id'             => (int) ( $row['id'] ?? 0 ),
			'code'           => (string) ( $row['internal_code'] ?? '' ),
			'internal_name'  => (string) ( $row['internal_name'] ?? '' ),
			'contact_email'  => (string) ( $row['contact_email'] ?? '' ),
			'contact_phone'  => (string) ( $row['contact_phone'] ?? '' ),
			'internal_notes' => (string) ( $row['internal_notes'] ?? '' ),
			'status'         => (string) ( $row['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_origin_for_form( bool $is_edit ): array {
		if ( ! $is_edit ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( $id <= 0 ) {
			return [];
		}

		$row = $this->origin_repository->findById( $id );

		if ( null === $row ) {
			return [];
		}

		$address = $this->origin_validator->decode_internal_address(
			isset( $row['internal_address'] ) ? (string) $row['internal_address'] : null
		);

		return [
			'id'                     => (int) ( $row['id'] ?? 0 ),
			'code'                   => (string) ( $row['internal_code'] ?? '' ),
			'internal_name'          => (string) ( $row['internal_name'] ?? '' ),
			'supplier_id'            => (int) ( $row['supplier_id'] ?? 0 ),
			'country_code'           => (string) ( $row['country_code'] ?? '' ),
			'region'                 => $address['region'],
			'city'                   => $address['city'],
			'address_summary'        => $address['address_summary'],
			'dispatch_lead_days_min' => isset( $row['dispatch_lead_days_min'] ) ? (int) $row['dispatch_lead_days_min'] : null,
			'dispatch_lead_days_max' => isset( $row['dispatch_lead_days_max'] ) ? (int) $row['dispatch_lead_days_max'] : null,
			'internal_notes'         => (string) ( $row['internal_notes'] ?? '' ),
			'status'                 => (string) ( $row['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @param array<string, mixed> $draft
	 *
	 * @return array<string, mixed>
	 */
	private function supplier_from_draft( array $draft ): array {
		return [
			'id'             => isset( $draft['id'] ) ? (int) $draft['id'] : 0,
			'code'           => (string) ( $draft['code'] ?? '' ),
			'internal_name'  => (string) ( $draft['internal_name'] ?? '' ),
			'contact_email'  => (string) ( $draft['contact_email'] ?? '' ),
			'contact_phone'  => (string) ( $draft['contact_phone'] ?? '' ),
			'internal_notes' => (string) ( $draft['internal_notes'] ?? '' ),
			'status'         => (string) ( $draft['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @param array<string, mixed> $draft
	 *
	 * @return array<string, mixed>
	 */
	private function origin_from_draft( array $draft ): array {
		return [
			'id'                     => isset( $draft['id'] ) ? (int) $draft['id'] : 0,
			'code'                   => (string) ( $draft['code'] ?? '' ),
			'internal_name'          => (string) ( $draft['internal_name'] ?? '' ),
			'supplier_id'            => isset( $draft['supplier_id'] ) ? (int) $draft['supplier_id'] : 0,
			'country_code'           => (string) ( $draft['country_code'] ?? '' ),
			'region'                 => (string) ( $draft['region'] ?? '' ),
			'city'                   => (string) ( $draft['city'] ?? '' ),
			'address_summary'        => (string) ( $draft['address_summary'] ?? '' ),
			'dispatch_lead_days_min' => isset( $draft['dispatch_lead_days_min'] ) && '' !== $draft['dispatch_lead_days_min'] ? (int) $draft['dispatch_lead_days_min'] : null,
			'dispatch_lead_days_max' => isset( $draft['dispatch_lead_days_max'] ) && '' !== $draft['dispatch_lead_days_max'] ? (int) $draft['dispatch_lead_days_max'] : null,
			'internal_notes'         => (string) ( $draft['internal_notes'] ?? '' ),
			'status'                 => (string) ( $draft['status'] ?? RecordStatus::Active->value ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_supplier_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return [
			'id'             => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'code'           => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '',
			'internal_name'  => isset( $_POST['internal_name'] ) ? wp_unslash( (string) $_POST['internal_name'] ) : '',
			'contact_email'  => isset( $_POST['contact_email'] ) ? wp_unslash( (string) $_POST['contact_email'] ) : '',
			'contact_phone'  => isset( $_POST['contact_phone'] ) ? wp_unslash( (string) $_POST['contact_phone'] ) : '',
			'internal_notes' => isset( $_POST['internal_notes'] ) ? wp_unslash( (string) $_POST['internal_notes'] ) : '',
			'status'         => isset( $_POST['status'] ) ? wp_unslash( (string) $_POST['status'] ) : '',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_origin_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return [
			'id'                     => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'code'                   => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '',
			'internal_name'          => isset( $_POST['internal_name'] ) ? wp_unslash( (string) $_POST['internal_name'] ) : '',
			'supplier_id'            => isset( $_POST['supplier_id'] ) ? (int) $_POST['supplier_id'] : 0,
			'country_code'           => isset( $_POST['country_code'] ) ? wp_unslash( (string) $_POST['country_code'] ) : '',
			'region'                 => isset( $_POST['region'] ) ? wp_unslash( (string) $_POST['region'] ) : '',
			'city'                   => isset( $_POST['city'] ) ? wp_unslash( (string) $_POST['city'] ) : '',
			'address_summary'        => isset( $_POST['address_summary'] ) ? wp_unslash( (string) $_POST['address_summary'] ) : '',
			'dispatch_lead_days_min' => $_POST['dispatch_lead_days_min'] ?? '',
			'dispatch_lead_days_max' => $_POST['dispatch_lead_days_max'] ?? '',
			'internal_notes'         => isset( $_POST['internal_notes'] ) ? wp_unslash( (string) $_POST['internal_notes'] ) : '',
			'status'                 => isset( $_POST['status'] ) ? wp_unslash( (string) $_POST['status'] ) : '',
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function redirect_supplier_form( array $input ): never {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$this->action_handler->redirect(
			self::SLUG,
			$id > 0
				? [ 'entity' => 'supplier', 'action' => 'edit', 'id' => $id ]
				: [ 'entity' => 'supplier', 'action' => 'add' ]
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function redirect_origin_form( array $input ): never {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$this->action_handler->redirect(
			self::SLUG,
			$id > 0
				? [ 'entity' => 'origin', 'action' => 'edit', 'id' => $id ]
				: [ 'entity' => 'origin', 'action' => 'add' ]
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function stash_supplier_draft( array $input ): void {
		$this->action_handler->notices()->stash_form_draft( $this->draft_key( self::DRAFT_SUPPLIER ), $input );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function stash_origin_draft( array $input ): void {
		$this->action_handler->notices()->stash_form_draft( $this->draft_key( self::DRAFT_ORIGIN ), $input );
	}

	private function draft_key( string $entity ): string {
		return self::SLUG . '_' . $entity;
	}

	private function form_url( string $entity, string $action, int $id = 0 ): string {
		$args = [
			'page'   => self::SLUG,
			'entity' => $entity,
			'action' => $action,
		];

		if ( $id > 0 ) {
			$args['id'] = $id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private function render_supplier_actions( int $id ): string {
		$edit = '<a href="' . esc_url( $this->form_url( 'supplier', 'edit', $id ) ) . '">' . esc_html__( 'Edit', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		$deactivate = $this->deactivate_form( self::ACTION_DEACTIVATE_SUPPLIER, $id, __( 'Deactivate this supplier?', 'cetech-woocommerce-delivery-engine' ), __( 'Deactivate', 'cetech-woocommerce-delivery-engine' ) );

		return $edit . $deactivate;
	}

	private function render_origin_actions( int $id ): string {
		$edit = '<a href="' . esc_url( $this->form_url( 'origin', 'edit', $id ) ) . '">' . esc_html__( 'Edit', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		$deactivate = $this->deactivate_form( self::ACTION_DEACTIVATE_ORIGIN, $id, __( 'Deactivate this origin?', 'cetech-woocommerce-delivery-engine' ), __( 'Deactivate', 'cetech-woocommerce-delivery-engine' ) );

		return $edit . $deactivate;
	}

	private function deactivate_form( string $action, int $id, string $confirm, string $label ): string {
		$form = '<form method="post" style="display:inline;margin-left:8px;">';
		$form .= wp_nonce_field( $action, 'cetech_de_nonce', true, false );
		$form .= '<input type="hidden" name="cetech_de_action" value="' . esc_attr( $action ) . '" />';
		$form .= '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		$form .= '<button type="submit" class="button-link delete" onclick="return confirm(\'' . esc_js( $confirm ) . '\');">';
		$form .= esc_html( $label );
		$form .= '</button></form>';

		return $form;
	}

	/**
	 * @return array<int, string>
	 */
	private function supplier_name_map(): array {
		$map = [];

		foreach ( $this->supplier_repository->list( [ 'limit' => 500 ] ) as $supplier ) {
			$id = (int) ( $supplier['id'] ?? 0 );

			if ( $id > 0 ) {
				$map[ $id ] = (string) ( $supplier['internal_name'] ?? '' );
			}
		}

		return $map;
	}

	/**
	 * @return array<string, string>
	 */
	private function supplier_options(): array {
		$options = [ '' => __( '— Select supplier —', 'cetech-woocommerce-delivery-engine' ) ];

		foreach ( $this->supplier_repository->list( [ 'limit' => 500 ] ) as $supplier ) {
			$id = (int) ( $supplier['id'] ?? 0 );

			if ( $id > 0 ) {
				$options[ (string) $id ] = sprintf(
					'%s (%s)',
					(string) ( $supplier['internal_name'] ?? '' ),
					(string) ( $supplier['internal_code'] ?? '' )
				);
			}
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

	private function nullable_int( mixed $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return max( 0, (int) $value );
	}
}

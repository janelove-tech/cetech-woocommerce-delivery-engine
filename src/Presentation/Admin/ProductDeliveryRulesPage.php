<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Admin;

use CetechDeliveryEngine\Domain\DeliveryOffer\DeliveryOfferRepositoryInterface;
use CetechDeliveryEngine\Domain\Enum\FulfilmentAvailability;
use CetechDeliveryEngine\Domain\Enum\FulfilmentChoice;
use CetechDeliveryEngine\Domain\Enum\ProductTargetType;
use CetechDeliveryEngine\Domain\Enum\RecordStatus;
use CetechDeliveryEngine\Domain\LogisticsProfile\LogisticsProfileRepositoryInterface;
use CetechDeliveryEngine\Domain\ProductRule\ProductDeliveryRuleRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\OriginRepositoryInterface;
use CetechDeliveryEngine\Domain\Supplier\SupplierRepositoryInterface;
use CetechDeliveryEngine\Infrastructure\Persistence\WpdbProductDeliveryRuleRepository;
use CetechDeliveryEngine\Presentation\Admin\Validation\ProductDeliveryRuleValidator;

/**
 * Admin-only product delivery rule configuration.
 *
 * Not exposed to storefront, cart, checkout, or WooCommerce product edit screens.
 */
final class ProductDeliveryRulesPage {

	public const SLUG = 'cetech-delivery-engine-product-rules';

	private const ACTION_SAVE = 'cetech_de_save_product_delivery_rule';

	private const ACTION_DEACTIVATE = 'cetech_de_deactivate_product_delivery_rule';

	public function __construct(
		private ProductDeliveryRuleRepositoryInterface $repository,
		private DeliveryOfferRepositoryInterface $delivery_offer_repository,
		private LogisticsProfileRepositoryInterface $logistics_profile_repository,
		private SupplierRepositoryInterface $supplier_repository,
		private OriginRepositoryInterface $origin_repository,
		private ProductDeliveryRuleValidator $validator,
		private AdminActionHandler $action_handler,
		private ConfigurationAuditLogger $audit_logger
	) {
	}

	public function handle_actions(): void {
		if ( $this->action_handler->verify_post( self::ACTION_SAVE, self::ACTION_SAVE, 'manage_product_delivery_rules', self::SLUG ) ) {
			$this->handle_save();
		}

		if ( $this->action_handler->verify_post( self::ACTION_DEACTIVATE, self::ACTION_DEACTIVATE, 'manage_product_delivery_rules', self::SLUG ) ) {
			$this->handle_deactivate();
		}
	}

	public function render(): void {
		AdminPageAccess::require_capability( 'manage_product_delivery_rules' );

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
		AdminPageRenderer::open_wrap( __( 'Product Rules', 'cetech-woocommerce-delivery-engine' ) );
		AdminPageRenderer::add_new_button( self::SLUG, __( 'Add New', 'cetech-woocommerce-delivery-engine' ) );

		$lookups = $this->build_lookups();
		$records = $this->repository->list( [ 'limit' => 500 ] );
		$rows    = [];

		foreach ( $records as $record ) {
			$id = (int) ( $record['id'] ?? 0 );
			$rows[] = [
				(string) $id,
				esc_html( (string) ( $record['target_type'] ?? '' ) ),
				esc_html( (string) ( $record['target_id'] ?? '' ) ),
				esc_html( (string) ( $record['target_label_snapshot'] ?? '—' ) ),
				esc_html( (string) ( $record['fulfilment_availability'] ?? '' ) ),
				esc_html( (string) ( $record['fulfilment_choice'] ?? '' ) ),
				esc_html( $this->format_offer_ids( $lookups['offers'], $record['delivery_offer_ids'] ?? null ) ),
				esc_html( $this->lookup_optional( $lookups['profiles'], $record['logistics_profile_id'] ?? null ) ),
				esc_html( $this->lookup_optional( $lookups['suppliers'], $record['supplier_id'] ?? null ) ),
				esc_html( $this->lookup_optional( $lookups['origins'], $record['origin_id'] ?? null ) ),
				esc_html( (string) ( $record['priority'] ?? '' ) ),
				esc_html( (string) ( $record['status'] ?? '' ) ),
				esc_html( (string) ( $record['updated_at'] ?? '' ) ),
				$this->render_actions( $id ),
			];
		}

		AdminPageRenderer::render_table(
			[
				__( 'ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Target type', 'cetech-woocommerce-delivery-engine' ),
				__( 'Target ID', 'cetech-woocommerce-delivery-engine' ),
				__( 'Target label', 'cetech-woocommerce-delivery-engine' ),
				__( 'Fulfilment availability', 'cetech-woocommerce-delivery-engine' ),
				__( 'Fulfilment choice', 'cetech-woocommerce-delivery-engine' ),
				__( 'Delivery offers', 'cetech-woocommerce-delivery-engine' ),
				__( 'Logistics profile', 'cetech-woocommerce-delivery-engine' ),
				__( 'Supplier', 'cetech-woocommerce-delivery-engine' ),
				__( 'Origin', 'cetech-woocommerce-delivery-engine' ),
				__( 'Priority', 'cetech-woocommerce-delivery-engine' ),
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
			? __( 'Edit Product Rule', 'cetech-woocommerce-delivery-engine' )
			: __( 'Add Product Rule', 'cetech-woocommerce-delivery-engine' );

		AdminPageRenderer::open_wrap( $title );

		echo '<form method="post" action="">';
		AdminFormHelper::nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';

		if ( $is_edit && ! empty( $record['id'] ) ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $record['id'] ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		AdminFormHelper::select_field(
			'target_type',
			__( 'Target type', 'cetech-woocommerce-delivery-engine' ),
			$this->target_type_options(),
			(string) ( $record['target_type'] ?? ProductTargetType::Product->value )
		);
		AdminFormHelper::number_field(
			'target_id',
			__( 'Target ID', 'cetech-woocommerce-delivery-engine' ),
			isset( $record['target_id'] ) ? (int) $record['target_id'] : null,
			1,
			__( 'WooCommerce product, variation, or product category term ID.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::text_field(
			'target_label_snapshot',
			__( 'Target label snapshot', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['target_label_snapshot'] ?? '' ),
			false,
			__( 'Optional. Auto-filled from WooCommerce when left blank on save.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::select_field(
			'fulfilment_availability',
			__( 'Fulfilment availability', 'cetech-woocommerce-delivery-engine' ),
			$this->availability_options(),
			(string) ( $record['fulfilment_availability'] ?? FulfilmentAvailability::InStore->value )
		);
		AdminFormHelper::select_field(
			'fulfilment_choice',
			__( 'Fulfilment choice', 'cetech-woocommerce-delivery-engine' ),
			$this->choice_options(),
			(string) ( $record['fulfilment_choice'] ?? FulfilmentChoice::Delivery->value )
		);
		AdminFormHelper::checkbox_group_field(
			'delivery_offer_ids',
			__( 'Delivery offers', 'cetech-woocommerce-delivery-engine' ),
			$this->delivery_offer_options(),
			(array) ( $record['delivery_offer_ids'] ?? [] ),
			__( 'Required for delivery fulfilment. Leave empty when fulfilment choice is store pickup.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::select_field(
			'logistics_profile_id',
			__( 'Logistics profile', 'cetech-woocommerce-delivery-engine' ),
			$this->optional_select_options( $this->logistics_profile_options() ),
			(string) ( $record['logistics_profile_id'] ?? '' ),
			__( 'Optional.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::select_field(
			'supplier_id',
			__( 'Supplier', 'cetech-woocommerce-delivery-engine' ),
			$this->optional_select_options( $this->supplier_options() ),
			(string) ( $record['supplier_id'] ?? '' ),
			__( 'Optional.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::select_field(
			'origin_id',
			__( 'Origin', 'cetech-woocommerce-delivery-engine' ),
			$this->optional_select_options( $this->origin_options() ),
			(string) ( $record['origin_id'] ?? '' ),
			__( 'Optional. Must belong to selected supplier when both are set.', 'cetech-woocommerce-delivery-engine' )
		);
		AdminFormHelper::number_field(
			'priority',
			__( 'Priority', 'cetech-woocommerce-delivery-engine' ),
			isset( $record['priority'] ) ? (int) $record['priority'] : 100
		);
		AdminFormHelper::select_field(
			'status',
			__( 'Status', 'cetech-woocommerce-delivery-engine' ),
			$this->status_options(),
			(string) ( $record['status'] ?? RecordStatus::Active->value )
		);
		AdminFormHelper::textarea_field(
			'internal_notes',
			__( 'Internal notes', 'cetech-woocommerce-delivery-engine' ),
			(string) ( $record['internal_notes'] ?? '' ),
			4,
			__( 'Private admin-only notes. Not shown to customers.', 'cetech-woocommerce-delivery-engine' )
		);
		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Update Product Rule', 'cetech-woocommerce-delivery-engine' ) : __( 'Create Product Rule', 'cetech-woocommerce-delivery-engine' ) );
		echo ' <a class="button" href="' . esc_url( AdminPageRenderer::list_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'cetech-woocommerce-delivery-engine' ) . '</a>';
		echo '</form>';
		AdminPageRenderer::close_wrap();
	}

	private function handle_save(): void {
		$input  = $this->read_form_input();
		$errors = $this->validator->validate( $input, isset( $input['id'] ) ? (int) $input['id'] : null );

		if ( [] !== $errors ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( implode( ' ', array_values( $errors ) ) );
			$this->redirect_to_form( $input );
		}

		$id       = isset( $input['id'] ) ? (int) $input['id'] : 0;
		$previous = $id > 0 ? $this->repository->findById( $id ) : null;

		$payload = [
			'id'                      => $id,
			'target_type'             => sanitize_key( (string) $input['target_type'] ),
			'target_id'               => (int) $input['target_id'],
			'target_label_snapshot'   => $this->validator->resolve_target_label_snapshot( $input ),
			'fulfilment_availability' => (string) $input['fulfilment_availability'],
			'fulfilment_choice'       => (string) $input['fulfilment_choice'],
			'delivery_offer_ids'      => $this->validator->normalize_offer_ids( $input['delivery_offer_ids'] ?? [] ),
			'logistics_profile_id'    => $this->nullable_int( $input['logistics_profile_id'] ?? null ),
			'supplier_id'             => $this->nullable_int( $input['supplier_id'] ?? null ),
			'origin_id'               => $this->nullable_int( $input['origin_id'] ?? null ),
			'priority'                => (int) $input['priority'],
			'status'                  => (string) $input['status'],
			'internal_notes'          => trim( (string) ( $input['internal_notes'] ?? '' ) ),
		];

		$saved_id = $this->repository->save( $payload );

		if ( $saved_id <= 0 ) {
			$this->action_handler->notices()->stash_form_draft( self::SLUG, $input );
			$this->action_handler->notices()->flash_error( __( 'Unable to save product rule.', 'cetech-woocommerce-delivery-engine' ) );
			$this->redirect_to_form( $input );
		}

		$audit_logged = $this->audit_logger->log(
			$id > 0 ? 'updated' : 'created',
			'product_delivery_rule',
			$saved_id,
			$previous,
			$this->repository->findById( $saved_id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success(
				$id > 0
					? __( 'Product rule updated.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Product rule created.', 'cetech-woocommerce-delivery-engine' )
			);
		} else {
			$this->action_handler->notices()->flash_warning(
				$id > 0
					? __( 'Product rule updated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
					: __( 'Product rule created, but audit logging failed.', 'cetech-woocommerce-delivery-engine' )
			);
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function handle_deactivate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->action_handler->notices()->flash_error( __( 'Invalid product rule.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$previous = $this->repository->findById( $id );

		if ( null === $previous ) {
			$this->action_handler->notices()->flash_error( __( 'Product rule not found.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$was_already_inactive = RecordStatus::Inactive->value === (string) ( $previous['status'] ?? '' );

		if ( ! $this->repository->deactivate( $id ) ) {
			$this->action_handler->notices()->flash_error( __( 'Unable to deactivate product rule.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		if ( $was_already_inactive ) {
			$this->action_handler->notices()->flash_success( __( 'Product rule is already inactive.', 'cetech-woocommerce-delivery-engine' ) );
			$this->action_handler->redirect( self::SLUG );
		}

		$audit_logged = $this->audit_logger->log(
			'deactivated',
			'product_delivery_rule',
			$id,
			$previous,
			$this->repository->findById( $id )
		);

		if ( $audit_logged ) {
			$this->action_handler->notices()->flash_success( __( 'Product rule deactivated.', 'cetech-woocommerce-delivery-engine' ) );
		} else {
			$this->action_handler->notices()->flash_warning( __( 'Product rule deactivated, but audit logging failed.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$this->action_handler->redirect( self::SLUG );
	}

	private function render_actions( int $id ): string {
		$edit_url = AdminPageRenderer::edit_url( self::SLUG, $id );

		ob_start();
		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'cetech-woocommerce-delivery-engine' ) . '</a> | ';
		echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'' . esc_js( __( 'Deactivate this product rule?', 'cetech-woocommerce-delivery-engine' ) ) . '\');">';
		AdminFormHelper::nonce_field( self::ACTION_DEACTIVATE );
		echo '<input type="hidden" name="cetech_de_action" value="' . esc_attr( self::ACTION_DEACTIVATE ) . '" />';
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
		submit_button( __( 'Deactivate', 'cetech-woocommerce-delivery-engine' ), 'link-delete', 'submit', false );
		echo '</form>';

		return (string) ob_get_clean();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function load_record_for_form( bool $is_edit ): ?array {
		if ( ! $is_edit ) {
			return [
				'target_type'             => ProductTargetType::Product->value,
				'fulfilment_availability' => FulfilmentAvailability::InStore->value,
				'fulfilment_choice'       => FulfilmentChoice::Delivery->value,
				'priority'                => 100,
				'status'                  => RecordStatus::Active->value,
				'delivery_offer_ids'      => [],
			];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( $id <= 0 ) {
			wp_die( esc_html__( 'Invalid edit request.', 'cetech-woocommerce-delivery-engine' ) );
		}

		$row = $this->repository->findById( $id );

		if ( null === $row ) {
			wp_die( esc_html__( 'Product rule not found.', 'cetech-woocommerce-delivery-engine' ) );
		}

		return $this->map_row_to_form( $row );
	}

	/**
	 * @param array<string, mixed> $draft
	 *
	 * @return array<string, mixed>
	 */
	private function form_record_from_draft( array $draft ): array {
		$offer_ids = $this->validator->normalize_offer_ids( $draft['delivery_offer_ids'] ?? [] );

		return [
			'id'                      => isset( $draft['id'] ) ? (int) $draft['id'] : 0,
			'target_type'             => (string) ( $draft['target_type'] ?? '' ),
			'target_id'               => isset( $draft['target_id'] ) ? (int) $draft['target_id'] : 0,
			'target_label_snapshot'   => (string) ( $draft['target_label_snapshot'] ?? '' ),
			'fulfilment_availability' => (string) ( $draft['fulfilment_availability'] ?? '' ),
			'fulfilment_choice'       => (string) ( $draft['fulfilment_choice'] ?? '' ),
			'delivery_offer_ids'      => array_map( 'strval', $offer_ids ),
			'logistics_profile_id'    => (string) ( $draft['logistics_profile_id'] ?? '' ),
			'supplier_id'             => (string) ( $draft['supplier_id'] ?? '' ),
			'origin_id'               => (string) ( $draft['origin_id'] ?? '' ),
			'priority'                => isset( $draft['priority'] ) ? (int) $draft['priority'] : 100,
			'status'                  => (string) ( $draft['status'] ?? RecordStatus::Active->value ),
			'internal_notes'          => (string) ( $draft['internal_notes'] ?? '' ),
		];
	}

	/**
	 * @param array<string, mixed> $row
	 *
	 * @return array<string, mixed>
	 */
	private function map_row_to_form( array $row ): array {
		$offer_ids = $this->decode_offer_ids( $row['delivery_offer_ids'] ?? null );

		return [
			'id'                      => (int) ( $row['id'] ?? 0 ),
			'target_type'             => (string) ( $row['target_type'] ?? '' ),
			'target_id'               => (int) ( $row['target_id'] ?? 0 ),
			'target_label_snapshot'   => (string) ( $row['target_label_snapshot'] ?? '' ),
			'fulfilment_availability' => (string) ( $row['fulfilment_availability'] ?? '' ),
			'fulfilment_choice'       => (string) ( $row['fulfilment_choice'] ?? '' ),
			'delivery_offer_ids'      => array_map( 'strval', $offer_ids ),
			'logistics_profile_id'    => (string) ( $row['logistics_profile_id'] ?? '' ),
			'supplier_id'             => (string) ( $row['supplier_id'] ?? '' ),
			'origin_id'               => (string) ( $row['origin_id'] ?? '' ),
			'priority'                => (int) ( $row['priority'] ?? 100 ),
			'status'                  => (string) ( $row['status'] ?? RecordStatus::Active->value ),
			'internal_notes'          => (string) ( $row['internal_notes'] ?? '' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_form_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$offer_ids = isset( $_POST['delivery_offer_ids'] ) && is_array( $_POST['delivery_offer_ids'] )
			? array_map( 'intval', wp_unslash( $_POST['delivery_offer_ids'] ) )
			: [];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return [
			'id'                      => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'target_type'             => isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['target_type'] ) ) : '',
			'target_id'               => isset( $_POST['target_id'] ) ? (int) $_POST['target_id'] : 0,
			'target_label_snapshot'   => isset( $_POST['target_label_snapshot'] ) ? wp_unslash( (string) $_POST['target_label_snapshot'] ) : '',
			'fulfilment_availability' => isset( $_POST['fulfilment_availability'] ) ? sanitize_key( wp_unslash( (string) $_POST['fulfilment_availability'] ) ) : '',
			'fulfilment_choice'       => isset( $_POST['fulfilment_choice'] ) ? sanitize_key( wp_unslash( (string) $_POST['fulfilment_choice'] ) ) : '',
			'delivery_offer_ids'      => $offer_ids,
			'logistics_profile_id'    => isset( $_POST['logistics_profile_id'] ) ? (int) $_POST['logistics_profile_id'] : 0,
			'supplier_id'             => isset( $_POST['supplier_id'] ) ? (int) $_POST['supplier_id'] : 0,
			'origin_id'               => isset( $_POST['origin_id'] ) ? (int) $_POST['origin_id'] : 0,
			'priority'                => isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 100,
			'status'                  => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '',
			'internal_notes'          => isset( $_POST['internal_notes'] ) ? wp_unslash( (string) $_POST['internal_notes'] ) : '',
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function redirect_to_form( array $input ): void {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;

		if ( $id > 0 ) {
			$this->action_handler->redirect(
				AdminPageRenderer::edit_url( self::SLUG, $id )
			);
		}

		$this->action_handler->redirect(
			add_query_arg( 'action', 'add', AdminPageRenderer::list_url( self::SLUG ) )
		);
	}

	/**
	 * @return array{
	 *     offers: array<int, array<string, mixed>>,
	 *     profiles: array<int, array<string, mixed>>,
	 *     suppliers: array<int, array<string, mixed>>,
	 *     origins: array<int, array<string, mixed>>
	 * }
	 */
	private function build_lookups(): array {
		return [
			'offers'    => $this->index_by_id( $this->delivery_offer_repository->list( [ 'limit' => 500 ] ) ),
			'profiles'  => $this->index_by_id( $this->logistics_profile_repository->list( [ 'limit' => 500 ] ) ),
			'suppliers' => $this->index_by_id( $this->supplier_repository->list( [ 'limit' => 500 ] ) ),
			'origins'   => $this->index_by_id( $this->origin_repository->list( [ 'limit' => 500 ] ) ),
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $entities
	 */
	private function lookup_optional( array $entities, mixed $id ): string {
		if ( null === $id || '' === $id ) {
			return '—';
		}

		$int = (int) $id;

		if ( $int <= 0 || ! isset( $entities[ $int ] ) ) {
			return sprintf( '#%d', $int );
		}

		$row = $entities[ $int ];

		return sprintf(
			'%s (%s)',
			(string) ( $row['internal_code'] ?? '' ),
			(string) ( $row['internal_name'] ?? $row['public_label'] ?? '' )
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $offers
	 */
	private function format_offer_ids( array $offers, mixed $stored ): string {
		$ids = $this->decode_offer_ids( $stored );

		if ( [] === $ids ) {
			return '—';
		}

		$labels = [];

		foreach ( $ids as $id ) {
			if ( isset( $offers[ $id ] ) ) {
				$labels[] = (string) ( $offers[ $id ]['internal_code'] ?? (string) $id );
			} else {
				$labels[] = '#' . (string) $id;
			}
		}

		return implode( ', ', $labels );
	}

	/**
	 * @return list<int>
	 */
	private function decode_offer_ids( mixed $stored ): array {
		if ( $this->repository instanceof WpdbProductDeliveryRuleRepository ) {
			return $this->repository->decode_offer_ids( $stored );
		}

		if ( null === $stored || '' === $stored ) {
			return [];
		}

		$decoded = json_decode( (string) $stored, true );

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$ids = [];

		foreach ( $decoded as $value ) {
			$int = (int) $value;

			if ( $int > 0 ) {
				$ids[] = $int;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function index_by_id( array $rows ): array {
		$indexed = [];

		foreach ( $rows as $row ) {
			$id = (int) ( $row['id'] ?? 0 );

			if ( $id > 0 ) {
				$indexed[ $id ] = $row;
			}
		}

		return $indexed;
	}

	/**
	 * @return array<string, string>
	 */
	private function target_type_options(): array {
		$options = [];

		foreach ( ProductTargetType::cases() as $case ) {
			$options[ $case->value ] = $case->value;
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function availability_options(): array {
		$options = [];

		foreach ( FulfilmentAvailability::cases() as $case ) {
			$options[ $case->value ] = $case->value;
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function choice_options(): array {
		$options = [];

		foreach ( FulfilmentChoice::cases() as $case ) {
			$options[ $case->value ] = $case->value;
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function status_options(): array {
		return [
			RecordStatus::Active->value   => RecordStatus::Active->value,
			RecordStatus::Inactive->value => RecordStatus::Inactive->value,
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function delivery_offer_options(): array {
		$options = [];

		foreach ( $this->delivery_offer_repository->list( [ 'limit' => 500 ] ) as $row ) {
			$id = (int) ( $row['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$options[ (string) $id ] = sprintf(
				'%s (%s)',
				(string) ( $row['internal_code'] ?? '' ),
				(string) ( $row['public_label'] ?? '' )
			);
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function logistics_profile_options(): array {
		$options = [];

		foreach ( $this->logistics_profile_repository->list( [ 'limit' => 500 ] ) as $row ) {
			$id = (int) ( $row['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$options[ (string) $id ] = sprintf(
				'%s (%s)',
				(string) ( $row['internal_code'] ?? '' ),
				(string) ( $row['internal_name'] ?? '' )
			);
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function supplier_options(): array {
		$options = [];

		foreach ( $this->supplier_repository->list( [ 'limit' => 500 ] ) as $row ) {
			$id = (int) ( $row['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$options[ (string) $id ] = sprintf(
				'%s (%s)',
				(string) ( $row['internal_code'] ?? '' ),
				(string) ( $row['internal_name'] ?? '' )
			);
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function origin_options(): array {
		$options = [];

		foreach ( $this->origin_repository->list( [ 'limit' => 500 ] ) as $row ) {
			$id = (int) ( $row['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$options[ (string) $id ] = sprintf(
				'%s (%s)',
				(string) ( $row['internal_code'] ?? '' ),
				(string) ( $row['internal_name'] ?? '' )
			);
		}

		return $options;
	}

	/**
	 * @param array<string, string> $options
	 *
	 * @return array<string, string>
	 */
	private function optional_select_options( array $options ): array {
		return array_merge(
			[ '' => __( '— None —', 'cetech-woocommerce-delivery-engine' ) ],
			$options
		);
	}

	private function nullable_int( mixed $value ): ?int {
		if ( null === $value || '' === $value || 0 === (int) $value ) {
			return null;
		}

		$int = (int) $value;

		return $int > 0 ? $int : null;
	}
}

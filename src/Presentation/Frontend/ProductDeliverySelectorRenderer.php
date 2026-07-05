<?php

declare(strict_types=1);

namespace CetechDeliveryEngine\Presentation\Frontend;

use CetechDeliveryEngine\Application\ProductRule\ProductDeliveryRuleResolver;
use CetechDeliveryEngine\Application\Selector\ProductDeliveryOption;
use CetechDeliveryEngine\Application\Selector\ProductDeliveryOptionsBuilder;
use CetechDeliveryEngine\Bootstrap\FeatureFlags;
use CetechDeliveryEngine\Core\Requirements;
use CetechDeliveryEngine\Domain\Enum\ProductTargetType;
use WC_Product;

/**
 * Display-only product-page delivery selector (feature-flagged, off by default).
 *
 * Does not write cart data, checkout fields, order meta, or calculate shipping prices.
 */
final class ProductDeliverySelectorRenderer {

	public function __construct(
		private FeatureFlags $feature_flags,
		private Requirements $requirements,
		private ProductDeliveryRuleResolver $rule_resolver,
		private ProductDeliveryOptionsBuilder $options_builder
	) {
	}

	public function register(): void {
		if ( ! $this->feature_flags->is_enabled( 'enable_product_delivery_selector' ) ) {
			return;
		}

		if ( ! $this->requirements->is_woocommerce_active() ) {
			return;
		}

		add_action( 'woocommerce_single_product_summary', [ $this, 'render' ], 25 );
	}

	public function render(): void {
		if ( ! $this->feature_flags->is_enabled( 'enable_product_delivery_selector' ) ) {
			return;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = $this->resolve_product();

		if ( null === $product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			$this->render_notice(
				__(
					'Delivery options may update after selecting a product option.',
					'cetech-woocommerce-delivery-engine'
				)
			);

			return;
		}

		if ( ! $product->is_type( 'simple' ) && ! $product->is_type( 'variation' ) ) {
			return;
		}

		$target_type = $product->is_type( 'variation' )
			? ProductTargetType::Variation->value
			: ProductTargetType::Product->value;
		$target_id = (int) $product->get_id();

		$result = $this->rule_resolver->resolve( $target_type, $target_id );

		if ( ! $result->success ) {
			return;
		}

		$options = $this->options_builder->buildFromResolution( $result );

		if ( [] === $options ) {
			$this->render_notice(
				__(
					'Delivery options are not available for this product.',
					'cetech-woocommerce-delivery-engine'
				)
			);

			return;
		}

		$this->render_options( $options );
	}

	private function resolve_product(): ?WC_Product {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$post_id = get_the_ID();
		$loaded  = is_int( $post_id ) ? wc_get_product( $post_id ) : false;

		return $loaded instanceof WC_Product ? $loaded : null;
	}

	/**
	 * @param list<ProductDeliveryOption> $options
	 */
	private function render_options( array $options ): void {
		$groups = [];

		foreach ( $options as $option ) {
			$group_key = $option->fulfilment_availability . ':' . $option->fulfilment_choice;
			$groups[ $group_key ]   = $groups[ $group_key ] ?? [
				'availability_label' => $option->fulfilment_availability_label,
				'choice_label'       => $option->fulfilment_choice_label,
				'options'            => [],
			];
			$groups[ $group_key ]['options'][] = $option;
		}

		echo '<div class="cetech-de-product-delivery-selector">';
		echo '<h3 class="cetech-de-delivery-selector__title">' . esc_html__( 'Delivery options', 'cetech-woocommerce-delivery-engine' ) . '</h3>';

		foreach ( $groups as $group ) {
			echo '<div class="cetech-de-delivery-availability">';
			echo '<h4 class="cetech-de-delivery-availability__heading">' . esc_html( $group['availability_label'] ) . '</h4>';
			echo '<p class="cetech-de-delivery-availability__choice"><em>' . esc_html( $group['choice_label'] ) . '</em></p>';
			echo '<ul class="cetech-de-delivery-options">';

			foreach ( $group['options'] as $option ) {
				$this->render_option( $option );
			}

			echo '</ul>';
			echo '</div>';
		}

		echo '</div>';
	}

	private function render_option( ProductDeliveryOption $option ): void {
		$label = $option->delivery_offer_public_label ?? '';

		if ( '' === $label ) {
			return;
		}

		$class = $option->is_available
			? 'cetech-de-delivery-option'
			: 'cetech-de-delivery-option cetech-de-delivery-option--unavailable';

		echo '<li class="' . esc_attr( $class ) . '">';
		echo '<span class="cetech-de-delivery-option__label">' . esc_html( $label ) . '</span>';

		if ( null !== $option->delivery_offer_public_description && '' !== $option->delivery_offer_public_description ) {
			echo '<span class="cetech-de-delivery-option__description"> ' . esc_html( $option->delivery_offer_public_description ) . '</span>';
		}

		if ( null !== $option->estimate_text && '' !== $option->estimate_text ) {
			echo '<span class="cetech-de-delivery-option__estimate"> ' . esc_html( $option->estimate_text ) . '</span>';
		}

		if ( ! $option->is_available && null !== $option->unavailable_reason && '' !== $option->unavailable_reason ) {
			echo '<span class="cetech-de-delivery-option__unavailable-reason"> ' . esc_html( $option->unavailable_reason ) . '</span>';
		}

		echo '</li>';
	}

	private function render_notice( string $message ): void {
		echo '<div class="cetech-de-product-delivery-selector">';
		echo '<h3 class="cetech-de-delivery-selector__title">' . esc_html__( 'Delivery options', 'cetech-woocommerce-delivery-engine' ) . '</h3>';
		echo '<p class="cetech-de-delivery-selector__notice">' . esc_html( $message ) . '</p>';
		echo '</div>';
	}
}

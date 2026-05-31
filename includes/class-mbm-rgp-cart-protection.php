<?php
/**
 * Cart, checkout, Store API, and direct product protection.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Cart_Protection {
	private $settings;
	private $access;

	public function __construct( MBM_RGP_Settings $settings, MBM_RGP_Access $access ) {
		$this->settings = $settings;
		$this->access   = $access;
	}

	public function hooks() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'block_classic_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( $this, 'block_store_api_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'purge_restricted_cart_items' ) );
		add_action( 'template_redirect', array( $this, 'block_single_product' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'block_store_api_product_read' ), 10, 3 );
	}

	public function block_classic_add_to_cart( $passed, $product_id ) {
		if ( $this->access->can_view_product( absint( $product_id ) ) ) {
			return $passed;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $this->get_add_to_cart_message(), 'error' );
		}

		return false;
	}

	public function block_store_api_add_to_cart( $product, $request ) {
		unset( $request );

		if ( ! $product || $this->access->can_view_product( $this->get_product_gate_id( $product ) ) ) {
			return;
		}

		$message = $this->get_add_to_cart_message();
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'mbm_rgp_product_restricted',
				esc_html( $message ),
				403
			);
		}

		throw new Exception( esc_html( $message ), 403 );
	}

	public function purge_restricted_cart_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$removed = false;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			if ( $product_id && ! $this->access->can_view_product( $product_id ) ) {
				WC()->cart->remove_cart_item( $key );
				$removed = true;
			}
		}

		if ( $removed && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $this->get_cart_removed_message(), 'error' );
		}
	}

	public function block_single_product() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = get_queried_object_id();
		if ( $this->access->can_view_product( $product_id ) ) {
			return;
		}

		$settings = $this->settings->get();
		if ( 'redirect' === $settings['blocked_behavior'] && ! empty( $settings['redirect_url'] ) ) {
			$redirect_url = wp_validate_redirect( $settings['redirect_url'], '' );
			if ( $redirect_url ) {
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	public function block_store_api_product_read( $result, $server, $request ) {
		unset( $server );

		if ( null !== $result || ! is_object( $request ) || ! is_callable( array( $request, 'get_route' ) ) ) {
			return $result;
		}

		$method = is_callable( array( $request, 'get_method' ) ) ? strtoupper( (string) $request->get_method() ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return $result;
		}

		$product_id = $this->get_store_api_product_id_from_route( (string) $request->get_route() );
		if ( ! $product_id || $this->access->can_view_product( $product_id ) ) {
			return $result;
		}

		return new WP_Error(
			'mbm_rgp_product_restricted',
			esc_html__( 'Product not found.', 'mbm-role-gated-products' ),
			array( 'status' => 404 )
		);
	}

	private function get_add_to_cart_message() {
		$message = (string) $this->settings->get_value( 'add_to_cart_message' );

		return $message ? $message : __( 'This product is not available to your account.', 'mbm-role-gated-products' );
	}

	private function get_cart_removed_message() {
		$message = (string) $this->settings->get_value( 'cart_removed_message' );

		return $message ? $message : __( 'An item was removed from your cart because it is no longer available to your account.', 'mbm-role-gated-products' );
	}

	private function get_store_api_product_id_from_route( $route ) {
		if ( ! preg_match( '#^/wc/store/v[0-9]+/products/(\\d+)(?:/|$)#', $route, $matches ) ) {
			return 0;
		}

		return absint( $matches[1] );
	}

	private function get_product_gate_id( $product ) {
		if ( is_object( $product ) && is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' ) && is_callable( array( $product, 'get_parent_id' ) ) ) {
			return (int) $product->get_parent_id();
		}

		return is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ? (int) $product->get_id() : 0;
	}
}

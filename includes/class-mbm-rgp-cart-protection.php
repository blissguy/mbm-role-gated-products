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
		add_action( 'template_redirect', array( $this, 'block_single_product' ), 0 );
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

		$this->disable_canonical_redirects();

		$settings = $this->settings->get();
		if ( 'redirect' === $settings['blocked_behavior'] && ! empty( $settings['redirect_url'] ) ) {
			$redirect_url = wp_validate_redirect( $settings['redirect_url'], '' );

			// Redirecting to the page we are already on would loop forever.
			if ( $redirect_url && ! $this->is_current_path( $redirect_url ) ) {
				wp_safe_redirect( $redirect_url, 302 );
				exit;
			}
		}

		$this->send_404();
	}

	private function send_404() {
		global $wp_query;

		$wp_query->set_404();

		/*
		 * set_404() only resets the query flags, so the request still carries the
		 * restricted product's slug and post object. Anything that tries to
		 * recover a 404 -- core's permalink guessing, SEO and redirect plugins,
		 * 404 templates reading the queried post -- can map the request straight
		 * back to the product and bounce the visitor to a URL that 404s again.
		 * Detach the request from the product so this is an ordinary 404.
		 */
		foreach ( array( 'name', 'p', 'page_id', 'pagename', 'product', 'post_type' ) as $query_var ) {
			$wp_query->set( $query_var, '' );
		}

		$wp_query->posts             = array();
		$wp_query->post              = null;
		$wp_query->post_count        = 0;
		$wp_query->found_posts       = 0;
		$wp_query->queried_object    = null;
		$wp_query->queried_object_id = 0;
		$GLOBALS['post']             = null;

		status_header( 404 );
		nocache_headers();
	}

	private function disable_canonical_redirects() {
		$this->remove_action_from_every_priority( 'template_redirect', 'redirect_canonical' );
		$this->remove_action_from_every_priority( 'template_redirect', 'wp_old_slug_redirect' );

		// Neutralize both functions even if something re-adds or calls them directly.
		add_filter( 'do_redirect_guess_404_permalink', '__return_false', PHP_INT_MAX );
		add_filter( 'redirect_canonical', '__return_false', PHP_INT_MAX );
		add_filter( 'old_slug_redirect_url', '__return_false', PHP_INT_MAX );

		$this->disable_third_party_404_autoredirects();
	}

	/*
	 * Redirect plugins commonly answer a 404 by sending the visitor to the
	 * closest matching slug. A restricted product's closest match is its own
	 * slug, so the blocked page redirects to itself and loops until the browser
	 * gives up. These integrations switch that guessing off for this request
	 * only, leaving the rest of each plugin -- its own redirect rules, 404
	 * logging, and 404 page settings -- untouched.
	 */
	private function disable_third_party_404_autoredirects() {
		/*
		 * WP 301 Redirects (Pro): "Autoredirect 404". It scores the requested
		 * slug against a cached slug index with similar_text() and redirects to
		 * anything above 65% -- which a restricted product always matches at
		 * 100%. Emptying the index for this request makes it find no candidate.
		 * The plugin exposes no hooks of its own, so this filters the transient
		 * it reads.
		 */
		add_filter( 'pre_transient_wf301_indexed_slugs', '__return_empty_array', PHP_INT_MAX );

		/**
		 * Fires when a restricted product page is being blocked, before the
		 * response is sent. Use it to switch off any other feature that turns a
		 * 404 into a redirect.
		 */
		do_action( 'mbm_rgp_disable_404_autoredirects' );
	}

	private function remove_action_from_every_priority( $hook, $callback ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof WP_Hook ) {
			return;
		}

		foreach ( array_keys( $wp_filter[ $hook ]->callbacks ) as $priority ) {
			remove_action( $hook, $callback, $priority );
		}
	}

	private function is_current_path( $url ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return $this->normalize_path( $url ) === $this->normalize_path( $request_uri );
	}

	private function normalize_path( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );

		return '' === $path ? '/' : trailingslashit( $path );
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

		$product_id = $this->get_store_api_product_gate_id_from_route( (string) $request->get_route() );
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

	private function get_store_api_product_gate_id_from_route( $route ) {
		if ( ! preg_match( '#^/wc/store(?:/v[0-9]+)?/products/([^/]+)/?$#', $route, $matches ) ) {
			return 0;
		}

		$identifier = rawurldecode( (string) $matches[1] );
		if ( '' === $identifier || $this->is_reserved_store_api_product_segment( $identifier ) ) {
			return 0;
		}

		$product = false;
		if ( ctype_digit( $identifier ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( absint( $identifier ) );
		} elseif ( function_exists( 'get_page_by_path' ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_page_by_path( sanitize_title( $identifier ), OBJECT, array( 'product', 'product_variation' ) ) );
		}

		return $product ? $this->get_product_gate_id( $product ) : 0;
	}

	private function is_reserved_store_api_product_segment( $segment ) {
		return in_array(
			sanitize_key( $segment ),
			array( 'attributes', 'brands', 'categories', 'collection-data', 'reviews', 'tags' ),
			true
		);
	}

	private function get_product_gate_id( $product ) {
		if ( is_object( $product ) && is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' ) && is_callable( array( $product, 'get_parent_id' ) ) ) {
			return (int) $product->get_parent_id();
		}

		return is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ? (int) $product->get_id() : 0;
	}
}

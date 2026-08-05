<?php
/**
 * Keeps WordPress 404 recovery from bouncing visitors to gated products.
 *
 * Core answers a 404 by guessing a nearby permalink and issuing a *permanent*
 * redirect to it. When that guess lands on a role-gated product the visitor is
 * sent to a URL that immediately 404s again, and because the hop is a 301 both
 * the browser and any shared cache keep replaying it. The result is a redirect
 * loop that survives long after the original request.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Redirect_Guard {
	private $access;

	public function __construct( MBM_RGP_Access $access ) {
		$this->access = $access;
	}

	public function hooks() {
		add_filter( 'redirect_canonical', array( $this, 'block_redirect_to_hidden_product' ), 10, 2 );
		add_filter( 'old_slug_redirect_post_id', array( $this, 'block_old_slug_redirect' ) );
	}

	public function block_redirect_to_hidden_product( $redirect_url, $requested_url = '' ) {
		if ( empty( $redirect_url ) || ! is_string( $redirect_url ) ) {
			return $redirect_url;
		}

		// A canonical redirect that points at the current URL can only loop.
		if ( $requested_url && $this->same_path( $redirect_url, $requested_url ) ) {
			return false;
		}

		$product_id = $this->get_product_id_from_url( $redirect_url );
		if ( $product_id && ! $this->access->can_view_product( $product_id ) ) {
			return false;
		}

		return $redirect_url;
	}

	public function block_old_slug_redirect( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return $post_id;
		}

		return $this->access->can_view_product( $post_id ) ? $post_id : 0;
	}

	private function get_product_id_from_url( $url ) {
		/*
		 * Resolved from the slug rather than with url_to_postid(), because that
		 * runs its own product query -- which this plugin filters restricted
		 * products out of, so it can never identify the products we need to
		 * recognize here.
		 */
		$path = trim( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/' );
		if ( '' === $path ) {
			return 0;
		}

		$segments = explode( '/', $path );
		$slug     = sanitize_title( (string) end( $segments ) );
		if ( '' === $slug ) {
			return 0;
		}

		$product = get_page_by_path( $slug, OBJECT, 'product' );

		return $product ? absint( $product->ID ) : 0;
	}

	private function same_path( $url, $other_url ) {
		return $this->normalize_path( $url ) === $this->normalize_path( $other_url );
	}

	private function normalize_path( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );

		return '' === $path ? '/' : trailingslashit( $path );
	}
}

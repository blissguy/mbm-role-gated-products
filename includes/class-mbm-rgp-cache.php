<?php
/**
 * Keeps role-specific product responses out of shared caches.
 *
 * A restricted product page renders differently for each visitor, so it must
 * never be stored by a host page cache or CDN. Without this, one visitor's
 * response is replayed to everyone else: an approved visitor's product page
 * gets served to logged-out visitors, or a blocked 404 gets served to an
 * approved customer.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Cache {
	private $access;
	private $purged = array();

	public function __construct( MBM_RGP_Access $access ) {
		$this->access = $access;
	}

	public function hooks() {
		// Runs before the single-product gate so both the approved view and the
		// blocked response are marked private.
		add_action( 'template_redirect', array( $this, 'keep_restricted_product_out_of_caches' ), -1 );
		add_action( 'mbm_rgp_product_restriction_changed', array( $this, 'purge_product' ) );
	}

	public function keep_restricted_product_out_of_caches() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( ! $this->access->is_restricted( get_queried_object_id() ) ) {
			return;
		}

		$this->mark_response_private();
	}

	public function mark_response_private() {
		// Honored by Batcache, WP Cloud, WP Rocket, W3 Total Cache, LiteSpeed
		// and most other page caches.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'Vary: Cookie', false );
		}
	}

	public function purge_product( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || isset( $this->purged[ $product_id ] ) ) {
			return;
		}

		$this->purged[ $product_id ] = true;

		clean_post_cache( $product_id );

		$url = get_permalink( $product_id );

		// Batcache, used by WP Cloud and Pressable.
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $product_id );
		}

		if ( $url && function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) ) {
			wpcom_vip_purge_edge_cache_for_url( $url );
		}

		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $product_id );
		}

		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $product_id );
		}

		if ( $url ) {
			do_action( 'litespeed_purge_url', $url );
		}

		/**
		 * Fires so a site can purge its own page cache or CDN for a product
		 * whose role restrictions just changed.
		 *
		 * @param string $url        Product permalink, or an empty string.
		 * @param int    $product_id Product ID.
		 */
		do_action( 'mbm_rgp_purge_product_url', (string) $url, $product_id );
	}
}

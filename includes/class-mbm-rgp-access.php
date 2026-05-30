<?php
/**
 * Shared product access checks.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Access {
	private $settings;
	private $hidden_product_ids = array();
	private $loading_restricted_product_ids = false;

	public function __construct( MBM_RGP_Settings $settings ) {
		$this->settings = $settings;
	}

	public function is_restricted( $product_id ) {
		return 'yes' === get_post_meta( absint( $product_id ), MBM_RGP_Plugin::META_RESTRICT_ENABLED, true );
	}

	public function can_view_product( $product_id, $user_id = 0 ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || ! $this->is_restricted( $product_id ) ) {
			return true;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( $user_id && user_can( $user_id, 'manage_woocommerce' ) ) {
			return true;
		}

		if ( ! $user_id ) {
			return false;
		}

		$allowed = $this->get_allowed_roles( $product_id );
		if ( empty( $allowed ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return (bool) array_intersect( $allowed, (array) $user->roles );
	}

	public function get_allowed_roles( $product_id ) {
		$allowed = get_post_meta( absint( $product_id ), MBM_RGP_Plugin::META_ALLOWED_ROLES, true );
		$allowed = array_filter( array_map( 'sanitize_key', (array) $allowed ) );

		return array_values( array_unique( $allowed ) );
	}

	public function get_restricted_product_ids() {
		$ids = get_transient( MBM_RGP_Plugin::TRANSIENT_RESTRICTED_PRODUCT_IDS );

		if ( false === $ids ) {
			$this->loading_restricted_product_ids = true;
			$ids = get_posts(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => -1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_key'               => MBM_RGP_Plugin::META_RESTRICT_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'             => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			$this->loading_restricted_product_ids = false;

			set_transient( MBM_RGP_Plugin::TRANSIENT_RESTRICTED_PRODUCT_IDS, array_map( 'absint', (array) $ids ), DAY_IN_SECONDS );
		}

		return array_map( 'absint', (array) $ids );
	}

	public function get_hidden_product_ids() {
		$user = wp_get_current_user();
		$key  = $user && $user->ID ? $user->ID . ':' . implode( ',', (array) $user->roles ) : 'guest';

		if ( isset( $this->hidden_product_ids[ $key ] ) ) {
			return $this->hidden_product_ids[ $key ];
		}

		$hidden = array();
		foreach ( $this->get_restricted_product_ids() as $product_id ) {
			if ( ! $this->can_view_product( $product_id ) ) {
				$hidden[] = $product_id;
			}
		}

		$this->hidden_product_ids[ $key ] = $hidden;

		return $hidden;
	}

	public function is_loading_restricted_product_ids() {
		return $this->loading_restricted_product_ids;
	}

	public function flush_cache() {
		$this->hidden_product_ids = array();
		delete_transient( MBM_RGP_Plugin::TRANSIENT_RESTRICTED_PRODUCT_IDS );
	}
}

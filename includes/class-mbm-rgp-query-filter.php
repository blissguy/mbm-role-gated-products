<?php
/**
 * Product query filtering.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Query_Filter {
	private $access;

	public function __construct( MBM_RGP_Access $access ) {
		$this->access = $access;
	}

	public function hooks() {
		add_action( 'woocommerce_product_query', array( $this, 'filter_shop_query' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_wordpress_query' ), 20 );
		add_action( 'init', array( $this, 'register_bricks_query_control' ), PHP_INT_MAX );
		add_filter( 'bricks/posts/query_vars', array( $this, 'filter_bricks_query_vars' ), 20, 4 );
		add_filter( 'woocommerce_related_products', array( $this, 'filter_product_id_list' ), 10, 1 );
		add_filter( 'woocommerce_product_get_upsell_ids', array( $this, 'filter_product_id_list' ), 10, 1 );
		add_filter( 'woocommerce_product_get_cross_sell_ids', array( $this, 'filter_product_id_list' ), 10, 1 );
		add_filter( 'woocommerce_cart_crosssell_ids', array( $this, 'filter_product_id_list' ), 10, 1 );
	}

	public function filter_shop_query( $query ) {
		$this->apply_hidden_ids_to_query( $query );
	}

	public function filter_wordpress_query( $query ) {
		if ( is_admin() || $this->access->is_loading_restricted_product_ids() ) {
			return;
		}

		if ( $this->is_main_product_page_query( $query ) ) {
			return;
		}

		$targets_products = false;

		if ( $query->is_main_query() && $query->is_search() ) {
			$targets_products = true;
		}

		$post_type = $query->get( 'post_type' );
		if ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
			$targets_products = true;
		}

		if ( $targets_products ) {
			$this->apply_hidden_ids_to_query( $query );
		}
	}

	public function filter_bricks_query_vars( $query_vars, $settings = array(), $element_id = '', $element_name = '' ) {
		unset( $element_id, $element_name );

		if ( current_user_can( 'manage_woocommerce' ) ) {
			return $query_vars;
		}

		$show_restricted = ! empty( $settings['mbmRgpShowRestricted'] );
		$exclude         = $show_restricted ? $this->access->get_hidden_product_ids() : $this->access->get_restricted_product_ids();

		if ( empty( $exclude ) ) {
			return $query_vars;
		}

		$post_in = isset( $query_vars['post__in'] ) ? $this->normalize_id_list( $query_vars['post__in'] ) : array();
		if ( ! empty( $post_in ) ) {
			$remaining              = array_values( array_diff( $post_in, $this->normalize_id_list( $exclude ) ) );
			$query_vars['post__in'] = ! empty( $remaining ) ? $remaining : array( 0 );

			return $query_vars;
		}

		$existing                    = isset( $query_vars['post__not_in'] ) ? (array) $query_vars['post__not_in'] : array();
		// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Required to hide restricted products from Bricks query loops.
		$query_vars['post__not_in'] = array_values( array_unique( array_merge( array_map( 'absint', $existing ), $exclude ) ) );

		return $query_vars;
	}

	public function register_bricks_query_control() {
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}

		$elements = wp_list_pluck( \Bricks\Elements::$elements, 'name' );
		$elements = apply_filters( 'mbm_rgp_bricks_loopable_elements', $elements );

		foreach ( array_filter( (array) $elements ) as $element_name ) {
			add_filter( "bricks/elements/{$element_name}/controls", array( $this, 'add_bricks_query_control' ) );
		}
	}

	public function add_bricks_query_control( $controls ) {
		$controls['mbmRgpShowRestricted'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Show restricted products', 'mbm-role-gated-products' ),
			'type'        => 'checkbox',
			'required'    => array( 'hasLoop', '!=', '' ),
			'description' => esc_html__( 'On: restricted products appear here, but only for viewers with an approved role. Off: they are hidden from this loop entirely.', 'mbm-role-gated-products' ),
		);

		return $controls;
	}

	public function filter_product_id_list( $product_ids ) {
		$hidden = $this->access->get_hidden_product_ids();
		if ( empty( $hidden ) ) {
			return $product_ids;
		}

		return array_values( array_diff( array_map( 'absint', (array) $product_ids ), $hidden ) );
	}

	private function apply_hidden_ids_to_query( $query ) {
		$hidden = $this->access->get_hidden_product_ids();
		if ( empty( $hidden ) ) {
			return;
		}

		$post_in = $this->normalize_id_list( $query->get( 'post__in' ) );
		if ( ! empty( $post_in ) ) {
			$remaining = array_values( array_diff( $post_in, $hidden ) );
			$query->set( 'post__in', ! empty( $remaining ) ? $remaining : array( 0 ) );

			return;
		}

		$existing = $this->normalize_id_list( $query->get( 'post__not_in' ) );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $hidden ) ) ) );
	}

	private function normalize_id_list( $ids ) {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	}

	private function is_main_product_page_query( $query ) {
		if ( ! $query->is_main_query() ) {
			return false;
		}

		if ( $query->is_singular( 'product' ) ) {
			return true;
		}

		if ( 'product' !== $query->get( 'post_type' ) ) {
			return false;
		}

		return (bool) ( $query->get( 'name' ) || $query->get( 'p' ) || $query->get( 'product' ) );
	}
}

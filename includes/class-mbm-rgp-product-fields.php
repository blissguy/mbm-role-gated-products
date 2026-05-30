<?php
/**
 * WooCommerce product edit fields.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Product_Fields {
	private $settings;
	private $access;

	public function __construct( MBM_RGP_Settings $settings, MBM_RGP_Access $access ) {
		$this->settings = $settings;
		$this->access   = $access;
	}

	public function hooks() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
		add_action( 'save_post_product', array( $this, 'flush_cache' ) );
		add_action( 'trashed_post', array( $this, 'flush_cache' ) );
		add_action( 'untrashed_post', array( $this, 'flush_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
	}

	public function render_fields() {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => MBM_RGP_Plugin::META_RESTRICT_ENABLED,
				'label'       => __( 'Restrict by role', 'mbm-role-gated-products' ),
				'description' => __( 'Only the approved roles below can see or buy this product.', 'mbm-role-gated-products' ),
			)
		);

		$saved = $this->get_saved_or_default_roles( $post->ID );
		$roles = $this->get_role_choices();

		echo '<p class="form-field"><label for="mbm_rgp_allowed_roles">' . esc_html__( 'Approved roles', 'mbm-role-gated-products' ) . '</label>';
		echo '<select id="mbm_rgp_allowed_roles" class="wc-enhanced-select" name="' . esc_attr( MBM_RGP_Plugin::META_ALLOWED_ROLES ) . '[]" multiple="multiple" style="width:50%" data-placeholder="' . esc_attr__( 'Select roles', 'mbm-role-gated-products' ) . '">';

		foreach ( $roles as $slug => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( in_array( $slug, $saved, true ), true, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		echo '<span class="description">' . esc_html__( 'If no role is selected, the product is locked for every customer-facing role.', 'mbm-role-gated-products' ) . '</span>';
		echo '</p>';

		echo '</div>';
	}

	public function save_fields( $product_id ) {
		$product_id = absint( $product_id );

		if (
			empty( $_POST['woocommerce_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$enabled = isset( $_POST[ MBM_RGP_Plugin::META_RESTRICT_ENABLED ] ) ? 'yes' : 'no';
		update_post_meta( $product_id, MBM_RGP_Plugin::META_RESTRICT_ENABLED, $enabled );

		$roles = array();
		if ( isset( $_POST[ MBM_RGP_Plugin::META_ALLOWED_ROLES ] ) ) {
			$raw_roles = (array) wp_unslash( $_POST[ MBM_RGP_Plugin::META_ALLOWED_ROLES ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$roles     = array_filter( array_map( 'sanitize_key', $raw_roles ) );
			$roles     = array_values( array_intersect( array_unique( $roles ), array_keys( wp_roles()->roles ) ) );
		}

		update_post_meta( $product_id, MBM_RGP_Plugin::META_ALLOWED_ROLES, $roles );
		$this->access->flush_cache();
	}

	public function flush_cache() {
		$this->access->flush_cache();
	}

	private function get_saved_or_default_roles( $product_id ) {
		if ( metadata_exists( 'post', $product_id, MBM_RGP_Plugin::META_ALLOWED_ROLES ) ) {
			return $this->access->get_allowed_roles( $product_id );
		}

		$default_role = sanitize_key( (string) $this->settings->get_value( 'default_role' ) );

		return $default_role ? array( $default_role ) : array();
	}

	private function get_role_choices() {
		$choices = array();

		foreach ( wp_roles()->roles as $role_slug => $role ) {
			$name                  = isset( $role['name'] ) ? $role['name'] : $role_slug;
			$choices[ $role_slug ] = translate_user_role( $name );
		}

		natcasesort( $choices );

		return $choices;
	}
}

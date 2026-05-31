<?php
/**
 * Admin-facing indicators and manager banner.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Admin {
	private $settings;
	private $access;

	public function __construct( MBM_RGP_Settings $settings, MBM_RGP_Access $access ) {
		$this->settings = $settings;
		$this->access   = $access;
	}

	public function hooks() {
		add_action( 'wp_footer', array( $this, 'render_admin_banner' ) );
		add_filter( 'manage_edit-product_columns', array( $this, 'add_restriction_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_restriction_column' ), 10, 2 );
	}

	public function render_admin_banner() {
		if ( ! $this->is_enabled( 'admin_banner_enabled' ) || ! function_exists( 'is_product' ) || ! is_product() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$product_id = get_queried_object_id();
		if ( ! $this->access->is_restricted( $product_id ) ) {
			return;
		}

		$labels = $this->get_allowed_role_labels( $product_id );
		$detail = $labels
			? sprintf(
				/* translators: %s: comma-separated role labels. */
				__( 'Visible and available only to approved role(s): %s', 'mbm-role-gated-products' ),
				implode( ', ', $labels )
			)
			: __( 'No approved roles are set, so this is hidden from every customer-facing role.', 'mbm-role-gated-products' );

		printf(
			'<div role="status" style="position:fixed;inset-block-end:0;inset-inline:0;z-index:99999;background:#1d2327;color:#fff;padding:.75rem 1rem;font:14px/1.45 system-ui,sans-serif;text-align:center;box-shadow:0 -2px 8px rgba(0,0,0,.25)"><strong style="color:#ffb900">%1$s</strong> &nbsp; %2$s</div>',
			esc_html__( 'Restricted product (admin view)', 'mbm-role-gated-products' ),
			esc_html( $detail )
		);
	}

	public function add_restriction_column( $columns ) {
		if ( ! $this->is_enabled( 'product_column_enabled' ) ) {
			return $columns;
		}

		$position = array_search( 'price', array_keys( $columns ), true );
		$position = false === $position ? count( $columns ) : $position;

		return array_slice( $columns, 0, $position, true )
			+ array( 'mbm_rgp_restricted' => __( 'Access', 'mbm-role-gated-products' ) )
			+ array_slice( $columns, $position, null, true );
	}

	public function render_restriction_column( $column, $product_id ) {
		if ( 'mbm_rgp_restricted' !== $column ) {
			return;
		}

		if ( ! $this->access->is_restricted( $product_id ) ) {
			echo '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'Public', 'mbm-role-gated-products' ) . '</span>';
			return;
		}

		$labels  = $this->get_allowed_role_labels( $product_id );
		$tooltip = $labels
			? sprintf(
				/* translators: %s: comma-separated role labels. */
				__( 'Restricted to: %s', 'mbm-role-gated-products' ),
				implode( ', ', $labels )
			)
			: __( 'Restricted, no approved roles', 'mbm-role-gated-products' );

		printf(
			'<span class="dashicons dashicons-lock" style="color:#b32d2e" title="%1$s"></span> <span style="color:#646970">%2$s</span>',
			esc_attr( $tooltip ),
			esc_html( $labels ? implode( ', ', $labels ) : __( 'Locked', 'mbm-role-gated-products' ) )
		);
	}

	private function get_allowed_role_labels( $product_id ) {
		$names  = $this->get_role_choices();
		$labels = array();

		foreach ( $this->access->get_allowed_roles( $product_id ) as $slug ) {
			$labels[] = isset( $names[ $slug ] ) ? $names[ $slug ] : $slug;
		}

		return $labels;
	}

	private function get_role_choices() {
		$choices = array();

		foreach ( wp_roles()->roles as $role_slug => $role ) {
			$name                  = isset( $role['name'] ) ? $role['name'] : $role_slug;
			$choices[ $role_slug ] = translate_user_role( $name );
		}

		return $choices;
	}

	private function is_enabled( $key ) {
		return 1 === absint( $this->settings->get_value( $key ) );
	}
}

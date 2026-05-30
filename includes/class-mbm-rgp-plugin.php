<?php
/**
 * Main plugin loader.
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MBM_RGP_Plugin {
	const OPTION_SETTINGS = 'mbm_role_gated_products_settings';

	const META_RESTRICT_ENABLED = '_mbm_rgp_restrict_enabled';
	const META_ALLOWED_ROLES    = '_mbm_rgp_allowed_roles';

	const TRANSIENT_RESTRICTED_PRODUCT_IDS = 'mbm_rgp_restricted_product_ids';

	private static $instance = null;

	private $settings;
	private $access;
	private $product_fields;
	private $query_filter;
	private $cart_protection;
	private $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-settings.php';

		$defaults = MBM_RGP_Settings::defaults();
		$current  = get_option( self::OPTION_SETTINGS );

		if ( ! is_array( $current ) ) {
			add_option( self::OPTION_SETTINGS, $defaults );
		} else {
			update_option( self::OPTION_SETTINGS, array_intersect_key( wp_parse_args( $current, $defaults ), $defaults ) );
		}

		delete_transient( self::TRANSIENT_RESTRICTED_PRODUCT_IDS );
	}

	public static function deactivate() {
		delete_transient( self::TRANSIENT_RESTRICTED_PRODUCT_IDS );
	}

	private function __construct() {
		$this->load_dependencies();

		$this->settings = new MBM_RGP_Settings();
		$this->settings->hooks();

		add_filter( 'plugin_action_links_' . plugin_basename( MBM_RGP_FILE ), array( $this, 'plugin_action_links' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		$this->access          = new MBM_RGP_Access( $this->settings );
		$this->product_fields  = new MBM_RGP_Product_Fields( $this->settings, $this->access );
		$this->query_filter    = new MBM_RGP_Query_Filter( $this->access );
		$this->cart_protection = new MBM_RGP_Cart_Protection( $this->settings, $this->access );
		$this->admin           = new MBM_RGP_Admin( $this->settings, $this->access );

		$this->product_fields->hooks();
		$this->query_filter->hooks();
		$this->cart_protection->hooks();
		$this->admin->hooks();
	}

	private function load_dependencies() {
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-settings.php';
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-access.php';
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-product-fields.php';
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-query-filter.php';
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-cart-protection.php';
		require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-admin.php';
	}

	public function settings() {
		return $this->settings;
	}

	public function access() {
		return $this->access;
	}

	public function plugin_action_links( $links ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $links;
		}

		$url = admin_url( 'admin.php?page=mbm-role-gated-products' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'mbm-role-gated-products' )
			)
		);

		return $links;
	}

	public function missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'MBM Role-Gated Products for WooCommerce requires WooCommerce to be active.', 'mbm-role-gated-products' );
		echo '</p></div>';
	}
}

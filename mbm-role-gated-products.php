<?php
/**
 * Plugin Name: MBM Role-Gated Products for WooCommerce
 * Description: Restrict WooCommerce product visibility and purchasing by WordPress user role.
 * Version: 1.0.2
 * Author: MixBus Marketing
 * Author URI: https://mixbusmarketing.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mbm-role-gated-products
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @package MBMRoleGatedProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MBM_RGP_VERSION', '1.0.2' );
define( 'MBM_RGP_FILE', __FILE__ );
define( 'MBM_RGP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MBM_RGP_URL', plugin_dir_url( __FILE__ ) );

require_once MBM_RGP_PATH . 'includes/class-mbm-rgp-plugin.php';

register_activation_hook( __FILE__, array( 'MBM_RGP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MBM_RGP_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'MBM_RGP_Plugin', 'instance' ), 20 );

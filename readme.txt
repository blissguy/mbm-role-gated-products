=== MBM Role-Gated Products for WooCommerce ===
Contributors: emmanuelkuebu, mixbusmarketing
Tags: woocommerce, roles, products, access-control
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restrict WooCommerce product visibility and purchasing by WordPress user role.

== Description ==

MBM Role-Gated Products for WooCommerce adds per-product role restrictions and enforces them across product listings, search, Bricks query loops, direct product URLs, add-to-cart requests, the Store API, and checkout.

Bricks query loops hide restricted products by default. Enable the element-level Show restricted products control when a specific loop should include restricted products for approved viewers.

== Installation ==

1. Upload or copy the plugin folder to `wp-content/plugins/mbm-role-gated-products`.
2. Activate WooCommerce.
3. Activate this plugin.
4. Go to WooCommerce > Role-Gated Products.
5. Enable role restrictions on individual WooCommerce products.

== Frequently Asked Questions ==

= Are restricted products only hidden in the UI? =

No. The plugin enforces access server-side for product pages, add-to-cart requests, Store API requests, cart validation, and product queries.

== Changelog ==

= 1.0.0 =

Initial release.

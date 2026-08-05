=== Role-Gated Products for WooCommerce ===
Contributors: emmanuelkuebu, mixbusmarketing
Tags: woocommerce, roles, products, access-control
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restrict WooCommerce product visibility and purchasing by WordPress user role.

== Description ==

Role-Gated Products for WooCommerce adds per-product role restrictions and enforces them across product listings, search, Bricks query loops, direct product URLs, add-to-cart requests, the Store API, and checkout.

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

= Does this work with page caching or a CDN? =

Yes. Restricted product pages are marked as private so caching layers do not store them, because what a visitor sees on those pages depends on who they are.

If a restricted product was already cached before you restricted it, clear your host or CDN cache once for that page. After that the plugin keeps it out of the cache on its own.

= Visitors get a "redirected you too many times" error on a restricted product. Why? =

A redirect plugin is answering the hidden page's "page not found" by sending visitors to the closest matching address, which for a restricted product is its own address. This version stops that. If you use WP 301 Redirects you can leave its Autoredirect 404 option switched on.

== Changelog ==

= 1.1.0 =

Renamed to Role-Gated Products for WooCommerce.

Fixed a redirect loop on restricted product pages. Redirect plugins and WordPress itself answer a "page not found" by sending visitors to the closest matching address. For a restricted product the closest match is its own address, so the hidden page redirected to itself over and over until the browser gave up with a "redirected you too many times" error. Restricted products are now left out of that guessing.

This includes an integration with WP 301 Redirects, whose Autoredirect 404 option caused the loop. You can leave that option switched on; it is now skipped only for the specific pages a visitor is not allowed to see, and keeps working everywhere else on the site.

Restricted product pages are no longer stored by page caches or CDNs, so one visitor's version of a page can never be shown to someone with different access. Previously a cached copy could show a restricted product to visitors who should not see it.

Product caches are now cleared automatically when a product's access settings change, including changes made by imports, bulk edits, or other tools rather than the product screen.

= 1.0.2 =

Fix restricted single product redirects and prevent canonical redirects from exposing restricted product permalinks.

= 1.0.1 =

Harden Store API restricted product reads and improve approved roles field layout.

= 1.0.0 =

Initial release.

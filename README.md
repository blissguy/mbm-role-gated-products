# MBM Role-Gated Products for WooCommerce

Restrict WooCommerce product visibility and purchasing by WordPress user role.

## What It Does

- Adds per-product controls for role-gated access.
- Hides restricted products from WooCommerce archives, WordPress search, Bricks query loops, related products, upsells, and cross-sells.
- Adds a Bricks query-loop checkbox for loops that should include restricted products for approved viewers.
- Blocks direct product URLs with a 404 by default or an optional redirect.
- Blocks classic add-to-cart and WooCommerce Store API add-to-cart requests.
- Removes restricted items from the cart if access changes after an item was added.
- Lets shop managers and admins keep access for editing and previewing products.

## Setup

1. Activate WooCommerce.
2. Activate this plugin.
3. Go to **WooCommerce > Role-Gated Products**.
4. Choose the default approved role. If an `artist` role exists, it is selected by default.
5. Edit a WooCommerce product, enable **Restrict by role**, and choose the approved roles.

## Artist Approval Workflows

This plugin is intentionally generic. It does not depend on AEA Artist Invites or any specific approval plugin.

For an artist application workflow, keep the approval system responsible for moving approved users into the `artist` role. This plugin then uses that role to decide whether those users can see or buy restricted products.

## Bricks Query Loops

Restricted products are hidden from Bricks query loops by default, even for approved users. This keeps general product grids clean.

For a loop that should intentionally show restricted products, enable **Show restricted products** in that element's Query controls. With that enabled, restricted products still only appear for viewers whose role is approved on the product.

## Settings

- **Default approved role**: preselects a role on products that have not saved access settings yet.
- **Blocked product page behavior**: returns a 404 by default, or redirects to a configured URL.
- **Blocked add-to-cart message**: shown when an unauthorized user tries to add a restricted product.
- **Cart removal message**: shown when a restricted product is removed from the cart.
- **Admin banner**: shows shop managers a front-end banner on restricted product pages.
- **Products table column**: shows an Access column on the Products admin screen.
- **Clear restricted product cache**: deletes the cached list of restricted product IDs.

## Notes

Access is enforced server-side. Product URLs, labels, and UI visibility are not used as the security mechanism.

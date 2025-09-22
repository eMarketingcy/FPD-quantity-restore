=== FPD Quantity Restore ===
Contributors: emarketing-cyprus
Tags: woocommerce, fancy product designer, quantity, breakdance, cart, admin
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: eMarketing Cyprus

Restore the quantity selector on products customized with Fancy Product Designer (FPD). Includes a per-product checkbox, a global toggle, and a modern admin panel with Breakdance layout controls that apply only when the FPD designer is present.

== Description ==
FPD often hides the WooCommerce quantity input and forces qty=1, because its workflow assumes one unique design at a time. This plugin shows the quantity selector again, duplicating the same design for the selected quantity.

New in 1.3.0:
* **Modernized admin UI** with separate CSS/JS assets.
* **Separated fields** for each layout property with !important toggles.
* Frontend assets split into **frontend.css** and **frontend.js**.

== Installation ==
1. Upload the `fpd-qty-restore` folder to `/wp-content/plugins/` or install the ZIP via wp-admin.
2. Activate the plugin.
3. Go to **FPD Qty Restore** and configure:
   - **Global Behavior** → enable site-wide for detected FPD products.
   - **Breakdance Layout Controls** → flex-wrap, button width/margin/display, price spacing.
4. Per product: in **Inventory**, check **“Allow quantity with Fancy Product Designer.”**

== Usage ==
* Global **ON** → detect FPD products by meta keys; show qty selector.
* Per-product toggle → always show qty for that product.
* Styles only apply when `.fpd-product-designer-wrapper` exists (JS adds `fpd-has-designer`).

== Hooks ==
`apply_filters( 'fpd_qr_is_fpd_product', bool $detected, int $product_id )`

== Changelog ==
= 1.3.0 =
* New modern admin UI (separate CSS/JS).
* Split frontend assets; separated fields and !important toggles.

= 1.2.0 =
* Admin UI cards + layout controls (scoped to FPD pages).

= 1.1.0 =
* Global toggle and uninstall script.

= 1.0.0 =
* Per-product toggle, visual unhide, cart quantity enforcement.

=== Propono - Checkout Order Bump for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, order bump, upsell, checkout, conversion
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show a one-click order-bump offer on the checkout so shoppers can add a complementary product before they pay.

== Description ==

Propono adds a single, high-visibility order-bump offer above the payment methods on the classic WooCommerce checkout. Ticking the checkbox adds your chosen product to the order and WooCommerce recalculates every total before payment, so the amount sent to the gateway already includes the bump. Unticking removes it just as fast.

* One offer, one checkbox, above the payment methods.
* Pick any simple, purchasable product as the bump.
* Optional special price for the bump line only.
* Uses WooCommerce's own cart and `update_checkout` recalculation, no custom totals, no gateway surprises.
* HPOS compatible, scoped styles, no layout shift.

The bump line is tagged privately so it is never confused with a copy the shopper added themselves. Classic (shortcode) checkout.

The full source lives at [github.com/wppoland/propono](https://github.com/wppoland/propono).

== Installation ==

1. Upload to `/wp-content/plugins/propono`, or install via Plugins > Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce > Order Bump, choose the bump product, write the copy, enable, and save.

== Screenshots ==

1. The order-bump offer above the payment methods on the checkout, with a one-click checkbox that adds the product to the order.
2. The Propono settings under WooCommerce: bump product, heading, checkbox label, description and optional special price.

== Translations ==

Propono is fully translatable and ships the `propono.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

== Changelog ==

= 1.1.2 =
* Security (medium): the optional special price now covers exactly one unit of the bump product and only while the cart holds another product. Before, a shopper could raise the bump quantity or remove everything else and buy any number of units at the special price.

= 1.1.1 =
* Links point at the new plogins.com address and the renamed GitHub repository.

= 1.1.0 =
* Renamed to Propono. The WordPress.org review team asks a plugin name to lead with a distinctive, coined identifier rather than a generic descriptive word. Propono is Esperanto for an offer. The text domain follows the name; the stored data, the settings and every hook are unchanged.

= 1.0.10 =
* Fixed: arrow glyphs in the admin menu paths, and in the strings handed to translators. An arrow inside a translatable string makes the glyph every translator's problem and changes the layout in any locale that drops it.

= 1.0.9 =
* The translation template was regenerated. It still named an older version of the plugin and pointed at source lines that had since moved, which is what translation tools read to show a string in context.

= 1.0.8 =
* Renamed to Plogins Bump - Checkout Order Bump for WooCommerce so the name leads with the brand rather than a generic word, which is what the WordPress.org plugin review team asks for. The plugin slug is unchanged.

= 1.0.7 =
* Removed the "Tested up to" header from the main PHP file. It belongs in readme.txt only, where it is already declared; present in both, the header can override the readme and show a compatibility version that was never intended.

= 1.0.6 =
* Tested against WordPress 7.1. Verified by activating this build on a clean 7.1 install with WooCommerce 11.1, not by editing the header.

= 1.0.5 =
* Fix: the bump product list now only offers products the checkout can really show, and if the product you saved loses its price or sells out, the settings screen says the offer is hidden instead of leaving the choice looking fine.
* Fix: the accent colour is accepted with or without the leading hash, so typing d97706 now colours the offer instead of clearing the field.

= 1.0.4 =
* Fix: the bump price override is handed to WooCommerce as the numeric string it already is, instead of being cast to a float first. WC_Product::set_price() is typed for a string and stores what it is given.

= 1.0.2 =
* Added bundled Polish, German and Spanish translations for the plugin interface.

= 1.0.1 =
* Initial release: single checkout order bump with optional price override.

== Upgrade Notice ==

= 1.1.2 =
Security release. Only shops that set a special bump price are exposed. Update, nothing else to do.

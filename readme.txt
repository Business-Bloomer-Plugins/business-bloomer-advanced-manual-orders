=== Advanced Manual Orders for WooCommerce ===
Contributors: businessbloomer
Tags: manual order, phone order, admin order, currency, add products
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create manual orders without the pain: pick a currency, get real shipping rates, taxes that update on their own, duplicate and reorder in one click.

== Description ==

Taking an order by phone or email in WooCommerce means typing the shipping cost by hand, clicking Recalculate after every change, and hoping the customer can actually pay the link you send. Advanced Manual Orders fixes the order screen you already use.

= What it adds =

* **New order form:** click "Add order", on the orders list or on an order. Start from a past order (pick from your latest 10 or search by name, email or number) and repeat it whole, or copy its products for a customer and currency you choose. Saved addresses are filled in.
* **Add products in one click:** an "Add a product" box always sits above the order items. Best sellers come first, or type a name or SKU. Each pick adds one, pick it again for more. Every variation is listed on its own, "Any" attributes included, so there's nothing else to pick. Pick a product that's already on the order and its quantity goes up.
* **Currency per order:** turn on the currency selector (one click, or in the settings) and pick any currency for a manual order, on the form or on the order screen while the order is unpaid. The currencies your store sells in come first, read from WooPayments, WPML, Aelia, CURCY, FOX, YayCurrency, TIV.NET, Price Based on Country and Booster.
* **Real shipping rates:** "Calculate shipping", next to Recalculate, shows the rates of your shipping zones for these items and this address, the same ones checkout would show. Pick one and it's added with its tax.
* **Taxes that update on their own:** add or remove items, change quantities or the address, and taxes and totals are recalculated. No more Recalculate button.
* **Duplicate order:** one click from the orders list, the order preview or the order screen. An exact copy, same customer, items, prices, shipping, fees and coupons, saved as a new pending order. Payment details, invoices, license keys and order notes stay with the original.
* **New order for this customer:** a fresh order with the same customer and addresses.
* **New order with these products:** the same products and quantities at today's prices.
* **Payment link that works:** customers pay without logging in or confirming their email, and the pay page runs in the order's currency. "Copy link" button next to it.

New orders open as drafts with WooCommerce's own "Create" button, exactly like a normal new order. Drafts you never create are cleaned up by WooCommerce after a few days.

= Settings =

WooCommerce > Settings > Advanced > Manual orders.

== Frequently Asked Questions ==

= Are prices converted when I change the currency? =

No. The currency is changed and prices stay as entered, so type the prices you agreed with the customer. Automatic conversion at exchange rates is coming in the Pro version.

= Which shipping methods work? =

Any method that works at checkout: flat rate, free shipping (including minimum amounts and free shipping coupons), local pickup and shipping plugins. Rates are calculated from the items saved on the order and the shipping address on screen (or the billing one when shipping is empty).

= Is it safe to let customers pay without logging in? =

It only applies to orders created in the admin, and only with the full payment link, which contains a secret key. Anyone with the link can see that order and pay it, the same as a guest order. You can switch it off in the settings.

= My store charges the same price in every country and manual orders get it wrong. =

If you set `woocommerce_adjust_non_base_location_prices` to false, WooCommerce 10.5 to 11.2 price products added to a manual order for the wrong country ([issue 63701](https://github.com/woocommerce/woocommerce/issues/63701)). The plugin fixes it until WooCommerce 11.3 ships the official fix. You can switch it off in the settings.

= What does "Repeat the whole order" leave out? =

Everything that belongs to the original's payment and processing: payment method, transaction ID, paid and completed dates, stock changes, order notes, and hidden data added by other plugins, such as invoice numbers, license keys and tracking numbers. Visible custom fields and line item details, like variation attributes, product options or an engraving text, are copied. Developers can change this with the `amo_copy_meta` filter.

= Does it work with HPOS? =

Yes, with both order storage types.

= My multi-currency plugin isn't listed. =

Add its currencies in the settings and they show at the top of the list. Developers can use the `amo_store_currencies` filter.

== Changelog ==

= 0.1.0 =
* First version.

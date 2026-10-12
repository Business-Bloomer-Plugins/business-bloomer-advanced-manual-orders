=== Business Bloomer Advanced Manual Orders for WooCommerce ===
Contributors: businessbloomer
Tags: manual order, phone order, admin order, currency, add products
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Faster manual orders: find customers by phone or email, real shipping rates, email the payment link, duplicate and reorder in one click.

== Description ==

Taking an order by phone or email in WooCommerce means typing the shipping cost by hand, clicking Recalculate after every change, and hoping the customer can actually pay the link you send. Advanced Manual Orders fixes the order screen you already use.

https://youtu.be/RCZxM-ybwV0

= What it adds =

* **New order form:** click "Add order", on the orders list or on an order. Start from a past order (pick from your latest 10 or search by name, email or number) and repeat it whole, or copy its products for a customer and currency you choose. Saved addresses are filled in.
* **Find any customer, fast:** search by name, email, phone or company, on the new order form and in the order's Customer field. Registered customers and past guests are both listed, and phone numbers match however they were typed ("0915550101" finds "091-555-0101"). Pick one and their billing and shipping addresses are filled in on the spot. Pick a guest and the order stays a guest order.
* **Add products in one click:** an "Add a product" box always sits above the order items. Best sellers come first, or type a name or SKU. Each pick adds one, pick it again for more. Every variation is listed on its own, "Any" attributes included, so there's nothing else to pick. Pick a product that's already on the order and its quantity goes up.
* **Currency per order:** turn on the currency selector (one click, or in the settings) and pick any currency for a manual order, on the form or on the order screen while the order is unpaid. The currencies your store sells in come first, read from WooPayments, WPML, Aelia, CURCY, FOX, YayCurrency, TIV.NET, Price Based on Country and Booster.
* **Real shipping rates:** "Calculate shipping", next to Recalculate, shows the rates of your shipping zones for these items and this address, the same ones checkout would show. Pick one and it's added with its tax.
* **Taxes that update on their own:** add or remove items, change quantities or the address, and taxes and totals are recalculated. No more Recalculate button.
* **Duplicate order:** one click from the orders list, the order preview or the order screen. An exact copy, same customer, items, prices, shipping, fees and coupons, saved as a new order (Pending payment by default, you choose the status in the settings) and no emails are sent. Payment details, invoices, license keys and order notes stay with the original.
* **Tips where you need them:** WooCommerce's own buttons (Add item, Add fee, Add tax, Recalculate) get a short tip on what the plugin now does for you, like percentage fees and automatic taxes.
* **New order for this customer:** a fresh order with the same customer and addresses.
* **New order with these products:** the same products and quantities at today's prices.
* **Payment link that works:** customers pay without logging in or confirming their email, and the pay page runs in the order's currency. Under the order status, "Order Pay link: Copy" copies it and "Save & email" saves your changes and sends it to the customer with WooCommerce's own invoice email.
* **Ready for AI agents:** AI assistants connected to your store (through the Abilities API and MCP) can find a customer, create the order with shipping and taxes, email the payment link, check shipping rates and duplicate orders. "Make Mario an order for 2 red hoodies with the cheapest shipping and send him the pay link" just works. Only users who can edit orders can use them, and customer names, emails, phones and addresses are never sent to the AI service.

New orders open as drafts with WooCommerce's own "Create" button, exactly like a normal new order. Drafts you never create are cleaned up by WooCommerce after a few days.

= Settings =

WooCommerce > Settings > Advanced > Advanced Manual Orders.

== Installation ==

1. Install and activate the plugin from Plugins > Add New, or upload the zip.
2. Open any order, or click "Add order" on the orders list.
3. Optional: review the settings under WooCommerce > Settings > Advanced > Advanced Manual Orders.

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

Everything that belongs to the original's payment and processing: payment method, transaction ID, paid and completed dates, stock changes, order notes, and hidden data added by other plugins, such as invoice numbers, license keys and tracking numbers. Visible custom fields and line item details, like variation attributes, product options or an engraving text, are copied. Developers can change this with the `amowc_copy_meta` filter.

= What email does "Save & email" (Order Pay link) send? =

WooCommerce's own "Order details" customer invoice email, with your email template and settings, the same one as Order actions > Send order details to customer. The order is saved first, so the email has your latest changes, then it goes to the billing email and a note is added to the order.

= Which abilities does it add for AI agents? =

Six: find-customers, create-order, get-shipping-rates, set-shipping, email-pay-link and duplicate-order, under business-bloomer-advanced-manual-orders/. They run the same code as the order screen. Results refer to customers by ID only: names, emails, phone numbers and addresses stay on your site.

= Does it work with HPOS? =

Yes, with both order storage types.

= My multi-currency plugin isn't listed. =

Add its currencies in the settings and they show at the top of the list. Developers can use the `amowc_store_currencies` filter.

== Screenshots ==

1. "Add order" asks for the customer first: registered customers and past guests, found by name, email, phone or company.
2. The "Add a product" box: best sellers first, every variation listed on its own, one click to add.
3. "Calculate shipping" shows the real rates of your shipping zones for these items and this address.
4. Under the order status: copy the payment link, or save the order and email it, and pick the order currency.
5. Duplicate any order from the orders list, the preview or the order screen.
6. The settings, under WooCommerce > Settings > Advanced > Advanced Manual Orders.

== Changelog ==

= 1.0.0 =
* First release.

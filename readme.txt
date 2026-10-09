=== Manual Order Superpowers for WooCommerce ===
Contributors: businessbloomer
Tags: manual order, phone order, admin order, currency, shipping
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create manual orders without the pain: pick a currency, get real shipping rates, taxes that update on their own, duplicate and reorder in one click.

== Description ==

Taking an order by phone or email in WooCommerce means typing the shipping cost by hand, clicking Recalculate after every change, and hoping the customer can actually pay the link you send. Manual Order Superpowers fixes the order screen you already use.

= What it adds =

* **New order form:** click "Add order" and choose the customer, the currency and an old order to copy products from. Their saved addresses are filled in.
* **Currency per order:** pick any currency for a manual order, on the form or on the order screen while the order is unpaid.
* **Real shipping rates:** "Calculate shipping" shows the rates of your shipping zones for these items and this address, the same ones checkout would show. Pick one and it's added with its tax.
* **Taxes that update on their own:** add or remove items, change quantities or the address, and taxes and totals are recalculated. No more Recalculate button.
* **Duplicate order:** an exact copy, same customer, items, prices, shipping and fees.
* **New order for this customer:** a fresh order with the same customer and addresses.
* **New order with these products:** the same products and quantities at today's prices.
* **Payment link that works:** customers pay without logging in or confirming their email, and the pay page runs in the order's currency. "Copy link" button next to it.
* **Payment link email:** optionally email the link to the customer as soon as you create a pending order.

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

= Does it work with HPOS? =

Yes, with both order storage types.

== Changelog ==

= 0.1.0 =
* First version.

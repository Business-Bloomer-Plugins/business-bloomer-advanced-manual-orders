<?php
/**
 * Temporary fix for WooCommerce issue 63701: with woocommerce_adjust_non_base_location_prices
 * set to false, products added to a manual order are priced for the wrong country.
 * WooCommerce fixes it in 11.3, so this only runs on older versions.
 *
 * @see https://github.com/woocommerce/woocommerce/issues/63701
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_ajax_add_order_item_meta', 'amowc_same_price_on_add', 10, 3 );

/**
 * True when this WooCommerce version needs the fix.
 *
 * @return bool
 */
function amowc_same_price_fix_needed() {
	return version_compare( WC_VERSION, '11.3', '<' );
}

/**
 * True when the fix should run: setting on, prices include tax, same price in every country.
 *
 * @return bool
 */
function amowc_same_price_fix_active() {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.
	return amowc_same_price_fix_needed() && amowc_enabled( 'same_price_fix' ) && wc_tax_enabled() && wc_prices_include_tax() && ! apply_filters( 'woocommerce_adjust_non_base_location_prices', true );
}

/**
 * The address on screen sent with the plugin's own "Add a product" request.
 *
 * @param array|null $location country, state, postcode, city. Null to read it.
 * @return array
 */
function amowc_request_location( $location = null ) {
	static $saved = array();
	if ( null !== $location ) {
		$saved = $location;
	}
	return $saved;
}

/**
 * Tax address for a newly added product: the one on screen, else the one saved on the order.
 *
 * @param WC_Order $order Order.
 * @return array country, state, postcode, city.
 */
function amowc_tax_location( $order ) {
	$sent     = amowc_request_location();
	$location = array();
	foreach ( array( 'country', 'state', 'postcode', 'city' ) as $key ) {
		if ( isset( $sent[ $key ] ) ) {
			$location[ $key ] = wc_strtoupper( $sent[ $key ] );
			continue;
		}
		// WooCommerce's own "Add item(s)" request, which the order screen sends with the address.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read during WooCommerce's own nonce-checked AJAX request.
		$location[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wc_strtoupper( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	if ( ! $location['country'] && method_exists( $order, 'get_taxable_location' ) ) {
		$location = wp_parse_args( $order->get_taxable_location(), $location );
	}

	return $location;
}

/**
 * Prices a product line so the customer pays the product price, tax included, whatever the country.
 *
 * @param WC_Order_Item_Product $item     Line item.
 * @param array                 $location Tax address.
 */
function amowc_same_price_item( $item, $location ) {
	$product = $item->get_product();

	if ( ! $product || ! $product->is_taxable() || empty( $location['country'] ) ) {
		return;
	}

	$gross = (float) $product->get_price() * $item->get_quantity();
	$rates = WC_Tax::get_rates_from_location( $product->get_tax_class(), array( $location['country'], $location['state'], $location['postcode'], $location['city'] ) );
	$net   = $gross - array_sum( WC_Tax::calc_tax( $gross, $rates, true ) );

	$item->set_subtotal( $net );
	$item->set_total( $net );
	$item->save();
}

/**
 * A product was just added from the order screen: price it for the address on screen.
 *
 * @param int                   $item_id Item ID.
 * @param WC_Order_Item_Product $item    Item.
 * @param WC_Order              $order   Order.
 */
function amowc_same_price_on_add( $item_id, $item, $order ) {
	if ( $item instanceof WC_Order_Item_Product && amowc_same_price_fix_active() ) {
		amowc_same_price_item( $item, amowc_tax_location( $order ) );
	}
}

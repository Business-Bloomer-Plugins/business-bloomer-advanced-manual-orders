<?php
/**
 * Real shipping rates for manual orders.
 *
 * WooCommerce only calculates shipping for a cart. So the order items go into a
 * throwaway cart, with a throwaway customer and session, the rates are read, and
 * everything is put back. Shipping plugins see exactly what they see at checkout.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_order_item_add_action_buttons', 'amowc_shipping_button' );

/**
 * "Calculate shipping" button next to "Recalculate", always in view.
 *
 * @param WC_Order $order Order.
 */
function amowc_shipping_button( $order ) {
	if ( ! wc_shipping_enabled() || ! $order->is_editable() ) {
		return;
	}
	echo '<button type="button" class="button amowc-calc-shipping">' . esc_html__( 'Calculate shipping', 'business-bloomer-advanced-manual-orders' ) . '</button>';
}

/**
 * Shipping rates for the order items, grouped by package.
 *
 * @param WC_Order $order       Order.
 * @param array    $destination country, state, postcode, city, address_1, address_2.
 * @return WC_Shipping_Rate[][] Package index => rate ID => rate.
 */
function amowc_get_shipping_rates( $order, $destination ) {
	$wc    = WC();
	$saved = array(
		'cart'     => $wc->cart,
		'customer' => $wc->customer,
		'session'  => $wc->session,
	);

	$rates = array();

	try {
		// Not initialised, so nothing it holds is ever saved.
		$wc->session = new WC_Session_Handler();

		$customer = new WC_Customer( $order->get_customer_id() );
		$customer->set_shipping_location( $destination['country'], $destination['state'], $destination['postcode'], $destination['city'] );
		$customer->set_shipping_address_1( $destination['address_1'] );
		$customer->set_shipping_address_2( $destination['address_2'] );
		$customer->set_billing_location( $order->get_billing_country(), $order->get_billing_state(), $order->get_billing_postcode(), $order->get_billing_city() );
		$wc->customer = $customer;

		// A cart that never touches the admin's own cart session.
		add_filter( 'woocommerce_cart_session_initialize', '__return_false' );
		$wc->cart = new WC_Cart();
		remove_filter( 'woocommerce_cart_session_initialize', '__return_false' );

		$contents = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$qty     = $item->get_quantity();

			if ( ! $product || $qty <= 0 ) {
				continue;
			}

			// The price as entered on the order, so "free over X" and price-based rates match it.
			$line = (float) $item->get_subtotal();
			if ( wc_prices_include_tax() ) {
				$line += (float) $item->get_subtotal_tax();
			}
			$product->set_price( $line / $qty );

			// Variation attributes, "Any" ones filled from the order item.
			$variation = $product->is_type( 'variation' ) ? $product->get_variation_attributes() : array();
			foreach ( $variation as $name => $value ) {
				if ( '' === $value ) {
					$variation[ $name ] = (string) $item->get_meta( substr( $name, 10 ) );
				}
			}

			$key              = $wc->cart->generate_cart_id( $item->get_product_id(), $item->get_variation_id(), $variation, array( 'amowc_item' => $item_id ) );
			$contents[ $key ] = array(
				'key'          => $key,
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'variation'    => $variation,
				'quantity'     => $qty,
				'data'         => $product,
				'data_hash'    => wc_get_cart_item_data_hash( $product ),
			);
		}

		if ( ! $contents ) {
			return array();
		}

		$wc->cart->set_cart_contents( $contents );
		$wc->cart->set_applied_coupons( $order->get_coupon_codes() );
		$wc->cart->calculate_totals();

		foreach ( $wc->shipping()->get_packages() as $index => $package ) {
			if ( ! empty( $package['rates'] ) ) {
				$rates[ $index ] = $package['rates'];
			}
		}
	} finally {
		$wc->cart     = $saved['cart'];
		$wc->customer = $saved['customer'];
		$wc->session  = $saved['session'];
		$wc->shipping()->reset_shipping();
	}

	return $rates;
}

/**
 * The order and the destination sent by the order screen.
 *
 * @param WP_REST_Request $request Request.
 * @return array|WP_Error Order and destination.
 */
function amowc_shipping_request( $request ) {
	$order = amowc_rest_order( $request );

	if ( is_wp_error( $order ) ) {
		return $order;
	}

	$destination = amowc_rest_address( $request );

	if ( ! $destination['country'] ) {
		return new WP_Error( 'amowc_shipping', __( 'Enter the shipping or billing country first.', 'business-bloomer-advanced-manual-orders' ), array( 'status' => 400 ) );
	}

	return array( $order, $destination );
}

/**
 * The available rates, grouped by package.
 *
 * @param WP_REST_Request $request Request.
 * @return array|WP_Error
 */
function amowc_rest_shipping_rates( $request ) {
	$found = amowc_shipping_request( $request );

	if ( is_wp_error( $found ) ) {
		return $found;
	}

	list( $order, $destination ) = $found;

	$packages = array();

	foreach ( amowc_get_shipping_rates( $order, $destination ) as $index => $rates ) {
		$list = array();
		foreach ( $rates as $rate_id => $rate ) {
			$list[] = array(
				'id'    => $rate_id,
				'label' => $rate->get_label(),
				'cost'  => html_entity_decode( wp_strip_all_tags( wc_price( $rate->get_cost(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		$packages[] = array(
			'index' => $index,
			'rates' => $list,
		);
	}

	if ( ! $packages ) {
		return new WP_Error( 'amowc_shipping', __( 'No shipping method is available for these items and this address. Check your shipping zones, or that the products need shipping.', 'business-bloomer-advanced-manual-orders' ), array( 'status' => 400 ) );
	}

	return array( 'packages' => $packages );
}

/**
 * Replaces the order's shipping lines with the chosen rates.
 *
 * @param WP_REST_Request $request Request.
 * @return array|WP_Error
 */
function amowc_rest_add_shipping( $request ) {
	$found = amowc_shipping_request( $request );

	if ( is_wp_error( $found ) ) {
		return $found;
	}

	list( $order, $destination ) = $found;

	$chosen = array_map( 'sanitize_text_field', array_map( 'strval', (array) $request['rates'] ) );
	$added  = array();

	foreach ( amowc_get_shipping_rates( $order, $destination ) as $index => $rates ) {
		$rate_id = isset( $chosen[ $index ] ) ? $chosen[ $index ] : '';
		if ( isset( $rates[ $rate_id ] ) ) {
			$added[] = $rates[ $rate_id ];
		}
	}

	if ( ! $added ) {
		return new WP_Error( 'amowc_shipping', __( 'Pick a shipping method.', 'business-bloomer-advanced-manual-orders' ), array( 'status' => 400 ) );
	}

	foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
		$order->remove_item( $item_id );
	}

	foreach ( $added as $rate ) {
		$item = new WC_Order_Item_Shipping();
		$item->set_shipping_rate( $rate );
		$order->add_item( $item );
	}

	$order->calculate_totals( false );

	return array( 'added' => count( $added ) );
}

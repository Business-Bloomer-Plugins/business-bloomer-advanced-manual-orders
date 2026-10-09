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
add_action( 'wp_ajax_amowc_shipping_rates', 'amowc_ajax_shipping_rates' );
add_action( 'wp_ajax_amowc_add_shipping', 'amowc_ajax_add_shipping' );

/**
 * "Calculate shipping" button next to "Recalculate", always in view.
 *
 * @param WC_Order $order Order.
 */
function amowc_shipping_button( $order ) {
	if ( ! wc_shipping_enabled() || ! $order->is_editable() ) {
		return;
	}
	echo '<button type="button" class="button amowc-calc-shipping">' . esc_html__( 'Calculate shipping', 'advanced-manual-orders-for-woocommerce' ) . '</button>';
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
 * Reads the order and the destination posted by the order screen.
 *
 * @return array Order and destination.
 */
function amowc_shipping_request() {
	check_ajax_referer( 'amowc_admin', 'security' );

	$order = isset( $_POST['order_id'] ) ? wc_get_order( absint( $_POST['order_id'] ) ) : null;

	if ( ! $order || ! current_user_can( 'edit_shop_orders' ) || ! $order->is_editable() ) {
		wp_send_json_error( array( 'message' => __( 'This order can not be edited.', 'advanced-manual-orders-for-woocommerce' ) ) );
	}

	$destination = array();
	foreach ( array( 'country', 'state', 'postcode', 'city', 'address_1', 'address_2' ) as $field ) {
		$destination[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
	}

	if ( ! $destination['country'] ) {
		wp_send_json_error( array( 'message' => __( 'Enter the shipping or billing country first.', 'advanced-manual-orders-for-woocommerce' ) ) );
	}

	return array( $order, $destination );
}

/**
 * Returns the available rates to the order screen.
 */
function amowc_ajax_shipping_rates() {
	list( $order, $destination ) = amowc_shipping_request();

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
		wp_send_json_error( array( 'message' => __( 'No shipping method is available for these items and this address. Check your shipping zones, or that the products need shipping.', 'advanced-manual-orders-for-woocommerce' ) ) );
	}

	wp_send_json_success( array( 'packages' => $packages ) );
}

/**
 * Replaces the order's shipping lines with the chosen rates.
 */
function amowc_ajax_add_shipping() {
	list( $order, $destination ) = amowc_shipping_request();

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in amowc_shipping_request().
	$chosen = isset( $_POST['rates'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['rates'] ) ) : array();
	$added  = array();

	foreach ( amowc_get_shipping_rates( $order, $destination ) as $index => $rates ) {
		$rate_id = isset( $chosen[ $index ] ) ? $chosen[ $index ] : '';
		if ( isset( $rates[ $rate_id ] ) ) {
			$added[] = $rates[ $rate_id ];
		}
	}

	if ( ! $added ) {
		wp_send_json_error( array( 'message' => __( 'Pick a shipping method.', 'advanced-manual-orders-for-woocommerce' ) ) );
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

	wp_send_json_success();
}

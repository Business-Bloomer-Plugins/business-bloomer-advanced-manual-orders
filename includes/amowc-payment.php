<?php
/**
 * Payment link: pay without login, the order's currency on the pay page, email it to the customer.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'user_has_cap', 'amowc_allow_pay_with_link', 20, 3 );
add_filter( 'woocommerce_order_email_verification_required', 'amowc_skip_email_verification', 20, 3 );
add_action( 'wp', 'amowc_pay_page_currency', 1 );

/**
 * The admin-created order whose valid payment link is being opened, if any.
 *
 * @param int $order_id Order ID to check.
 * @return WC_Order|null
 */
function amowc_order_from_pay_link( $order_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the proof.
	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

	if ( ! $key || ! $order_id || ! amowc_enabled( 'guest_pay' ) ) {
		return null;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || 'admin' !== $order->get_created_via() || ! hash_equals( $order->get_order_key(), $key ) ) {
		return null;
	}

	return $order;
}

/**
 * Lets whoever has the payment link pay an order created in the admin,
 * logged out or logged in as someone else.
 *
 * @param array $allcaps All capabilities of the user.
 * @param array $caps    Required capabilities.
 * @param array $args    [0] capability, [1] user ID, [2] order ID.
 * @return array
 */
function amowc_allow_pay_with_link( $allcaps, $caps, $args ) {
	if ( isset( $args[0], $args[2] ) && 'pay_for_order' === $args[0] && amowc_order_from_pay_link( absint( $args[2] ) ) ) {
		$allcaps['pay_for_order'] = true;
	}
	return $allcaps;
}

/**
 * No "confirm your email address" step on the pay page of an admin-created order.
 *
 * @param bool     $required Whether verification is required.
 * @param WC_Order $order    Order.
 * @param string   $context  Where the check happens.
 * @return bool
 */
function amowc_skip_email_verification( $required, $order, $context = '' ) {
	if ( $required && 'order-pay' === $context && $order && amowc_order_from_pay_link( $order->get_id() ) ) {
		return false;
	}
	return $required;
}

/**
 * On the pay page of an order in another currency, the store runs in that currency,
 * so payment methods check and charge the right one.
 */
function amowc_pay_page_currency() {
	$order_id = absint( get_query_var( 'order-pay' ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the proof.
	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

	if ( ! $order_id || ! $key ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || get_option( 'woocommerce_currency' ) === $order->get_currency() ) {
		return;
	}

	$currency = $order->get_currency();
	add_filter(
		'woocommerce_currency',
		function () use ( $currency ) {
			return $currency;
		}
	);
}

/**
 * "Email Order Pay link": sends WooCommerce's own customer invoice email, which carries
 * the payment link, exactly like "Order actions > Send order details to customer".
 *
 * @param WP_REST_Request $request Request.
 * @return array|WP_Error
 */
function amowc_rest_email_pay_link( $request ) {
	$order = amowc_rest_order( $request, false );

	if ( is_wp_error( $order ) ) {
		return $order;
	}

	if ( ! $order->needs_payment() ) {
		return new WP_Error( 'amowc_pay_link', __( 'This order doesn\'t need payment.', 'business-bloomer-advanced-manual-orders' ), array( 'status' => 400 ) );
	}

	if ( ! is_email( $order->get_billing_email() ) ) {
		return new WP_Error( 'amowc_pay_link', __( 'Add the customer\'s billing email and update the order first.', 'business-bloomer-advanced-manual-orders' ), array( 'status' => 400 ) );
	}

	// Same hooks and steps as WooCommerce's "Send order details to customer".
	do_action( 'woocommerce_before_resend_order_emails', $order, 'customer_invoice' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook, fired as core's order action does.

	WC()->payment_gateways();
	WC()->shipping();
	WC()->mailer()->customer_invoice( $order );

	/* translators: %s: customer email address */
	$order->add_order_note( sprintf( __( 'Payment link emailed to %s.', 'business-bloomer-advanced-manual-orders' ), $order->get_billing_email() ), false, true );

	do_action( 'woocommerce_after_resend_order_email', $order, 'customer_invoice' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook, fired as core's order action does.

	return array(
		'email'      => $order->get_billing_email(),
		'notes_html' => amowc_order_notes_html( $order->get_id() ),
	);
}

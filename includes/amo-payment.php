<?php
/**
 * Payment link: pay without login, the order's currency on the pay page.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'user_has_cap', 'amo_allow_pay_with_link', 20, 3 );
add_filter( 'woocommerce_order_email_verification_required', 'amo_skip_email_verification', 20, 3 );
add_action( 'wp', 'amo_pay_page_currency', 1 );

/**
 * The admin-created order whose valid payment link is being opened, if any.
 *
 * @param int $order_id Order ID to check.
 * @return WC_Order|null
 */
function amo_order_from_pay_link( $order_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the proof.
	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

	if ( ! $key || ! $order_id || ! amo_enabled( 'guest_pay' ) ) {
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
function amo_allow_pay_with_link( $allcaps, $caps, $args ) {
	if ( isset( $args[0], $args[2] ) && 'pay_for_order' === $args[0] && amo_order_from_pay_link( absint( $args[2] ) ) ) {
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
function amo_skip_email_verification( $required, $order, $context = '' ) {
	if ( $required && 'order-pay' === $context && $order && amo_order_from_pay_link( $order->get_id() ) ) {
		return false;
	}
	return $required;
}

/**
 * On the pay page of an order in another currency, the store runs in that currency,
 * so payment methods check and charge the right one.
 */
function amo_pay_page_currency() {
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

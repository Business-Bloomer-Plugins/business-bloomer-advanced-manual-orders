<?php
/**
 * Payment link: pay without login, the order's currency on the pay page, email on create.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'user_has_cap', 'mosw_allow_pay_with_link', 20, 3 );
add_filter( 'woocommerce_order_email_verification_required', 'mosw_skip_email_verification', 20, 3 );
add_action( 'wp', 'mosw_pay_page_currency', 1 );
add_action( 'woocommerce_process_shop_order_meta', 'mosw_email_payment_link', 100 );

/**
 * The admin-created order whose valid payment link is being opened, if any.
 *
 * @param int $order_id Order ID to check.
 * @return WC_Order|null
 */
function mosw_order_from_pay_link( $order_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the proof.
	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

	if ( ! $key || ! $order_id || ! mosw_enabled( 'guest_pay' ) ) {
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
function mosw_allow_pay_with_link( $allcaps, $caps, $args ) {
	if ( isset( $args[0], $args[2] ) && 'pay_for_order' === $args[0] && mosw_order_from_pay_link( absint( $args[2] ) ) ) {
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
function mosw_skip_email_verification( $required, $order, $context = '' ) {
	if ( $required && 'order-pay' === $context && $order && mosw_order_from_pay_link( $order->get_id() ) ) {
		return false;
	}
	return $required;
}

/**
 * On the pay page of an order in another currency, the store runs in that currency,
 * so payment methods check and charge the right one.
 */
function mosw_pay_page_currency() {
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
 * Emails the payment link once, the first time a pending admin order with items is saved.
 *
 * @param int $order_id Order ID.
 */
function mosw_email_payment_link( $order_id ) {
	if ( ! mosw_enabled( 'email_on_new' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || 'admin' !== $order->get_created_via() || ! $order->has_status( 'pending' ) || ! $order->get_billing_email() || ! $order->get_items() || $order->get_meta( '_mosw_link_emailed' ) ) {
		return;
	}

	WC()->mailer()->customer_invoice( $order );

	$order->update_meta_data( '_mosw_link_emailed', time() );
	$order->save();
	$order->add_order_note( __( 'Payment link emailed to the customer.', 'manual-order-superpowers-for-woocommerce' ), false, true );
}

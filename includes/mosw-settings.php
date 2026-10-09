<?php
/**
 * Settings: WooCommerce > Settings > Advanced > Manual orders.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_get_sections_advanced', 'mosw_add_section' );
add_filter( 'woocommerce_get_settings_advanced', 'mosw_section_settings', 10, 2 );

/**
 * Returns a setting, falling back to its default.
 *
 * @param string $key Setting name without the mosw_ prefix.
 * @return mixed
 */
function mosw_get_option( $key ) {
	$defaults = array(
		'popup'        => 'yes',
		'auto_totals'  => 'yes',
		'guest_pay'    => 'yes',
		'email_on_new' => 'no',
		'currencies'   => array(),
	);

	return get_option( 'mosw_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

/**
 * True when a yes/no setting is on.
 *
 * @param string $key Setting name without the mosw_ prefix.
 * @return bool
 */
function mosw_enabled( $key ) {
	return 'yes' === mosw_get_option( $key );
}

/**
 * URL of the settings section.
 *
 * @return string
 */
function mosw_settings_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=advanced&section=mosw' );
}

/**
 * Adds the "Manual orders" section.
 *
 * @param array $sections Advanced tab sections.
 * @return array
 */
function mosw_add_section( $sections ) {
	$sections['mosw'] = __( 'Manual orders', 'manual-order-superpowers-for-woocommerce' );
	return $sections;
}

/**
 * Settings fields of the section.
 *
 * @param array  $settings Current settings.
 * @param string $section  Current section.
 * @return array
 */
function mosw_section_settings( $settings, $section ) {
	if ( 'mosw' !== $section ) {
		return $settings;
	}

	$fields = array(
		array(
			'title' => __( 'Manual orders', 'manual-order-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => __( 'How orders you create in the admin behave.', 'manual-order-superpowers-for-woocommerce' ),
			'id'    => 'mosw_options',
		),
		array(
			'title'   => __( 'New order form', 'manual-order-superpowers-for-woocommerce' ),
			'desc'    => __( 'Ask for customer, currency and products to copy when you click "Add order"', 'manual-order-superpowers-for-woocommerce' ),
			'id'      => 'mosw_popup',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'   => __( 'Taxes and totals', 'manual-order-superpowers-for-woocommerce' ),
			'desc'    => __( 'Recalculate automatically when items, coupons or the address change', 'manual-order-superpowers-for-woocommerce' ),
			'id'      => 'mosw_auto_totals',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'    => __( 'Currencies', 'manual-order-superpowers-for-woocommerce' ),
			'desc'     => __( 'Currencies you can pick for a manual order. Leave empty to offer all of them.', 'manual-order-superpowers-for-woocommerce' ),
			'id'       => 'mosw_currencies',
			'type'     => 'multiselect',
			'class'    => 'wc-enhanced-select',
			'options'  => get_woocommerce_currencies(),
			'default'  => array(),
			'desc_tip' => true,
		),
		array(
			'title'    => __( 'Payment link', 'manual-order-superpowers-for-woocommerce' ),
			'desc'     => __( 'Customers can pay without logging in', 'manual-order-superpowers-for-woocommerce' ),
			'id'       => 'mosw_guest_pay',
			'type'     => 'checkbox',
			'default'  => 'yes',
			'desc_tip' => __( 'Only for orders created in the admin. Whoever has the link can see the order and pay it, the same as a guest order.', 'manual-order-superpowers-for-woocommerce' ),
		),
		array(
			'desc'    => __( 'Email the payment link to the customer when you create a pending order', 'manual-order-superpowers-for-woocommerce' ),
			'id'      => 'mosw_email_on_new',
			'type'    => 'checkbox',
			'default' => 'no',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'mosw_options',
		),
	);

	return apply_filters( 'mosw_settings_fields', $fields );
}

/**
 * Currencies offered for manual orders, code => name.
 *
 * @param string $keep    Currency always offered (the order's current one).
 * @return array
 */
function mosw_currencies( $keep = '' ) {
	$all     = get_woocommerce_currencies();
	$allowed = array_filter( (array) mosw_get_option( 'currencies' ) );
	$store   = get_option( 'woocommerce_currency' );

	if ( $allowed ) {
		$codes = array_unique( array_filter( array_merge( array( $store, $keep ), $allowed ) ) );
		$all   = array_intersect_key( $all, array_flip( $codes ) );
	}

	// Store currency first.
	if ( isset( $all[ $store ] ) ) {
		$all = array( $store => $all[ $store ] ) + $all;
	}

	return $all;
}

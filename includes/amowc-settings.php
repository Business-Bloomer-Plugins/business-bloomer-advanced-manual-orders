<?php
/**
 * Settings: WooCommerce > Settings > Advanced > Advanced Manual Orders.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_get_sections_advanced', 'amowc_add_section' );
add_filter( 'woocommerce_get_settings_advanced', 'amowc_section_settings', 10, 2 );
add_action( 'woocommerce_admin_field_amowc_pro', 'amowc_pro_features' );

/**
 * Returns a setting, falling back to its default.
 *
 * @param string $key Setting name without the amowc_ prefix.
 * @return mixed
 */
function amowc_get_option( $key ) {
	$defaults = array(
		'popup'          => 'yes',
		'auto_totals'    => 'yes',
		'guest_pay'      => 'yes',
		'currency'       => 'no',
		'dup_status'     => 'pending',
		'copy_fields'    => array(),
		'currencies'     => array(),
		'same_price_fix' => 'yes',
	);

	return get_option( 'amowc_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

/**
 * True when a yes/no setting is on.
 *
 * @param string $key Setting name without the amowc_ prefix.
 * @return bool
 */
function amowc_enabled( $key ) {
	return 'yes' === amowc_get_option( $key );
}

/**
 * URL of the settings section.
 *
 * @return string
 */
function amowc_settings_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=advanced&section=amowc' );
}

/**
 * Adds the "Advanced Manual Orders" section.
 *
 * @param array $sections Advanced tab sections.
 * @return array
 */
function amowc_add_section( $sections ) {
	$sections['amowc'] = __( 'Advanced Manual Orders', 'business-bloomer-advanced-manual-orders' );
	return $sections;
}

/**
 * Settings fields of the section.
 *
 * @param array  $settings Current settings.
 * @param string $section  Current section.
 * @return array
 */
function amowc_section_settings( $settings, $section ) {
	if ( 'amowc' !== $section ) {
		return $settings;
	}

	$fields = array(
		array(
			'title' => __( 'New orders', 'business-bloomer-advanced-manual-orders' ),
			'type'  => 'title',
			'desc'  => __( 'The "Add order" form, Duplicate and Repeat.', 'business-bloomer-advanced-manual-orders' ),
			'id'    => 'amowc_new_orders',
		),
		array(
			'title'   => __( 'New order form', 'business-bloomer-advanced-manual-orders' ),
			'desc'    => __( 'Ask for customer, currency and products to copy when you click "Add order"', 'business-bloomer-advanced-manual-orders' ),
			'id'      => 'amowc_popup',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'    => __( 'Duplicate order status', 'business-bloomer-advanced-manual-orders' ),
			'desc'     => __( 'Status of the copy made by "Duplicate". No emails are sent for it. On hold, Processing and Completed reduce stock, as for any order.', 'business-bloomer-advanced-manual-orders' ),
			'id'       => 'amowc_dup_status',
			'type'     => 'select',
			'class'    => 'wc-enhanced-select',
			'options'  => amowc_duplicate_statuses(),
			'default'  => 'pending',
			'desc_tip' => true,
		),
		array(
			'title'    => __( 'Also copy these fields', 'business-bloomer-advanced-manual-orders' ),
			'desc'     => __( 'Hidden order fields, like a VAT number, to copy to the new order when you duplicate or repeat an order, or start a new order for the same customer. The list shows the fields found on your orders.', 'business-bloomer-advanced-manual-orders' ),
			'id'       => 'amowc_copy_fields',
			'type'     => 'multiselect',
			'class'    => 'wc-enhanced-select',
			'options'  => 'amowc' === $section ? amowc_order_meta_keys() : array(),
			'default'  => array(),
			'desc_tip' => true,
		),
		array(
			'type' => 'sectionend',
			'id'   => 'amowc_new_orders',
		),
		array(
			'title' => __( 'Order edit page', 'business-bloomer-advanced-manual-orders' ),
			'type'  => 'title',
			'desc'  => __( 'While you add items, change quantities or the address.', 'business-bloomer-advanced-manual-orders' ),
			'id'    => 'amowc_edit_order',
		),
		array(
			'title'   => __( 'Taxes and totals', 'business-bloomer-advanced-manual-orders' ),
			'desc'    => __( 'Recalculate automatically when items, coupons or the address change', 'business-bloomer-advanced-manual-orders' ),
			'id'      => 'amowc_auto_totals',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'amowc_edit_order',
		),
		array(
			'title' => __( 'Currency', 'business-bloomer-advanced-manual-orders' ),
			'type'  => 'title',
			'desc'  => __( 'The currency of a manual order.', 'business-bloomer-advanced-manual-orders' ),
			'id'    => 'amowc_currency_options',
		),
		array(
			'title'   => __( 'Currency selector', 'business-bloomer-advanced-manual-orders' ),
			'desc'    => __( 'Pick a currency on the "Add order" form and on unpaid orders', 'business-bloomer-advanced-manual-orders' ),
			'id'      => 'amowc_currency',
			'type'    => 'checkbox',
			'default' => 'no',
		),
		array(
			'title'    => __( 'Currencies', 'business-bloomer-advanced-manual-orders' ),
			'desc'     => __( 'Extra currencies to show at the top of the currency list. Currencies from WooPayments, WPML, Aelia, CURCY, FOX, YayCurrency, TIV.NET, Price Based on Country and Booster are added on their own.', 'business-bloomer-advanced-manual-orders' ),
			'id'       => 'amowc_currencies',
			'type'     => 'multiselect',
			'class'    => 'wc-enhanced-select',
			'options'  => get_woocommerce_currencies(),
			'default'  => array(),
			'desc_tip' => true,
		),
		array(
			'type' => 'sectionend',
			'id'   => 'amowc_currency_options',
		),
		array(
			'title' => __( 'Payment link', 'business-bloomer-advanced-manual-orders' ),
			'type'  => 'title',
			'desc'  => __( 'The link customers use to pay a manual order.', 'business-bloomer-advanced-manual-orders' ),
			'id'    => 'amowc_payment_link',
		),
		array(
			'title'    => __( 'Payment link', 'business-bloomer-advanced-manual-orders' ),
			'desc'     => __( 'Customers can pay without logging in', 'business-bloomer-advanced-manual-orders' ),
			'id'       => 'amowc_guest_pay',
			'type'     => 'checkbox',
			'default'  => 'yes',
			'desc_tip' => __( 'Only for orders created in the admin. Whoever has the link can see the order and pay it, the same as a guest order.', 'business-bloomer-advanced-manual-orders' ),
		),
		array(
			'type' => 'sectionend',
			'id'   => 'amowc_payment_link',
		),
	);

	// Until WooCommerce 11.3, see amowc-tax-fix.php.
	if ( amowc_same_price_fix_needed() ) {
		$position = array_search( 'amowc_auto_totals', wp_list_pluck( $fields, 'id' ), true );
		array_splice(
			$fields,
			$position + 1,
			0,
			array(
				array(
					'title'    => __( 'Same price in every country', 'business-bloomer-advanced-manual-orders' ),
					'desc'     => __( 'Fix product prices on manual orders when your store charges the same price, tax included, in every country', 'business-bloomer-advanced-manual-orders' ),
					'id'       => 'amowc_same_price_fix',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'desc_tip' => __( 'For stores that set woocommerce_adjust_non_base_location_prices to false. Since WooCommerce 10.5, products added to a manual order are priced for the wrong country. WooCommerce fixes this in version 11.3, then this setting goes away.', 'business-bloomer-advanced-manual-orders' ),
				),
			)
		);
	}

	if ( ! amowc_pro_active() ) {
		$fields[] = array(
			'type' => 'amowc_pro',
			'id'   => 'amowc_pro',
		);
	}

	return apply_filters( 'amowc_settings_fields', $fields );
}

/**
 * Custom field names found on orders, for the "Also copy these fields" setting.
 * Fields WooCommerce stores itself (addresses, totals, dates) are left out.
 *
 * @return array Field names as keys and labels.
 */
function amowc_order_meta_keys() {
	global $wpdb;

	$keys = get_transient( 'amowc_order_meta_keys' );

	if ( ! is_array( $keys ) ) {
		$hpos = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- distinct field names, cached below.
		if ( $hpos ) {
			$keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}wc_orders_meta LIMIT 500" );
		} else {
			$keys = $wpdb->get_col( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' LIMIT 500" );
		}
		// phpcs:enable

		$internal = WC_Data_Store::load( 'order' )->get_internal_meta_keys();
		$keys     = array_values( array_diff( $keys, $internal, array( '_edit_lock', '_edit_last' ) ) );
		sort( $keys );
		set_transient( 'amowc_order_meta_keys', $keys, HOUR_IN_SECONDS );
	}

	// Saved fields stay listed even when no order has them anymore.
	$keys = array_unique( array_merge( (array) amowc_get_option( 'copy_fields' ), $keys ) );

	return array_combine( $keys, $keys );
}

/**
 * True when Advanced Manual Orders Pro is active.
 *
 * @return bool
 */
function amowc_pro_active() {
	return defined( 'AMOWCP_PLUGIN_FILE' );
}

/**
 * URL of the Pro plugin page.
 *
 * @return string
 */
function amowc_pro_url() {
	return 'https://www.businessbloomer.com/plugins/woocommerce-advanced-manual-orders-pro/';
}

/**
 * What Pro adds, shown below the settings while Pro is not active.
 */
function amowc_pro_features() {
	$features = array(
		__( 'Customers pay at your real checkout: the payment link fills their cart, so they can add their address, use a coupon and pick any payment method', 'business-bloomer-advanced-manual-orders' ),
		__( 'Choose which payment methods the payment link offers, make it expire, and send reminders until the order is paid', 'business-bloomer-advanced-manual-orders' ),
		__( 'Let customers change their address and shipping before they pay', 'business-bloomer-advanced-manual-orders' ),
		__( 'Order on your shop as a customer or a guest, then record a payment taken over the phone with a staff-only payment method', 'business-bloomer-advanced-manual-orders' ),
		__( 'Keep notes about each customer, shown on all their orders', 'business-bloomer-advanced-manual-orders' ),
		__( 'Prices you set by hand stay as they are when you add a coupon or change quantities', 'business-bloomer-advanced-manual-orders' ),
	);

	echo '<h2>' . esc_html__( 'Get more with Advanced Manual Orders Pro', 'business-bloomer-advanced-manual-orders' ) . '</h2><ul style="list-style:disc;margin-left:20px">';
	foreach ( $features as $feature ) {
		echo '<li>' . esc_html( $feature ) . '</li>';
	}
	echo '</ul><p><a href="' . esc_url( amowc_pro_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'See everything Pro does', 'business-bloomer-advanced-manual-orders' ) . '</a></p>';
}

/**
 * The currencies this store sells in: the store currency first, then the ones
 * enabled in a multi-currency plugin, then the ones added in the settings.
 *
 * @return string[] Currency codes.
 */
function amowc_store_currencies() {
	$codes = array( get_option( 'woocommerce_currency' ) );

	// WooPayments.
	if ( function_exists( 'WC_Payments_Multi_Currency' ) && class_exists( '\WCPay\MultiCurrency\MultiCurrency' ) && \WCPay\MultiCurrency\MultiCurrency::is_enabled() ) {
		$codes = array_merge( $codes, array_keys( WC_Payments_Multi_Currency()->get_enabled_currencies() ) );
	}

	// WPML WooCommerce Multilingual & Multicurrency.
	if ( function_exists( 'wcml_is_multi_currency_on' ) && wcml_is_multi_currency_on() ) {
		global $woocommerce_wpml;
		if ( is_object( $woocommerce_wpml ) && method_exists( $woocommerce_wpml, 'get_multi_currency' ) ) {
			$codes = array_merge( $codes, (array) $woocommerce_wpml->get_multi_currency()->get_currency_codes() );
		}
	}

	// Aelia Currency Switcher.
	if ( class_exists( 'WC_Aelia_CurrencySwitcher' ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Aelia's own filter.
		$codes = array_merge( $codes, (array) apply_filters( 'wc_aelia_cs_enabled_currencies', array() ) );
	}

	// CURCY by VillaTheme, free and premium.
	foreach ( array( 'WOOMULTI_CURRENCY_Data', 'WOOMULTI_CURRENCY_F_Data' ) as $class ) {
		if ( class_exists( $class ) && method_exists( $class, 'get_ins' ) ) {
			$codes = array_merge( $codes, (array) $class::get_ins()->get_currencies() );
			break;
		}
	}

	// FOX Currency Switcher (WOOCS). Its getter returns sample currencies when none are saved.
	if ( defined( 'WOOCS_VERSION' ) && is_array( get_option( 'woocs' ) ) ) {
		$codes = array_merge( $codes, array_keys( get_option( 'woocs' ) ) );
	}

	// YayCurrency.
	if ( class_exists( '\Yay_Currency\Helpers\Helper' ) && method_exists( '\Yay_Currency\Helpers\Helper', 'get_currencies_post_type' ) ) {
		$codes = array_merge( $codes, wp_list_pluck( (array) \Yay_Currency\Helpers\Helper::get_currencies_post_type(), 'post_title' ) );
	}

	// TIV.NET Multi-currency.
	if ( class_exists( '\WOOMC\API' ) && method_exists( '\WOOMC\API', 'enabled_currencies' ) ) {
		$codes = array_merge( $codes, (array) \WOOMC\API::enabled_currencies() );
	}

	// Price Based on Country.
	if ( class_exists( 'WCPBC_Pricing_Zones' ) ) {
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			if ( $zone->get_enabled() ) {
				$codes[] = $zone->get_currency();
			}
		}
	}

	// Booster for WooCommerce, Multicurrency module.
	if ( 'yes' === get_option( 'wcj_multicurrency_enabled' ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Booster's own filter.
		$total = (int) apply_filters( 'booster_option', 2, get_option( 'wcj_multicurrency_total_number', 2 ) );
		for ( $i = 1; $i <= $total; $i++ ) {
			$codes[] = get_option( 'wcj_multicurrency_currency_' . $i );
		}
	}

	$codes = array_merge( $codes, (array) amowc_get_option( 'currencies' ) );
	$codes = array_intersect( array_unique( array_filter( array_map( 'strval', $codes ) ) ), array_keys( get_woocommerce_currencies() ) );

	return array_values( (array) apply_filters( 'amowc_store_currencies', $codes ) );
}

/**
 * Every currency, code => name, the store's own currencies first.
 *
 * @return array
 */
function amowc_currencies() {
	$all   = get_woocommerce_currencies();
	$first = array();
	foreach ( amowc_store_currencies() as $code ) {
		$first[ $code ] = $all[ $code ];
	}

	return array_merge( $first, $all );
}

/**
 * Options for a currency dropdown: the store's currencies, then all the others.
 *
 * @param string $selected Selected currency code.
 */
function amowc_currency_options( $selected ) {
	$all    = amowc_currencies();
	$store  = array_intersect_key( $all, array_flip( amowc_store_currencies() ) );
	$groups = array(
		__( 'Store currencies', 'business-bloomer-advanced-manual-orders' ) => $store,
		__( 'All currencies', 'business-bloomer-advanced-manual-orders' )   => array_diff_key( $all, $store ),
	);

	foreach ( $groups as $label => $currencies ) {
		if ( ! $currencies ) {
			continue;
		}
		echo '<optgroup label="' . esc_attr( $label ) . '">';
		foreach ( $currencies as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $code, $selected, false ) . '>' . esc_html( $name . ' (' . $code . ' ' . html_entity_decode( get_woocommerce_currency_symbol( $code ), ENT_QUOTES, 'UTF-8' ) . ')' ) . '</option>';
		}
		echo '</optgroup>';
	}
}

/**
 * Statuses a duplicate can start in, without the wc- prefix.
 *
 * @return array
 */
function amowc_duplicate_statuses() {
	$statuses = array();

	foreach ( wc_get_order_statuses() as $key => $label ) {
		$status = 'wc-' === substr( $key, 0, 3 ) ? substr( $key, 3 ) : $key;
		if ( ! in_array( $status, array( 'refunded', 'failed', 'cancelled', 'checkout-draft' ), true ) ) {
			$statuses[ $status ] = $label;
		}
	}

	return $statuses;
}

/**
 * Status for a new duplicate, from the setting.
 *
 * @return string
 */
function amowc_duplicate_status() {
	$status = amowc_get_option( 'dup_status' );
	return array_key_exists( $status, amowc_duplicate_statuses() ) ? $status : 'pending';
}

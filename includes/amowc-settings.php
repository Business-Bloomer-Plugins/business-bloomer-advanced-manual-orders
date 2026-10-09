<?php
/**
 * Settings: WooCommerce > Settings > Advanced > Advanced Manual Orders.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_get_sections_advanced', 'amowc_add_section' );
add_filter( 'woocommerce_get_settings_advanced', 'amowc_section_settings', 10, 2 );

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
	$sections['amowc'] = __( 'Advanced Manual Orders', 'advanced-manual-orders-for-woocommerce' );
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
			'title' => __( 'Advanced Manual Orders', 'advanced-manual-orders-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => __( 'How orders you create in the admin behave.', 'advanced-manual-orders-for-woocommerce' ),
			'id'    => 'amowc_options',
		),
		array(
			'title'   => __( 'New order form', 'advanced-manual-orders-for-woocommerce' ),
			'desc'    => __( 'Ask for customer, currency and products to copy when you click "Add order"', 'advanced-manual-orders-for-woocommerce' ),
			'id'      => 'amowc_popup',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'    => __( 'Duplicate order status', 'advanced-manual-orders-for-woocommerce' ),
			'desc'     => __( 'Status of the copy made by "Duplicate". No emails are sent for it. On hold, Processing and Completed reduce stock, as for any order.', 'advanced-manual-orders-for-woocommerce' ),
			'id'       => 'amowc_dup_status',
			'type'     => 'select',
			'class'    => 'wc-enhanced-select',
			'options'  => amowc_duplicate_statuses(),
			'default'  => 'pending',
			'desc_tip' => true,
		),
		array(
			'title'   => __( 'Taxes and totals', 'advanced-manual-orders-for-woocommerce' ),
			'desc'    => __( 'Recalculate automatically when items, coupons or the address change', 'advanced-manual-orders-for-woocommerce' ),
			'id'      => 'amowc_auto_totals',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'   => __( 'Currency selector', 'advanced-manual-orders-for-woocommerce' ),
			'desc'    => __( 'Pick a currency on the "Add order" form and on unpaid orders', 'advanced-manual-orders-for-woocommerce' ),
			'id'      => 'amowc_currency',
			'type'    => 'checkbox',
			'default' => 'no',
		),
		array(
			'title'    => __( 'Currencies', 'advanced-manual-orders-for-woocommerce' ),
			'desc'     => __( 'Extra currencies to show at the top of the currency list. Currencies from WooPayments, WPML, Aelia, CURCY, FOX, YayCurrency, TIV.NET, Price Based on Country and Booster are added on their own.', 'advanced-manual-orders-for-woocommerce' ),
			'id'       => 'amowc_currencies',
			'type'     => 'multiselect',
			'class'    => 'wc-enhanced-select',
			'options'  => get_woocommerce_currencies(),
			'default'  => array(),
			'desc_tip' => true,
		),
		array(
			'title'    => __( 'Payment link', 'advanced-manual-orders-for-woocommerce' ),
			'desc'     => __( 'Customers can pay without logging in', 'advanced-manual-orders-for-woocommerce' ),
			'id'       => 'amowc_guest_pay',
			'type'     => 'checkbox',
			'default'  => 'yes',
			'desc_tip' => __( 'Only for orders created in the admin. Whoever has the link can see the order and pay it, the same as a guest order.', 'advanced-manual-orders-for-woocommerce' ),
		),
		array(
			'type' => 'sectionend',
			'id'   => 'amowc_options',
		),
	);

	// Until WooCommerce 11.3, see amowc-tax-fix.php.
	if ( amowc_same_price_fix_needed() ) {
		array_splice(
			$fields,
			-1,
			0,
			array(
				array(
					'title'    => __( 'Same price in every country', 'advanced-manual-orders-for-woocommerce' ),
					'desc'     => __( 'Fix product prices on manual orders when your store charges the same price, tax included, in every country', 'advanced-manual-orders-for-woocommerce' ),
					'id'       => 'amowc_same_price_fix',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'desc_tip' => __( 'For stores that set woocommerce_adjust_non_base_location_prices to false. Since WooCommerce 10.5, products added to a manual order are priced for the wrong country. WooCommerce fixes this in version 11.3, then this setting goes away.', 'advanced-manual-orders-for-woocommerce' ),
				),
			)
		);
	}

	return apply_filters( 'amowc_settings_fields', $fields );
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
		__( 'Store currencies', 'advanced-manual-orders-for-woocommerce' ) => $store,
		__( 'All currencies', 'advanced-manual-orders-for-woocommerce' )   => array_diff_key( $all, $store ),
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

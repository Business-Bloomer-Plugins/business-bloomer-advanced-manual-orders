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
			'desc'     => __( 'Extra currencies to show at the top of the currency list. Currencies from WooPayments, WPML, Aelia, CURCY, FOX, YayCurrency, TIV.NET, Price Based on Country and Booster are added on their own.', 'manual-order-superpowers-for-woocommerce' ),
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
 * The currencies this store sells in: the store currency first, then the ones
 * enabled in a multi-currency plugin, then the ones added in the settings.
 *
 * @return string[] Currency codes.
 */
function mosw_store_currencies() {
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

	$codes = array_merge( $codes, (array) mosw_get_option( 'currencies' ) );
	$codes = array_intersect( array_unique( array_filter( array_map( 'strval', $codes ) ) ), array_keys( get_woocommerce_currencies() ) );

	return array_values( (array) apply_filters( 'mosw_store_currencies', $codes ) );
}

/**
 * Every currency, code => name, the store's own currencies first.
 *
 * @return array
 */
function mosw_currencies() {
	$all   = get_woocommerce_currencies();
	$first = array();
	foreach ( mosw_store_currencies() as $code ) {
		$first[ $code ] = $all[ $code ];
	}

	return array_merge( $first, $all );
}

/**
 * Options for a currency dropdown: the store's currencies, then all the others.
 *
 * @param string $selected Selected currency code.
 */
function mosw_currency_options( $selected ) {
	$all    = mosw_currencies();
	$store  = array_intersect_key( $all, array_flip( mosw_store_currencies() ) );
	$groups = array(
		__( 'Store currencies', 'manual-order-superpowers-for-woocommerce' ) => $store,
		__( 'All currencies', 'manual-order-superpowers-for-woocommerce' )   => array_diff_key( $all, $store ),
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

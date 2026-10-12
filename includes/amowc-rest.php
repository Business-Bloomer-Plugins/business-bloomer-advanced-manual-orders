<?php
/**
 * REST routes used by the order screens, under amowc/v1.
 *
 * Every route needs a logged-in user who can edit orders, and the REST nonce the
 * order screen sends. The callbacks live with their feature, in the other files.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'amowc_register_routes' );

/**
 * Registers the routes.
 */
function amowc_register_routes() {
	$address = array();
	foreach ( array( 'country', 'state', 'postcode', 'city', 'address_1', 'address_2' ) as $field ) {
		$address[ $field ] = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	$term = array(
		'term' => array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		),
	);

	$routes = array(
		'/orders'                            => array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'amowc_rest_search_orders',
			'args'     => $term,
		),
		'/customers'                         => array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'amowc_rest_search_customers',
			'args'     => $term,
		),
		'/products'                          => array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'amowc_rest_search_products',
			'args'     => $term + array(
				'page' => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
			),
		),
		'/orders/(?P<id>\d+)/items'          => array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => 'amowc_rest_add_product',
			'args'     => $address + array(
				// "123" or, for a variation with "Any" attributes, "123|color=red".
				'product' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				// Unsaved changes to the items on screen, serialized like WooCommerce sends them.
				'items'   => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		),
		'/orders/(?P<id>\d+)/currency'       => array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => 'amowc_rest_set_currency',
			'args'     => array(
				'currency' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		),
		'/orders/(?P<id>\d+)/shipping-rates' => array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'amowc_rest_shipping_rates',
			'args'     => $address,
		),
		'/orders/(?P<id>\d+)/shipping'       => array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => 'amowc_rest_add_shipping',
			'args'     => $address + array(
				// Package index => chosen rate ID.
				'rates' => array(
					'type'    => 'object',
					'default' => array(),
				),
			),
		),
		'/settings/currency-selector'        => array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'amowc_rest_toggle_currency',
			'permission_callback' => 'amowc_rest_can_manage',
			'args'                => array(
				'enabled' => array(
					'type'     => 'boolean',
					'required' => true,
				),
			),
		),
	);

	foreach ( $routes as $route => $args ) {
		register_rest_route( 'amowc/v1', $route, $args + array( 'permission_callback' => 'amowc_rest_can_edit' ) );
	}
}

/**
 * Whether the current user can create and edit orders.
 *
 * @return bool
 */
function amowc_rest_can_edit() {
	return current_user_can( 'edit_shop_orders' );
}

/**
 * Whether the current user can change WooCommerce settings.
 *
 * @return bool
 */
function amowc_rest_can_manage() {
	return current_user_can( 'manage_woocommerce' );
}

/**
 * The order a route is about. It must be a regular order, editable unless $editable is false.
 *
 * @param WP_REST_Request $request  Request with the order ID.
 * @param bool            $editable Whether the order must still be editable.
 * @return WC_Order|WP_Error
 */
function amowc_rest_order( $request, $editable = true ) {
	$order = wc_get_order( absint( $request['id'] ) );

	if ( ! $order || 'shop_order' !== $order->get_type() || ( $editable && ! $order->is_editable() ) ) {
		return new WP_Error( 'amowc_order', __( 'This order can not be edited.', 'business-bloomer-advanced-manual-orders' ), array( 'status' => 400 ) );
	}

	return $order;
}

/**
 * The address fields of a request.
 *
 * @param WP_REST_Request $request Request.
 * @return array country, state, postcode, city, address_1, address_2.
 */
function amowc_rest_address( $request ) {
	$address = array();
	foreach ( array( 'country', 'state', 'postcode', 'city', 'address_1', 'address_2' ) as $field ) {
		$address[ $field ] = (string) $request[ $field ];
	}
	return $address;
}

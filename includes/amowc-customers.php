<?php
/**
 * Customer search: registered customers and guests, by name, email, phone, company or
 * address (billing or shipping), with their addresses ready to fill in.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customers for the customer pickers.
 *
 * @param WP_REST_Request $request Request.
 * @return array
 */
function amowc_rest_search_customers( $request ) {
	return array( 'results' => amowc_find_customers( $request['term'] ) );
}

/**
 * Registered customers and guests matching a search, with their addresses.
 * Empty search: the customers of the latest orders.
 *
 * @param string $term Name, email, phone, company, address, city or postcode.
 * @return array[] id ("12", or "o:345" for a guest), text, billing, shipping.
 */
function amowc_find_customers( $term ) {
	$term    = trim( (string) $term );
	$limit   = (int) apply_filters( 'amowc_customers_limit', 20 );
	$entries = array();

	if ( '' === $term ) {
		$orders = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array_keys( wc_get_order_statuses() ),
				'limit'   => 40,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		foreach ( $orders as $order ) {
			amowc_add_customer_entry( $entries, $order );
		}

		return amowc_filter_customers( array_values( array_slice( $entries, 0, 10 ) ), $term );
	}

	foreach ( amowc_search_customer_ids( $term, $limit ) as $user_id ) {
		amowc_add_customer_entry( $entries, null, $user_id );
	}

	// Orders hold the addresses and phone numbers of guests, and of customers who never saved them.
	$ids = array_merge( array_map( 'absint', wc_order_search( $term ) ), amowc_address_order_ids( $term ) );
	$ids = array_unique( $ids );
	rsort( $ids );

	// Loaded together, newest first: one read instead of one per order.
	$orders = $ids ? wc_get_orders(
		array(
			'type'     => 'shop_order',
			'status'   => array_keys( wc_get_order_statuses() ),
			'post__in' => array_slice( $ids, 0, 60 ),
			'limit'    => 60,
			'orderby'  => 'ID',
			'order'    => 'DESC',
		)
	) : array();

	foreach ( $orders as $order ) {
		if ( count( $entries ) >= $limit ) {
			break;
		}
		amowc_add_customer_entry( $entries, $order );
	}

	return amowc_filter_customers( array_values( array_slice( $entries, 0, $limit ) ), $term );
}

/**
 * Lets other plugins add to the customer search results, for example a staff note.
 *
 * @param array[] $customers Results: id, text, billing, shipping.
 * @param string  $term      Search.
 * @return array[]
 */
function amowc_filter_customers( $customers, $term ) {
	/**
	 * Customer search results, for the customer pickers.
	 *
	 * @param array[] $customers Each with id ("12", or "o:345" for a guest), text, billing, shipping.
	 * @param string  $term      Search, empty for the latest customers.
	 */
	return (array) apply_filters( 'amowc_customers', $customers, $term );
}

/**
 * Address fields the search looks in, besides WooCommerce's own order and customer search.
 *
 * @return string[] User meta keys, and order fields (the order meta key without its leading underscore).
 */
function amowc_search_fields() {
	return array( 'billing_email', 'billing_phone', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_city', 'billing_postcode', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_city', 'shipping_postcode' );
}

/**
 * Registered customers matching a search: WooCommerce's own search (login, email, name),
 * plus their saved billing and shipping details (amowc_search_fields()).
 *
 * @param string $term  Search.
 * @param int    $limit Maximum number of customers.
 * @return int[]
 */
function amowc_search_customer_ids( $term, $limit ) {
	$ids = WC_Data_Store::load( 'customer' )->search_customers( $term, $limit );

	$meta = array( 'relation' => 'OR' );
	foreach ( amowc_search_fields() as $key ) {
		$meta[] = array(
			'key'     => $key,
			'value'   => $term,
			'compare' => 'LIKE',
		);
	}

	$phone = amowc_phone_pattern( $term );
	if ( $phone ) {
		$meta[] = array(
			'key'     => 'billing_phone',
			'value'   => $phone,
			'compare' => 'REGEXP',
		);
	}

	$query = new WP_User_Query(
		array(
			'fields'     => 'ID',
			'number'     => $limit,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- customer search, admin only.
			'meta_query' => $meta,
		)
	);

	return array_slice( wp_parse_id_list( array_merge( $ids, (array) $query->get_results() ) ), 0, $limit );
}

/**
 * A search that looks like a phone number, as a pattern matching it however it was saved:
 * "0915550101" finds "091-555-0101" and "091 555 0101".
 *
 * @param string $term Search.
 * @return string Empty when it's not a phone number.
 */
function amowc_phone_pattern( $term ) {
	$digits = preg_replace( '/\D/', '', $term );

	if ( strlen( $digits ) < 4 || ! preg_match( '/^[\d\s+().\-\/]+$/', $term ) ) {
		return '';
	}

	return implode( '[^0-9]*', str_split( $digits ) );
}

/**
 * Orders whose billing or shipping details (amowc_search_fields()) contain the search, or whose
 * billing phone matches a phone number search however it was saved. One query.
 *
 * @param string $term Search.
 * @return int[]
 */
function amowc_address_order_ids( $term ) {
	$hpos    = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	$clauses = array( 'relation' => 'OR' );

	foreach ( amowc_search_fields() as $field ) {
		$clauses[] = array(
			$hpos ? 'field' : 'key' => $hpos ? $field : '_' . $field,
			'value'                 => $term,
			'compare'               => 'LIKE',
		);
	}

	$pattern = amowc_phone_pattern( $term );
	if ( $pattern ) {
		$clauses[] = array(
			$hpos ? 'field' : 'key' => $hpos ? 'billing_phone' : '_billing_phone',
			'value'                 => $pattern,
			'compare'               => 'REGEXP',
		);
	}

	if ( $hpos ) {
		$ids = wc_get_orders(
			array(
				'type'        => 'shop_order',
				'status'      => array_keys( wc_get_order_statuses() ),
				'limit'       => 60,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'ids',
				'field_query' => $clauses,
			)
		);
	} else {
		// Orders stored as posts: wc_get_orders() doesn't take a meta_query there.
		$ids = get_posts(
			array(
				'post_type'   => 'shop_order',
				'post_status' => array_keys( wc_get_order_statuses() ),
				'numberposts' => 60,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- customer search, admin only.
				'meta_query'  => $clauses,
			)
		);
	}

	return array_map( 'absint', $ids );
}

/**
 * Adds a customer to the results once: a registered customer, or a guest from one of their orders.
 *
 * @param array         $entries Results so far, by key.
 * @param WC_Order|null $order   Order to read a guest, or the registered customer, from.
 * @param int           $user_id Registered customer, when there's no order.
 */
function amowc_add_customer_entry( &$entries, $order, $user_id = 0 ) {
	if ( $order && $order->get_customer_id() ) {
		$user_id = $order->get_customer_id();
	}

	if ( $user_id ) {
		$key = 'u:' . $user_id;
		if ( ! isset( $entries[ $key ] ) ) {
			$entry = amowc_registered_customer_entry( $user_id );
			if ( $entry ) {
				$entries[ $key ] = $entry;
			}
		}
		return;
	}

	if ( ! $order ) {
		return;
	}

	// One entry per guest, recognised by email, or phone when there's no email.
	$who = strtolower( $order->get_billing_email() ? $order->get_billing_email() : preg_replace( '/\D/', '', $order->get_billing_phone() ) );
	if ( '' === $who || isset( $entries[ 'g:' . $who ] ) ) {
		return;
	}

	$billing  = $order->get_address( 'billing' );
	$shipping = $order->get_address( 'shipping' );

	$entries[ 'g:' . $who ] = array(
		'id'       => 'o:' . $order->get_id(),
		'text'     => amowc_customer_label( $billing, true, $shipping ),
		'billing'  => $billing,
		'shipping' => $shipping,
	);
}

/**
 * A registered customer, with their saved addresses. Never saved any: the ones of their latest order.
 *
 * @param int $user_id User ID.
 * @return array|null
 */
function amowc_registered_customer_entry( $user_id ) {
	$customer = new WC_Customer( $user_id );

	if ( ! $customer->get_id() ) {
		return null;
	}

	$billing  = $customer->get_billing();
	$shipping = $customer->get_shipping();

	if ( ! $billing['country'] ) {
		$last = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'type'        => 'shop_order',
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		if ( $last ) {
			$billing  = $last[0]->get_address( 'billing' );
			$shipping = $last[0]->get_address( 'shipping' );
		}
	}

	if ( ! $billing['email'] ) {
		$billing['email'] = $customer->get_email();
	}
	if ( ! $billing['first_name'] && ! $billing['last_name'] ) {
		$billing['first_name'] = $customer->get_first_name();
		$billing['last_name']  = $customer->get_last_name();
	}

	return array(
		'id'       => (string) $user_id,
		'text'     => amowc_customer_label( $billing, false, $shipping ),
		'billing'  => $billing,
		'shipping' => $shipping,
	);
}

/**
 * One line of the customer list: name, email, phone and address, plus where orders ship to
 * when that's someone or somewhere else, so similar customers can be told apart.
 *
 * @param array $billing  Billing address.
 * @param bool  $guest    Whether this is a guest.
 * @param array $shipping Shipping address.
 * @return string
 */
function amowc_customer_label( $billing, $guest, $shipping = array() ) {
	$name  = trim( $billing['first_name'] . ' ' . $billing['last_name'] );
	$parts = array_filter( array( $name ? $name : $billing['company'], $billing['email'], $billing['phone'], amowc_address_line( $billing ) ) );

	$ship_to = amowc_address_line( $shipping, true );
	if ( $ship_to && amowc_address_line( $billing, true ) !== $ship_to ) {
		/* translators: %s: shipping name and address */
		$parts[] = sprintf( __( 'Ships to %s', 'business-bloomer-advanced-manual-orders' ), $ship_to );
	}

	if ( $guest ) {
		$parts[] = __( 'Guest', 'business-bloomer-advanced-manual-orders' );
	}

	return html_entity_decode( implode( ' · ', $parts ), ENT_QUOTES, 'UTF-8' );
}

/**
 * An address on one line: street, postcode and city, country. With the name and company too if asked.
 *
 * @param array $address   Address.
 * @param bool  $with_name Whether to start with the name and company.
 * @return string
 */
function amowc_address_line( $address, $with_name = false ) {
	$address = wp_parse_args( (array) $address, array_fill_keys( array( 'first_name', 'last_name', 'company', 'address_1', 'postcode', 'city', 'country' ), '' ) );
	$parts   = array( $address['address_1'], trim( $address['postcode'] . ' ' . $address['city'] ), $address['country'] );

	if ( $with_name ) {
		array_unshift( $parts, trim( $address['first_name'] . ' ' . $address['last_name'] ), $address['company'] );
	}

	return implode( ', ', array_filter( $parts ) );
}

/**
 * Sets the customer picked on the "Add order" form: a registered customer ID, or "o:ID"
 * for a guest, whose addresses come from that order.
 *
 * @param WC_Order $order  New order.
 * @param string   $picked Picked value.
 */
function amowc_apply_picked_customer( $order, $picked ) {
	if ( 0 === strpos( $picked, 'o:' ) ) {
		$from = wc_get_order( absint( substr( $picked, 2 ) ) );
		if ( $from && 'shop_order' === $from->get_type() && ! $from->get_customer_id() ) {
			$order->set_address( $from->get_address( 'billing' ), 'billing' );
			$order->set_address( $from->get_address( 'shipping' ), 'shipping' );
		}
		return;
	}

	if ( absint( $picked ) ) {
		$entry = amowc_registered_customer_entry( absint( $picked ) );
		if ( $entry ) {
			$order->set_customer_id( absint( $picked ) );
			$order->set_address( $entry['billing'], 'billing' );
			$order->set_address( $entry['shipping'], 'shipping' );
		}
	}
}

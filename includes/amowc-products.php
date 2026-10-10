<?php
/**
 * "Add a product" box on the order screen: best sellers first, variations listed
 * one by one, picked products added straight to the order.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_amowc_search_products', 'amowc_ajax_search_products' );
add_action( 'wp_ajax_amowc_add_product', 'amowc_ajax_add_product' );

/**
 * One line of the product list: name or attributes, price, SKU, stock.
 *
 * @param WC_Product $product   Product or variation.
 * @param bool       $variation True to show the attributes instead of the name.
 * @param array      $chosen    Values picked for the variation's "Any" attributes.
 * @return string
 */
function amowc_product_label( $product, $variation = false, $chosen = array() ) {
	$parts = array();

	if ( $variation ) {
		$attributes = array();
		$parent     = wc_get_product( $product->get_parent_id() );
		foreach ( array_merge( $product->get_variation_attributes( false ), $chosen ) as $name => $value ) {
			$label = wc_attribute_label( $name, $parent ? $parent : $product );
			if ( '' === $value ) {
				/* translators: %s: attribute name, e.g. Size */
				$value = sprintf( __( 'Any %s', 'business-bloomer-advanced-manual-orders' ), $label );
			} elseif ( taxonomy_exists( $name ) ) {
				$term  = get_term_by( 'slug', $value, $name );
				$value = $term ? $term->name : $value;
			}
			$attributes[] = $value;
		}
		$parts[] = $attributes ? implode( ', ', $attributes ) : $product->get_name();
	} else {
		$parts[] = $product->get_name();
	}

	$price   = $product->get_price();
	$parts[] = '' === $price ? __( 'No price', 'business-bloomer-advanced-manual-orders' ) : html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' );

	if ( $product->get_sku() ) {
		$parts[] = $product->get_sku();
	}

	if ( ! $product->is_in_stock() ) {
		$parts[] = __( 'Out of stock', 'business-bloomer-advanced-manual-orders' );
	} elseif ( $product->managing_stock() ) {
		/* translators: %s: stock quantity */
		$parts[] = sprintf( __( '%s in stock', 'business-bloomer-advanced-manual-orders' ), wc_stock_amount( $product->get_stock_quantity() ) );
	}

	if ( 'private' === $product->get_status() && ! $variation ) {
		$parts[] = __( 'Private', 'business-bloomer-advanced-manual-orders' );
	}

	return implode( ' · ', $parts );
}

/**
 * Every combination of values for a variation's "Any" attributes, so each one
 * can be picked straight from the list. Too many combinations: just the variation.
 *
 * @param WC_Product_Variation $variation Variation.
 * @param WC_Product_Variable  $variable  Its variable product.
 * @return array[] Lists of attribute name => value. One empty list when nothing is "Any".
 */
function amowc_any_combinations( $variation, $variable ) {
	$options = amowc_attribute_options( $variable );
	$combos  = array( array() );

	foreach ( $variation->get_variation_attributes( false ) as $name => $value ) {
		if ( '' !== $value || empty( $options[ $name ] ) ) {
			continue;
		}
		$next = array();
		foreach ( $combos as $combo ) {
			foreach ( $options[ $name ] as $option ) {
				$next[] = array_merge( $combo, array( $name => $option ) );
			}
		}
		$combos = $next;

		if ( count( $combos ) > (int) apply_filters( 'amowc_max_any_combinations', 30 ) ) {
			return array( array() );
		}
	}

	return $combos;
}

/**
 * Options of a variable product's attributes, keyed like a variation's attributes.
 *
 * @param WC_Product $variable Variable product.
 * @return array Attribute key => options.
 */
function amowc_attribute_options( $variable ) {
	$options = array();
	foreach ( $variable->get_variation_attributes() as $name => $values ) {
		$options[ sanitize_title( $name ) ] = array_values( $values );
	}
	return $options;
}

/**
 * The "Any" attribute values sent with a picked variation, checked against the product.
 *
 * @param WC_Product $product Variation.
 * @param string     $query   name=value pairs.
 * @return array Attribute name => value.
 */
function amowc_parse_any_values( $product, $query ) {
	$values = array();
	parse_str( $query, $values );

	$parent  = wc_get_product( $product->get_parent_id() );
	$options = $parent ? amowc_attribute_options( $parent ) : array();
	$chosen  = array();

	foreach ( $product->get_variation_attributes( false ) as $name => $value ) {
		if ( '' === $value && isset( $values[ $name ], $options[ $name ] ) && in_array( $values[ $name ], $options[ $name ], true ) ) {
			$chosen[ $name ] = $values[ $name ];
		}
	}

	return $chosen;
}

/**
 * Product list for the "Add a product" box. Empty search: best sellers.
 * Variable products come as a group with one entry per variation.
 */
function amowc_ajax_search_products() {
	check_ajax_referer( 'amowc_admin', 'security' );

	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( -1 );
	}

	$term     = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
	$page     = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1;
	$per_page = (int) apply_filters( 'amowc_products_per_page', 20 );

	// Grouped and external products can't go on an order.
	$types = array_values( array_diff( array_keys( wc_get_product_types() ), array( 'grouped', 'external' ) ) );

	$args = array(
		'post_type'      => 'product',
		'post_status'    => current_user_can( 'read_private_products' ) ? array( 'publish', 'private' ) : array( 'publish' ),
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'fields'         => 'ids',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- product type filter, same as the core product search.
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $types,
			),
		),
		// Best sellers first. Products without a sales count still show, at the end.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sorting by sales.
		'meta_query'     => array(
			'relation' => 'OR',
			'sales'    => array(
				'key'  => 'total_sales',
				'type' => 'NUMERIC',
			),
			array(
				'key'     => 'total_sales',
				'compare' => 'NOT EXISTS',
			),
		),
		'orderby'        => array(
			'sales' => 'DESC',
			'title' => 'ASC',
		),
	);

	$matched = null;

	if ( '' !== $term ) {
		// Same search as WooCommerce: name, SKU, GTIN, and variations.
		$ids = array_filter( array_map( 'absint', WC_Data_Store::load( 'product' )->search_products( $term, '', true, false ) ) );

		if ( ! $ids ) {
			wp_send_json(
				array(
					'results'    => array(),
					'pagination' => array( 'more' => false ),
				)
			);
		}

		$matched = array_flip( $ids );
		$parents = array();
		foreach ( $ids as $id ) {
			$parent    = wp_get_post_parent_id( $id );
			$parents[] = $parent ? $parent : $id;
		}
		$args['post__in'] = array_values( array_unique( $parents ) );
	}

	$query   = new WP_Query( $args );
	$results = array();

	foreach ( $query->posts as $id ) {
		$product = wc_get_product( $id );

		if ( ! $product ) {
			continue;
		}

		if ( ! $product->is_type( 'variable' ) ) {
			$results[] = array(
				'id'   => $product->get_id(),
				'text' => amowc_product_label( $product ),
			);
			continue;
		}

		// A match on the product name shows every variation, a match on a variation shows that one.
		$children = array();
		foreach ( $product->get_children() as $child_id ) {
			if ( null !== $matched && ! isset( $matched[ $id ] ) && ! isset( $matched[ $child_id ] ) ) {
				continue;
			}
			$child = wc_get_product( $child_id );
			if ( ! $child ) {
				continue;
			}
			foreach ( amowc_any_combinations( $child, $product ) as $chosen ) {
				$children[] = array(
					'id'   => $chosen ? $child->get_id() . '|' . http_build_query( $chosen ) : $child->get_id(),
					'text' => amowc_product_label( $child, true, $chosen ),
				);
			}
		}

		if ( $children ) {
			$results[] = array(
				'text'     => $product->get_name(),
				'children' => $children,
			);
		}
	}

	wp_send_json(
		array(
			'results'    => $results,
			'pagination' => array( 'more' => $page < $query->max_num_pages ),
		)
	);
}

/**
 * Adds a product to the order. When it's already there, the quantity goes up instead.
 */
function amowc_ajax_add_product() {
	check_ajax_referer( 'amowc_admin', 'security' );

	$order = isset( $_POST['order_id'] ) ? wc_get_order( absint( $_POST['order_id'] ) ) : null;
	// "123" or, for a variation with "Any" attributes, "123|color=red".
	$picked  = isset( $_POST['product_id'] ) ? explode( '|', sanitize_text_field( wp_unslash( $_POST['product_id'] ) ), 2 ) : array( 0 );
	$product = wc_get_product( absint( $picked[0] ) );
	$chosen  = $product && isset( $picked[1] ) ? amowc_parse_any_values( $product, $picked[1] ) : array();

	if ( isset( $picked[1] ) && substr_count( $picked[1], '=' ) !== count( $chosen ) ) {
		wp_send_json_error( array( 'message' => __( 'Pick a product or a variation.', 'business-bloomer-advanced-manual-orders' ) ) );
	}

	// One per pick. Pick again for more, or edit the quantity on the line.
	$qty = 1;

	if ( ! $order || ! current_user_can( 'edit_shop_orders' ) || ! $order->is_editable() ) {
		wp_send_json_error( array( 'message' => __( 'This order can not be edited.', 'business-bloomer-advanced-manual-orders' ) ) );
	}

	if ( ! $product || $product->is_type( 'variable' ) ) {
		wp_send_json_error( array( 'message' => __( 'Pick a product or a variation.', 'business-bloomer-advanced-manual-orders' ) ) );
	}

	// Unsaved changes to the items on screen are kept, like WooCommerce does.
	if ( ! empty( $_POST['items'] ) ) {
		$items = array();
		parse_str( wp_unslash( $_POST['items'] ), $items ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by wc_save_order_items().
		wc_save_order_items( $order->get_id(), $items );
		$order = wc_get_order( $order->get_id() );
	}

	$validation = apply_filters( 'woocommerce_ajax_add_order_item_validation', new WP_Error(), $product, $order, $qty ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook, fired as core's Add item does.
	if ( $validation->get_error_code() ) {
		wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
	}

	$existing = null;
	foreach ( $order->get_items() as $item ) {
		if ( ( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() ) !== $product->get_id() ) {
			continue;
		}
		foreach ( $chosen as $name => $value ) {
			if ( $item->get_meta( $name ) !== $value ) {
				continue 2;
			}
		}
		$existing = $item;
		break;
	}

	if ( $existing ) {
		$old = $existing->get_quantity();
		$new = $old + $qty;

		// Same unit price as the line already has, edited or not.
		$existing->set_quantity( $new );
		$existing->set_subtotal( $old ? $existing->get_subtotal() / $old * $new : 0 );
		$existing->set_total( $old ? $existing->get_total() / $old * $new : 0 );
		$existing->save();
		$order->calculate_totals( false );

		/* translators: 1: product name, 2: new quantity */
		$order->add_order_note( sprintf( __( 'Quantity of %1$s changed to %2$s.', 'business-bloomer-advanced-manual-orders' ), $product->get_formatted_name(), $new ), false, true );
	} else {
		$args = array( 'order' => $order );
		if ( $chosen ) {
			$args['variation'] = array_merge( $product->get_attributes(), $chosen );
		}
		$item_id = $order->add_product( $product, $qty, $args );
		$item    = apply_filters( 'woocommerce_ajax_order_item', $order->get_item( $item_id ), $item_id, $order, $product ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook, fired as core's Add item does.

		do_action( 'woocommerce_ajax_add_order_item_meta', $item_id, $item, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook, fired as core's Add item does.

		/* translators: %s: product name */
		$order->add_order_note( sprintf( __( 'Added line items: %s', 'business-bloomer-advanced-manual-orders' ), $product->get_formatted_name() ), false, true );

		do_action( 'woocommerce_ajax_order_items_added', array( $item_id => $item ), $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook, fired as core's Add item does.
	}

	wp_send_json_success( amowc_order_items_html( $order->get_id() ) );
}

/**
 * The order items box and notes, rendered by WooCommerce's own templates.
 *
 * @param int $order_id Order ID.
 * @return array html, notes_html.
 */
function amowc_order_items_html( $order_id ) {
	$order = wc_get_order( $order_id );

	ob_start();
	include WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-items.php';
	$html = ob_get_clean();

	$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
	ob_start();
	include WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-notes.php';
	$notes_html = ob_get_clean();

	return array(
		'html'       => $html,
		'notes_html' => $notes_html,
	);
}

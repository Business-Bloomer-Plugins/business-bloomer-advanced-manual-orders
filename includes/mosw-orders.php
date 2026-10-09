<?php
/**
 * New drafts: from the "Add order" form, duplicate, same customer, same products. Order currency.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_mosw_new_order', 'mosw_handle_new_order_form' );
add_filter( 'woocommerce_order_actions', 'mosw_order_actions', 10, 2 );
add_action( 'woocommerce_order_action_mosw_duplicate', 'mosw_action_duplicate' );
add_action( 'woocommerce_order_action_mosw_same_customer', 'mosw_action_same_customer' );
add_action( 'woocommerce_order_action_mosw_same_products', 'mosw_action_same_products' );
add_filter( 'redirect_post_location', 'mosw_redirect_to_new_draft' );
add_filter( 'woocommerce_redirect_order_location', 'mosw_redirect_to_new_draft' );
add_action( 'woocommerce_admin_order_data_after_order_details', 'mosw_currency_field' );
add_action( 'wp_ajax_mosw_set_currency', 'mosw_ajax_set_currency' );

/**
 * Creates a draft order, the same kind WooCommerce makes when you click "Add order".
 * It shows the "Create" button and is removed after a few days if never created.
 *
 * @param array $args {
 *     @type int           $customer_id Customer to assign, with their saved addresses.
 *     @type string        $currency    Order currency.
 *     @type WC_Order|null $source      Order to copy from.
 *     @type string        $copy        What to copy from $source: all, customer, products.
 * }
 * @return WC_Order
 */
function mosw_create_draft( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'customer_id' => 0,
			'currency'    => '',
			'source'      => null,
			'copy'        => '',
		)
	);

	// Saved bare first, exactly like WooCommerce does, then loaded so every change below is stored.
	$draft = new WC_Order();
	$draft->set_object_read( false );
	$draft->set_status( 'auto-draft' );
	$draft->set_created_via( 'admin' );
	$draft->save();

	$order = wc_get_order( $draft->get_id() );
	$order->set_currency( $args['currency'] ? $args['currency'] : get_woocommerce_currency() );
	$order->set_prices_include_tax( wc_prices_include_tax() );

	$source = $args['source'] instanceof WC_Order ? $args['source'] : null;

	if ( $source && in_array( $args['copy'], array( 'all', 'customer' ), true ) ) {
		$order->set_customer_id( $source->get_customer_id() );
		$order->set_address( $source->get_address( 'billing' ), 'billing' );
		$order->set_address( $source->get_address( 'shipping' ), 'shipping' );
		$order->set_currency( $source->get_currency() );
	} elseif ( $args['customer_id'] ) {
		mosw_set_customer( $order, $args['customer_id'] );
	}

	if ( $source && 'all' === $args['copy'] ) {
		$order->set_prices_include_tax( $source->get_prices_include_tax() );
		$order->set_payment_method( $source->get_payment_method() );
		$order->set_payment_method_title( $source->get_payment_method_title() );
		$order->set_customer_note( $source->get_customer_note() );

		// Custom fields only. Underscore meta holds payment and system data that belongs to the original.
		foreach ( $source->get_meta_data() as $meta ) {
			if ( '_' !== substr( $meta->key, 0, 1 ) ) {
				$order->add_meta_data( $meta->key, $meta->value );
			}
		}
	}

	$order->save();

	if ( $source && in_array( $args['copy'], array( 'all', 'products' ), true ) ) {
		$fresh = 'products' === $args['copy'];
		mosw_copy_items( $source, $order, $fresh );

		if ( $fresh ) {
			$order->calculate_totals();
		} else {
			// Keep every tax amount as it was, even ones edited by hand.
			$order->update_taxes();
			$order->calculate_totals( false );
		}

		/* translators: %s: order number */
		$order->add_order_note( sprintf( __( 'Created from order #%s.', 'manual-order-superpowers-for-woocommerce' ), $source->get_order_number() ) );
	}

	do_action( 'mosw_draft_created', $order, $args );

	return $order;
}

/**
 * Assigns a customer and loads their saved addresses.
 *
 * @param WC_Order $order       Order.
 * @param int      $customer_id Customer user ID.
 */
function mosw_set_customer( $order, $customer_id ) {
	$customer = new WC_Customer( $customer_id );

	if ( ! $customer->get_id() ) {
		return;
	}

	$order->set_customer_id( $customer->get_id() );
	$order->set_address( $customer->get_billing(), 'billing' );
	$order->set_address( $customer->get_shipping(), 'shipping' );

	if ( ! $order->get_billing_email() ) {
		$order->set_billing_email( $customer->get_email() );
	}
}

/**
 * Copies line items from one order to another.
 *
 * @param WC_Order $from  Source order.
 * @param WC_Order $to    Target order.
 * @param bool     $fresh True: products only, at today's prices. False: every line as it was.
 */
function mosw_copy_items( $from, $to, $fresh ) {
	// Tax lines are rebuilt from the copied items.
	$types = $fresh ? array( 'line_item' ) : array( 'line_item', 'fee', 'shipping', 'coupon' );

	// Stock bookkeeping of the original order, never copied.
	$skip_meta = array( '_reduced_stock', '_restock_refunded_items' );

	foreach ( $from->get_items( $types ) as $item ) {
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;

		if ( $fresh && ! $product ) {
			continue;
		}

		$class = get_class( $item );
		$new   = new $class();
		$data  = $item->get_data();
		unset( $data['id'], $data['order_id'], $data['meta_data'] );
		$new->set_props( $data );

		foreach ( $item->get_meta_data() as $meta ) {
			if ( ! in_array( $meta->key, $skip_meta, true ) ) {
				$new->add_meta_data( $meta->key, $meta->value );
			}
		}

		if ( $fresh ) {
			$total = wc_get_price_excluding_tax(
				$product,
				array(
					'qty'   => $item->get_quantity(),
					'order' => $to,
				)
			);
			$new->set_subtotal( $total );
			$new->set_total( $total );
			$new->set_taxes( false );
		}

		$to->add_item( $new );
	}

	$to->save();
}

/**
 * Handles the "Add order" form.
 */
function mosw_handle_new_order_form() {
	check_admin_referer( 'mosw_new_order' );

	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to create orders.', 'manual-order-superpowers-for-woocommerce' ) );
	}

	$currency = isset( $_POST['mosw_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['mosw_currency'] ) ) : '';
	$source   = ! empty( $_POST['mosw_copy_from'] ) ? wc_get_order( absint( $_POST['mosw_copy_from'] ) ) : null;

	$order = mosw_create_draft(
		array(
			'customer_id' => isset( $_POST['mosw_customer'] ) ? absint( $_POST['mosw_customer'] ) : 0,
			'currency'    => array_key_exists( $currency, mosw_currencies() ) ? $currency : '',
			'source'      => $source && 'shop_order' === $source->get_type() ? $source : null,
			'copy'        => 'products',
		)
	);

	wp_safe_redirect( $order->get_edit_order_url() );
	exit;
}

/**
 * Adds the new-draft actions to the order actions box.
 *
 * @param array    $actions Order actions.
 * @param WC_Order $order   Order being edited.
 * @return array
 */
function mosw_order_actions( $actions, $order = null ) {
	if ( $order && $order->get_id() && 'auto-draft' !== $order->get_status() ) {
		$actions['mosw_duplicate']     = __( 'Duplicate order', 'manual-order-superpowers-for-woocommerce' );
		$actions['mosw_same_customer'] = __( 'New order for this customer', 'manual-order-superpowers-for-woocommerce' );
		$actions['mosw_same_products'] = __( 'New order with these products', 'manual-order-superpowers-for-woocommerce' );
	}
	return $actions;
}

/**
 * Remembers the draft to open after the order is saved.
 *
 * @param WC_Order|null $order Draft, or null to read it.
 * @return WC_Order|null
 */
function mosw_new_draft( $order = null ) {
	static $draft = null;
	if ( $order ) {
		$draft = $order;
	}
	return $draft;
}

/**
 * "Duplicate order" action.
 *
 * @param WC_Order $order Order.
 */
function mosw_action_duplicate( $order ) {
	mosw_new_draft(
		mosw_create_draft(
			array(
				'source' => $order,
				'copy'   => 'all',
			)
		)
	);
}

/**
 * "New order for this customer" action.
 *
 * @param WC_Order $order Order.
 */
function mosw_action_same_customer( $order ) {
	mosw_new_draft(
		mosw_create_draft(
			array(
				'source' => $order,
				'copy'   => 'customer',
			)
		)
	);
}

/**
 * "New order with these products" action.
 *
 * @param WC_Order $order Order.
 */
function mosw_action_same_products( $order ) {
	mosw_new_draft(
		mosw_create_draft(
			array(
				'source' => $order,
				'copy'   => 'products',
			)
		)
	);
}

/**
 * Opens the new draft after one of the actions above.
 *
 * @param string $location Redirect URL.
 * @return string
 */
function mosw_redirect_to_new_draft( $location ) {
	$draft = mosw_new_draft();
	return $draft ? $draft->get_edit_order_url() : $location;
}

/**
 * Currency picker in the order data box, while the order is unpaid and editable.
 *
 * @param WC_Order $order Order.
 */
function mosw_currency_field( $order ) {
	if ( ! $order->is_editable() || $order->get_date_paid() ) {
		return;
	}

	$current    = $order->get_currency();
	$currencies = mosw_currencies( $current );

	if ( count( $currencies ) < 2 ) {
		return;
	}
	?>
	<p class="form-field form-field-wide mosw-currency">
		<label for="mosw_currency"><?php esc_html_e( 'Currency', 'manual-order-superpowers-for-woocommerce' ); ?></label>
		<select id="mosw_currency" name="mosw_currency" class="wc-enhanced-select">
			<?php foreach ( $currencies as $code => $name ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $current ); ?>><?php echo esc_html( $name . ' (' . get_woocommerce_currency_symbol( $code ) . ')' ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * Saves the currency as soon as it changes, so items added next use it.
 */
function mosw_ajax_set_currency() {
	check_ajax_referer( 'mosw_admin', 'security' );

	$order    = isset( $_POST['order_id'] ) ? wc_get_order( absint( $_POST['order_id'] ) ) : null;
	$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

	if ( ! $order || ! current_user_can( 'edit_shop_orders' ) || ! $order->is_editable() || $order->get_date_paid() ) {
		wp_send_json_error();
	}

	if ( ! array_key_exists( $currency, mosw_currencies( $order->get_currency() ) ) ) {
		wp_send_json_error();
	}

	$old = $order->get_currency();

	if ( $old !== $currency ) {
		$order->set_currency( $currency );
		$order->save();

		if ( 'auto-draft' !== $order->get_status() ) {
			/* translators: 1: old currency code, 2: new currency code */
			$order->add_order_note( sprintf( __( 'Currency changed from %1$s to %2$s. Item prices were not converted.', 'manual-order-superpowers-for-woocommerce' ), $old, $currency ) );
		}
	}

	wp_send_json_success();
}

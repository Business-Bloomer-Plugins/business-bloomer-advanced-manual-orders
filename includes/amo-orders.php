<?php
/**
 * New drafts: from the "Add order" form, duplicate, same customer, same products. Order currency.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_amo_new_order', 'amo_handle_new_order_form' );
add_filter( 'woocommerce_order_actions', 'amo_order_actions', 10, 2 );
add_action( 'woocommerce_order_action_amo_duplicate', 'amo_action_duplicate' );
add_action( 'woocommerce_order_action_amo_same_customer', 'amo_action_same_customer' );
add_action( 'woocommerce_order_action_amo_same_products', 'amo_action_same_products' );
add_filter( 'redirect_post_location', 'amo_redirect_to_new_draft' );
add_filter( 'woocommerce_redirect_order_location', 'amo_redirect_to_new_draft' );
add_action( 'woocommerce_admin_order_data_after_order_details', 'amo_currency_field' );
add_action( 'wp_ajax_amo_set_currency', 'amo_ajax_set_currency' );
add_action( 'wp_ajax_amo_search_orders', 'amo_ajax_search_orders' );
add_action( 'wp_ajax_amo_toggle_currency', 'amo_ajax_toggle_currency' );
add_action( 'admin_post_amo_duplicate', 'amo_handle_duplicate_link' );
add_filter( 'woocommerce_admin_order_actions', 'amo_list_duplicate_button', 10, 2 );
add_filter( 'woocommerce_admin_order_preview_actions', 'amo_list_duplicate_button', 10, 2 );
add_filter( 'default_hidden_columns', 'amo_show_actions_column', 20, 2 );

/**
 * Creates a new order. By default a draft, the same kind WooCommerce makes when you click
 * "Add order": it shows the "Create" button and is removed after a few days if never created.
 *
 * @param array $args {
 *     @type int           $customer_id Customer to assign, with their saved addresses.
 *     @type string        $currency    Order currency.
 *     @type WC_Order|null $source      Order to copy from.
 *     @type string        $copy        What to copy from $source: all, customer, products.
 *     @type string        $status      auto-draft, or a real status like pending so it shows in the list.
 * }
 * @return WC_Order
 */
function amo_create_draft( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'customer_id' => 0,
			'currency'    => '',
			'source'      => null,
			'copy'        => '',
			'status'      => 'auto-draft',
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
		amo_set_customer( $order, $args['customer_id'] );
	}

	// What belongs to the original's payment and processing is never copied: payment method,
	// transaction ID, paid and completed dates, invoices, stock changes, order notes.
	if ( $source && 'all' === $args['copy'] ) {
		$order->set_prices_include_tax( $source->get_prices_include_tax() );
		$order->set_customer_note( $source->get_customer_note() );

		foreach ( $source->get_meta_data() as $meta ) {
			if ( amo_copy_meta( $meta->key, $source ) ) {
				$order->add_meta_data( $meta->key, $meta->value );
			}
		}
	}

	$order->save();

	if ( $source && in_array( $args['copy'], array( 'all', 'products' ), true ) ) {
		$fresh = 'products' === $args['copy'];
		amo_copy_items( $source, $order, $fresh );

		if ( $fresh ) {
			$order->calculate_totals();
		} else {
			// Keep every tax amount as it was, even ones edited by hand.
			$order->update_taxes();
			$order->calculate_totals( false );
		}

		/* translators: %s: order number */
		$order->add_order_note( sprintf( __( 'Created from order #%s.', 'advanced-manual-orders-for-woocommerce' ), $source->get_order_number() ) );
	}

	if ( 'auto-draft' !== $args['status'] ) {
		// A copy isn't a new sale: no "new order" or "order received" emails.
		foreach ( WC()->mailer()->get_emails() as $email ) {
			add_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false' );
		}

		$order->set_status( $args['status'] );
		$order->save();

		foreach ( WC()->mailer()->get_emails() as $email ) {
			remove_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false' );
		}
	}

	do_action( 'amo_draft_created', $order, $args );

	return $order;
}

/**
 * Assigns a customer and loads their saved addresses.
 *
 * @param WC_Order $order       Order.
 * @param int      $customer_id Customer user ID.
 */
function amo_set_customer( $order, $customer_id ) {
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
 * Whether a custom field of an order or line item goes to the copy. Visible fields, like
 * variation attributes or product options, are copied. Hidden ones (starting with _) hold
 * what was added while the original was paid and processed, like invoice numbers,
 * license keys, tracking numbers and stock changes, so they stay with the original.
 *
 * @param string                 $key    Meta key.
 * @param WC_Order|WC_Order_Item $owner  Order or line item it belongs to.
 * @return bool
 */
function amo_copy_meta( $key, $owner ) {
	return (bool) apply_filters( 'amo_copy_meta', '_' !== substr( $key, 0, 1 ), $key, $owner );
}

/**
 * Copies line items from one order to another.
 *
 * @param WC_Order $from  Source order.
 * @param WC_Order $to    Target order.
 * @param bool     $fresh True: products only, at today's prices. False: every line as it was.
 */
function amo_copy_items( $from, $to, $fresh ) {
	// Tax lines are rebuilt from the copied items.
	$types = $fresh ? array( 'line_item' ) : array( 'line_item', 'fee', 'shipping', 'coupon' );

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
			if ( amo_copy_meta( $meta->key, $item ) ) {
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

		if ( $fresh && $new instanceof WC_Order_Item_Product && amo_same_price_fix_active() ) {
			amo_same_price_item( $new, amo_tax_location( $to ) );
		}
	}

	$to->save();
}

/**
 * Handles the "Add order" form.
 */
function amo_handle_new_order_form() {
	check_admin_referer( 'amo_new_order' );

	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to create orders.', 'advanced-manual-orders-for-woocommerce' ) );
	}

	$currency = isset( $_POST['amo_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['amo_currency'] ) ) : '';
	$source   = ! empty( $_POST['amo_copy_from'] ) ? wc_get_order( absint( $_POST['amo_copy_from'] ) ) : null;

	$order = amo_create_draft(
		array(
			'customer_id' => isset( $_POST['amo_customer'] ) ? absint( $_POST['amo_customer'] ) : 0,
			'currency'    => array_key_exists( $currency, amo_currencies() ) ? $currency : '',
			'source'      => $source && 'shop_order' === $source->get_type() ? $source : null,
			'copy'        => isset( $_POST['amo_copy'] ) && 'all' === sanitize_key( $_POST['amo_copy'] ) ? 'all' : 'products',
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
function amo_order_actions( $actions, $order = null ) {
	if ( $order && $order->get_id() && 'auto-draft' !== $order->get_status() ) {
		$actions['amo_duplicate']     = __( 'Duplicate order', 'advanced-manual-orders-for-woocommerce' );
		$actions['amo_same_customer'] = __( 'New order for this customer', 'advanced-manual-orders-for-woocommerce' );
		$actions['amo_same_products'] = __( 'New order with these products', 'advanced-manual-orders-for-woocommerce' );
	}
	return $actions;
}

/**
 * Remembers the draft to open after the order is saved.
 *
 * @param WC_Order|null $order Draft, or null to read it.
 * @return WC_Order|null
 */
function amo_new_draft( $order = null ) {
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
function amo_action_duplicate( $order ) {
	amo_new_draft(
		amo_create_draft(
			array(
				'source' => $order,
				'copy'   => 'all',
				'status' => amo_duplicate_status(),
			)
		)
	);
}

/**
 * "New order for this customer" action.
 *
 * @param WC_Order $order Order.
 */
function amo_action_same_customer( $order ) {
	amo_new_draft(
		amo_create_draft(
			array(
				'source' => $order,
				'copy'   => 'customer',
				'status' => 'pending',
			)
		)
	);
}

/**
 * "New order with these products" action.
 *
 * @param WC_Order $order Order.
 */
function amo_action_same_products( $order ) {
	amo_new_draft(
		amo_create_draft(
			array(
				'source' => $order,
				'copy'   => 'products',
				'status' => 'pending',
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
function amo_redirect_to_new_draft( $location ) {
	$draft = amo_new_draft();
	return $draft ? $draft->get_edit_order_url() : $location;
}

/**
 * Currency picker in the order data box, while the order is unpaid and editable.
 *
 * @param WC_Order $order Order.
 */
function amo_currency_field( $order ) {
	if ( ! $order->is_editable() || $order->get_date_paid() ) {
		return;
	}

	?>
	<div class="form-field form-field-wide amo-currency-wrap">
		<?php amo_currency_selector( 'amo_currency', $order->get_currency() ); ?>
	</div>
	<?php
}

/**
 * Currency dropdown, or the link that turns it on. A second link turns it off.
 *
 * @param string $id       Field ID and name.
 * @param string $selected Selected currency.
 */
function amo_currency_selector( $id, $selected ) {
	$on        = amo_enabled( 'currency' );
	$can_reset = current_user_can( 'manage_woocommerce' );

	if ( ! $on && ! $can_reset ) {
		return;
	}
	?>
	<p class="amo-currency amo-advanced" <?php echo $on ? '' : 'hidden'; ?>>
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php esc_html_e( 'Currency', 'advanced-manual-orders-for-woocommerce' ); ?>
			<?php amo_help_tip( __( 'The currency of this order. Your store currencies come first. Prices you type are not converted.', 'advanced-manual-orders-for-woocommerce' ) ); ?>
			<?php if ( $can_reset ) : ?>
				<a href="#" class="amo-currency-toggle" data-on="0"><?php esc_html_e( 'Remove currency selector', 'advanced-manual-orders-for-woocommerce' ); ?></a>
			<?php endif; ?>
		</label>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="wc-enhanced-select" style="width:100%">
			<?php amo_currency_options( $selected ); ?>
		</select>
	</p>
	<?php if ( $can_reset ) : ?>
		<p class="amo-currency-off" <?php echo $on ? 'hidden' : ''; ?>>
			<a href="#" class="amo-currency-toggle amo-link" data-on="1"><?php esc_html_e( 'Enable currency selector', 'advanced-manual-orders-for-woocommerce' ); ?></a>
			<?php amo_help_tip( __( 'Adds a currency dropdown to new and unpaid orders, so you can take an order in another currency. You can remove it again at any time.', 'advanced-manual-orders-for-woocommerce' ) ); ?>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Info icon with a tooltip, in the plugin's color.
 *
 * @param string $tip Tooltip text.
 */
function amo_help_tip( $tip ) {
	printf( '<span class="amo-help-tip" tabindex="0" data-tip="%1$s" aria-label="%1$s"></span>', esc_attr( $tip ) );
}

/**
 * Turns the currency selector on or off from the order screen.
 */
function amo_ajax_toggle_currency() {
	check_ajax_referer( 'amo_admin', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error();
	}

	update_option( 'amo_currency', empty( $_POST['on'] ) ? 'no' : 'yes' );
	wp_send_json_success();
}

/**
 * Saves the currency as soon as it changes, so items added next use it.
 */
function amo_ajax_set_currency() {
	check_ajax_referer( 'amo_admin', 'security' );

	$order    = isset( $_POST['order_id'] ) ? wc_get_order( absint( $_POST['order_id'] ) ) : null;
	$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

	if ( ! $order || ! current_user_can( 'edit_shop_orders' ) || ! $order->is_editable() || $order->get_date_paid() ) {
		wp_send_json_error();
	}

	if ( ! array_key_exists( $currency, amo_currencies() ) ) {
		wp_send_json_error();
	}

	$old = $order->get_currency();

	if ( $old !== $currency ) {
		$order->set_currency( $currency );
		$order->save();

		if ( 'auto-draft' !== $order->get_status() ) {
			/* translators: 1: old currency code, 2: new currency code */
			$order->add_order_note( sprintf( __( 'Currency changed from %1$s to %2$s. Item prices were not converted.', 'advanced-manual-orders-for-woocommerce' ), $old, $currency ) );
		}
	}

	wp_send_json_success();
}

/**
 * Orders for the "Copy products from" picker. Empty search: the latest 10.
 */
function amo_ajax_search_orders() {
	check_ajax_referer( 'amo_admin', 'security' );

	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( -1 );
	}

	$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

	if ( '' === $term ) {
		$orders = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array_keys( wc_get_order_statuses() ),
				'limit'   => 10,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
	} else {
		// Name, email, address, product names, and the order number itself.
		$ids    = array_map( 'absint', wc_order_search( $term ) );
		$number = ltrim( $term, '#' );
		if ( ctype_digit( $number ) ) {
			$ids[] = absint( $number );
		}
		$ids = array_unique( $ids );
		rsort( $ids );

		$orders = array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order && 'shop_order' === $order->get_type() && ! $order->has_status( array( 'auto-draft', 'checkout-draft', 'trash' ) ) ) {
				$orders[] = $order;
			}
			if ( count( $orders ) >= 20 ) {
				break;
			}
		}
	}

	$results = array();
	foreach ( $orders as $order ) {
		$name = trim( $order->get_formatted_billing_full_name() );
		if ( ! $name ) {
			$name = $order->get_billing_email() ? $order->get_billing_email() : __( 'Guest', 'advanced-manual-orders-for-woocommerce' );
		}
		$count = $order->get_item_count();

		$results[] = array(
			'id'   => $order->get_id(),
			'text' => implode(
				' · ',
				array(
					'#' . $order->get_order_number(),
					$name,
					/* translators: %d: number of items */
					sprintf( _n( '%d item', '%d items', $count, 'advanced-manual-orders-for-woocommerce' ), $count ),
					html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
					$order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
				)
			),
		);
	}

	wp_send_json( array( 'results' => $results ) );
}

/**
 * Duplicate button in the orders list and in the order preview.
 *
 * @param array    $actions Action buttons.
 * @param WC_Order $order   Order.
 * @return array
 */
function amo_list_duplicate_button( $actions, $order ) {
	if ( $order instanceof WC_Order && 'shop_order' === $order->get_type() && current_user_can( 'edit_shop_orders' ) ) {
		$actions['amo_duplicate'] = array(
			'url'    => wp_nonce_url( admin_url( 'admin-post.php?action=amo_duplicate&order_id=' . $order->get_id() ), 'amo_duplicate_' . $order->get_id() ),
			'name'   => __( 'Duplicate', 'advanced-manual-orders-for-woocommerce' ),
			'title'  => sprintf(
				/* translators: %s: order status, e.g. Pending payment */
				__( 'Duplicate this order: a new "%s" order with the same customer, items, shipping and coupons. Payment details and invoices are not copied, and no emails are sent.', 'advanced-manual-orders-for-woocommerce' ),
				wc_get_order_status_name( amo_duplicate_status() )
			),
			'action' => 'amo-duplicate',
		);
	}
	return $actions;
}

/**
 * Duplicate button clicked in the orders list or the preview.
 */
function amo_handle_duplicate_link() {
	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	check_admin_referer( 'amo_duplicate_' . $order_id );

	$order = wc_get_order( $order_id );

	if ( ! $order || 'shop_order' !== $order->get_type() || ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to create orders.', 'advanced-manual-orders-for-woocommerce' ) );
	}

	$copy = amo_create_draft(
		array(
			'source' => $order,
			'copy'   => 'all',
			'status' => amo_duplicate_status(),
		)
	);

	wp_safe_redirect( $copy->get_edit_order_url() );
	exit;
}

/**
 * Shows the Actions column of the orders list by default, where the Duplicate button is.
 * Screen Options can still hide it.
 *
 * @param string[]  $hidden Hidden columns.
 * @param WP_Screen $screen Screen.
 * @return string[]
 */
function amo_show_actions_column( $hidden, $screen ) {
	if ( isset( $screen->id ) && in_array( $screen->id, array( 'edit-shop_order', wc_get_page_screen_id( 'shop-order' ) ), true ) ) {
		$hidden = array_values( array_diff( $hidden, array( 'wc_actions' ) ) );
	}
	return $hidden;
}

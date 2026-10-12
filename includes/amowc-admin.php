<?php
/**
 * Order screen scripts and the "Add order" form.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'amowc_enqueue' );
add_action( 'admin_footer', 'amowc_new_order_form' );

/**
 * Which order screen this is: 'list', 'edit' or ''.
 *
 * @return string
 */
function amowc_screen() {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return '';
	}

	if ( 'edit-shop_order' === $screen->id ) {
		return 'list';
	}

	if ( 'shop_order' === $screen->id ) {
		return 'edit';
	}

	if ( function_exists( 'wc_get_page_screen_id' ) && wc_get_page_screen_id( 'shop-order' ) === $screen->id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which screen this is.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		return in_array( $action, array( 'edit', 'new' ), true ) ? 'edit' : 'list';
	}

	return '';
}

/**
 * Loads the script on the orders list and order edit screens.
 */
function amowc_enqueue() {
	$screen = amowc_screen();

	if ( ! $screen || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	wp_enqueue_style( 'amowc-admin', AMOWC_PLUGIN_URL . 'assets/css/amowc-admin.css', array(), AMOWC_VERSION );
	wp_enqueue_script( 'amowc-admin', AMOWC_PLUGIN_URL . 'assets/js/amowc-admin.js', array( 'jquery', 'wc-enhanced-select' ), AMOWC_VERSION, true );
	wp_localize_script(
		'amowc-admin',
		'amowc',
		array(
			'screen'     => $screen,
			'rest'       => esc_url_raw( rest_url( 'amowc/v1/' ) ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'popup'      => amowc_enabled( 'popup' ),
			'autoTotals' => amowc_enabled( 'auto_totals' ),
			'shipping'   => wc_shipping_enabled(),
			'i18n'       => array(
				'shippingTitle' => __( 'Calculate shipping', 'business-bloomer-advanced-manual-orders' ),
				/* translators: %d: package number */
				'package'       => __( 'Package %d', 'business-bloomer-advanced-manual-orders' ),
				'add'           => __( 'Add to order', 'business-bloomer-advanced-manual-orders' ),
				'cancel'        => __( 'Cancel', 'business-bloomer-advanced-manual-orders' ),
				'replaceNote'   => __( 'Replaces the shipping already on this order.', 'business-bloomer-advanced-manual-orders' ),
				'error'         => __( 'Something went wrong. Please try again.', 'business-bloomer-advanced-manual-orders' ),
				'payLink'       => __( 'Order Pay link:', 'business-bloomer-advanced-manual-orders' ),
				'copyLink'      => __( 'Copy', 'business-bloomer-advanced-manual-orders' ),
				'copied'        => __( 'Copied', 'business-bloomer-advanced-manual-orders' ),
				'emailLink'     => __( 'Save & email', 'business-bloomer-advanced-manual-orders' ),
				/* translators: %s: customer email address */
				'emailConfirm'  => __( 'Save the order and email the payment link to %s?', 'business-bloomer-advanced-manual-orders' ),
				'noEmail'       => __( 'Add a billing email address first.', 'business-bloomer-advanced-manual-orders' ),
				'tipEmailLink'  => __( 'Saves your changes first, then sends WooCommerce\'s customer invoice email, with the payment link, to the billing email. Same as Order actions > Send order details to customer.', 'business-bloomer-advanced-manual-orders' ),
				'findCustomer'  => __( 'Name, email, phone or company', 'business-bloomer-advanced-manual-orders' ),
				'tipCustomer'   => __( 'Finds registered customers and guests by name, email, phone or company. Picking one fills in their billing and shipping addresses.', 'business-bloomer-advanced-manual-orders' ),
				'addProduct'    => __( 'Add a product: type a name or SKU', 'business-bloomer-advanced-manual-orders' ),
				'searching'     => __( 'Searching…', 'business-bloomer-advanced-manual-orders' ),
				'noResults'     => __( 'No products found', 'business-bloomer-advanced-manual-orders' ),
				'tipAddItems'   => __( 'Tip: the "Add a product" box above adds products in one click, every variation listed.', 'business-bloomer-advanced-manual-orders' ),
				'tipRecalc'     => __( 'Taxes and totals already update on their own when items or the address change.', 'business-bloomer-advanced-manual-orders' ),
				'tipShipping'   => __( 'Tip: "Calculate shipping" adds real rates from your shipping zones, with tax.', 'business-bloomer-advanced-manual-orders' ),
				'tipCalcShip'   => __( 'Real rates from your shipping zones for these items and this address, the same ones checkout shows.', 'business-bloomer-advanced-manual-orders' ),
				'tipCoupon'     => __( 'Totals and taxes update on their own after the coupon is applied.', 'business-bloomer-advanced-manual-orders' ),
				'tipProduct'    => __( 'Type a name or SKU, or open the list to see your best sellers. Every variation is listed on its own, and one click adds it. Pick the same product again to raise its quantity.', 'business-bloomer-advanced-manual-orders' ),
				'tipActions'    => __( 'New in this list: Duplicate order, New order for this customer, New order with these products.', 'business-bloomer-advanced-manual-orders' ),
				'tipFee'        => __( 'Tip: type an amount, or a percentage like 10% of the order. Totals and taxes update on their own.', 'business-bloomer-advanced-manual-orders' ),
				'tipTax'        => __( 'Not needed for normal taxes: they come from your tax settings and the customer address. Use it only for an extra rate on this order.', 'business-bloomer-advanced-manual-orders' ),
			),
		)
	);
}

/**
 * The "Add order" form, shown by the script when the button is clicked.
 */
function amowc_new_order_form() {
	if ( ! amowc_screen() || ! amowc_enabled( 'popup' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	$store = get_woocommerce_currency();
	?>
	<div id="amowc-new-order" class="amowc-modal" hidden>
		<div class="amowc-modal__box" role="dialog" aria-modal="true" aria-labelledby="amowc-new-order-title">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2 id="amowc-new-order-title"><?php esc_html_e( 'New order', 'business-bloomer-advanced-manual-orders' ); ?> <span class="amowc-badge"><?php esc_html_e( 'Advanced', 'business-bloomer-advanced-manual-orders' ); ?></span></h2>
				<input type="hidden" name="action" value="amowc_new_order">
				<?php wp_nonce_field( 'amowc_new_order' ); ?>

				<p>
					<label for="amowc_copy_from"><?php esc_html_e( 'Start from a past order (optional)', 'business-bloomer-advanced-manual-orders' ); ?></label>
					<select id="amowc_copy_from" name="amowc_copy_from" style="width:100%" data-placeholder="<?php esc_attr_e( 'Latest orders, or search by name, email or number', 'business-bloomer-advanced-manual-orders' ); ?>"></select>
				</p>

				<fieldset class="amowc-copy" hidden>
					<label><input type="radio" name="amowc_copy" value="all" checked> <?php esc_html_e( 'Repeat the whole order', 'business-bloomer-advanced-manual-orders' ); ?></label>
					<span class="description"><?php esc_html_e( 'Same customer, addresses, currency, products, prices, shipping, fees and coupons. Payment, invoices and order notes are not copied.', 'business-bloomer-advanced-manual-orders' ); ?></span>
					<label><input type="radio" name="amowc_copy" value="products"> <?php esc_html_e( 'Copy the products only', 'business-bloomer-advanced-manual-orders' ); ?></label>
					<span class="description"><?php esc_html_e( 'Same products and quantities at today\'s prices, for the customer below.', 'business-bloomer-advanced-manual-orders' ); ?></span>
				</fieldset>

				<div class="amowc-new-fields">
					<p>
						<label for="amowc_customer"><?php esc_html_e( 'Customer', 'business-bloomer-advanced-manual-orders' ); ?></label>
						<select id="amowc_customer" name="amowc_customer" data-placeholder="<?php esc_attr_e( 'Guest, or search by name, email, phone or company', 'business-bloomer-advanced-manual-orders' ); ?>" style="width:100%"></select>
						<span class="description"><?php esc_html_e( 'Registered customers and past guests. Their billing and shipping addresses are filled in.', 'business-bloomer-advanced-manual-orders' ); ?></span>
					</p>

					<?php amowc_currency_selector( 'amowc_new_currency', get_woocommerce_currency() ); ?>
				</div>

				<div class="amowc-modal__actions">
					<a href="#" class="amowc-skip"><?php esc_html_e( 'Skip, open an empty order', 'business-bloomer-advanced-manual-orders' ); ?></a>
					<button type="button" class="button amowc-close"><?php esc_html_e( 'Cancel', 'business-bloomer-advanced-manual-orders' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Start order', 'business-bloomer-advanced-manual-orders' ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

<?php
/**
 * Order screen scripts and the "Add order" form.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'amo_enqueue' );
add_action( 'admin_footer', 'amo_new_order_form' );

/**
 * Which order screen this is: 'list', 'edit' or ''.
 *
 * @return string
 */
function amo_screen() {
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
function amo_enqueue() {
	$screen = amo_screen();

	if ( ! $screen || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	wp_enqueue_style( 'amo-admin', AMO_PLUGIN_URL . 'assets/css/amo-admin.css', array(), AMO_VERSION );
	wp_enqueue_script( 'amo-admin', AMO_PLUGIN_URL . 'assets/js/amo-admin.js', array( 'jquery', 'wc-enhanced-select' ), AMO_VERSION, true );
	wp_localize_script(
		'amo-admin',
		'amo',
		array(
			'screen'     => $screen,
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'amo_admin' ),
			'popup'      => amo_enabled( 'popup' ),
			'autoTotals' => amo_enabled( 'auto_totals' ),
			'shipping'   => wc_shipping_enabled(),
			'i18n'       => array(
				'shippingTitle' => __( 'Calculate shipping', 'advanced-manual-orders-for-woocommerce' ),
				/* translators: %d: package number */
				'package'       => __( 'Package %d', 'advanced-manual-orders-for-woocommerce' ),
				'add'           => __( 'Add to order', 'advanced-manual-orders-for-woocommerce' ),
				'cancel'        => __( 'Cancel', 'advanced-manual-orders-for-woocommerce' ),
				'replaceNote'   => __( 'Replaces the shipping already on this order.', 'advanced-manual-orders-for-woocommerce' ),
				'error'         => __( 'Something went wrong. Please try again.', 'advanced-manual-orders-for-woocommerce' ),
				'copyLink'      => __( 'Copy Order Pay link', 'advanced-manual-orders-for-woocommerce' ),
				'copied'        => __( 'Link copied', 'advanced-manual-orders-for-woocommerce' ),
				'addProduct'    => __( 'Add a product: type a name or SKU', 'advanced-manual-orders-for-woocommerce' ),
				'searching'     => __( 'Searching…', 'advanced-manual-orders-for-woocommerce' ),
				'noResults'     => __( 'No products found', 'advanced-manual-orders-for-woocommerce' ),
				'tipAddItems'   => __( 'Tip: the "Add a product" box above adds products in one click, every variation listed.', 'advanced-manual-orders-for-woocommerce' ),
				'tipRecalc'     => __( 'Taxes and totals already update on their own when items or the address change.', 'advanced-manual-orders-for-woocommerce' ),
				'tipShipping'   => __( 'Tip: "Calculate shipping" adds real rates from your shipping zones, with tax.', 'advanced-manual-orders-for-woocommerce' ),
				'tipCalcShip'   => __( 'Real rates from your shipping zones for these items and this address, the same ones checkout shows.', 'advanced-manual-orders-for-woocommerce' ),
				'tipCoupon'     => __( 'Totals and taxes update on their own after the coupon is applied.', 'advanced-manual-orders-for-woocommerce' ),
				'tipActions'    => __( 'New in this list: Duplicate order, New order for this customer, New order with these products.', 'advanced-manual-orders-for-woocommerce' ),
				'tipFee'        => __( 'Tip: type an amount, or a percentage like 10% of the order. Totals and taxes update on their own.', 'advanced-manual-orders-for-woocommerce' ),
				'tipTax'        => __( 'Not needed for normal taxes: they come from your tax settings and the customer address. Use it only for an extra rate on this order.', 'advanced-manual-orders-for-woocommerce' ),
			),
		)
	);
}

/**
 * The "Add order" form, shown by the script when the button is clicked.
 */
function amo_new_order_form() {
	if ( ! amo_screen() || ! amo_enabled( 'popup' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	$store = get_woocommerce_currency();
	?>
	<div id="amo-new-order" class="amo-modal" hidden>
		<div class="amo-modal__box" role="dialog" aria-modal="true" aria-labelledby="amo-new-order-title">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2 id="amo-new-order-title"><?php esc_html_e( 'New order', 'advanced-manual-orders-for-woocommerce' ); ?> <span class="amo-badge"><?php esc_html_e( 'Advanced', 'advanced-manual-orders-for-woocommerce' ); ?></span></h2>
				<input type="hidden" name="action" value="amo_new_order">
				<?php wp_nonce_field( 'amo_new_order' ); ?>

				<p>
					<label for="amo_copy_from"><?php esc_html_e( 'Start from a past order (optional)', 'advanced-manual-orders-for-woocommerce' ); ?></label>
					<select id="amo_copy_from" name="amo_copy_from" style="width:100%" data-placeholder="<?php esc_attr_e( 'Latest orders, or search by name, email or number', 'advanced-manual-orders-for-woocommerce' ); ?>"></select>
				</p>

				<fieldset class="amo-copy" hidden>
					<label><input type="radio" name="amo_copy" value="all" checked> <?php esc_html_e( 'Repeat the whole order', 'advanced-manual-orders-for-woocommerce' ); ?></label>
					<span class="description"><?php esc_html_e( 'Same customer, addresses, currency, products, prices, shipping, fees and coupons. Payment, invoices and order notes are not copied.', 'advanced-manual-orders-for-woocommerce' ); ?></span>
					<label><input type="radio" name="amo_copy" value="products"> <?php esc_html_e( 'Copy the products only', 'advanced-manual-orders-for-woocommerce' ); ?></label>
					<span class="description"><?php esc_html_e( 'Same products and quantities at today\'s prices, for the customer below.', 'advanced-manual-orders-for-woocommerce' ); ?></span>
				</fieldset>

				<div class="amo-new-fields">
					<p>
						<label for="amo_customer"><?php esc_html_e( 'Customer', 'advanced-manual-orders-for-woocommerce' ); ?></label>
						<select id="amo_customer" name="amo_customer" class="wc-customer-search" data-placeholder="<?php esc_attr_e( 'Guest', 'advanced-manual-orders-for-woocommerce' ); ?>" data-allow_clear="true" style="width:100%"></select>
						<span class="description"><?php esc_html_e( 'Their saved billing and shipping addresses are filled in.', 'advanced-manual-orders-for-woocommerce' ); ?></span>
					</p>

					<?php amo_currency_selector( 'amo_new_currency', get_woocommerce_currency() ); ?>
				</div>

				<div class="amo-modal__actions">
					<a href="#" class="amo-skip"><?php esc_html_e( 'Skip, open an empty order', 'advanced-manual-orders-for-woocommerce' ); ?></a>
					<button type="button" class="button amo-close"><?php esc_html_e( 'Cancel', 'advanced-manual-orders-for-woocommerce' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Start order', 'advanced-manual-orders-for-woocommerce' ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

<?php
/**
 * Order screen scripts and the "Add order" form.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'mosw_enqueue' );
add_action( 'admin_footer', 'mosw_new_order_form' );

/**
 * Which order screen this is: 'list', 'edit' or ''.
 *
 * @return string
 */
function mosw_screen() {
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
function mosw_enqueue() {
	$screen = mosw_screen();

	if ( ! $screen || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	wp_enqueue_style( 'mosw-admin', MOSW_PLUGIN_URL . 'assets/css/mosw-admin.css', array(), MOSW_VERSION );
	wp_enqueue_script( 'mosw-admin', MOSW_PLUGIN_URL . 'assets/js/mosw-admin.js', array( 'jquery', 'wc-enhanced-select' ), MOSW_VERSION, true );
	wp_localize_script(
		'mosw-admin',
		'mosw',
		array(
			'screen'     => $screen,
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'mosw_admin' ),
			'popup'      => mosw_enabled( 'popup' ),
			'autoTotals' => mosw_enabled( 'auto_totals' ),
			'i18n'       => array(
				'shippingTitle' => __( 'Calculate shipping', 'manual-order-superpowers-for-woocommerce' ),
				/* translators: %d: package number */
				'package'       => __( 'Package %d', 'manual-order-superpowers-for-woocommerce' ),
				'add'           => __( 'Add to order', 'manual-order-superpowers-for-woocommerce' ),
				'cancel'        => __( 'Cancel', 'manual-order-superpowers-for-woocommerce' ),
				'replaceNote'   => __( 'Replaces the shipping already on this order.', 'manual-order-superpowers-for-woocommerce' ),
				'error'         => __( 'Something went wrong. Please try again.', 'manual-order-superpowers-for-woocommerce' ),
				'copyLink'      => __( 'Copy link', 'manual-order-superpowers-for-woocommerce' ),
				'copied'        => __( 'Copied', 'manual-order-superpowers-for-woocommerce' ),
				'addProduct'    => __( 'Add a product: type a name or SKU', 'manual-order-superpowers-for-woocommerce' ),
				'searching'     => __( 'Searching…', 'manual-order-superpowers-for-woocommerce' ),
				'noResults'     => __( 'No products found', 'manual-order-superpowers-for-woocommerce' ),
			),
		)
	);
}

/**
 * The "Add order" form, shown by the script when the button is clicked.
 */
function mosw_new_order_form() {
	if ( ! mosw_screen() || ! mosw_enabled( 'popup' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}

	$store = get_woocommerce_currency();
	?>
	<div id="mosw-new-order" class="mosw-modal" hidden>
		<div class="mosw-modal__box" role="dialog" aria-modal="true" aria-labelledby="mosw-new-order-title">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2 id="mosw-new-order-title"><?php esc_html_e( 'New order', 'manual-order-superpowers-for-woocommerce' ); ?></h2>
				<input type="hidden" name="action" value="mosw_new_order">
				<?php wp_nonce_field( 'mosw_new_order' ); ?>

				<p>
					<label for="mosw_customer"><?php esc_html_e( 'Customer', 'manual-order-superpowers-for-woocommerce' ); ?></label>
					<select id="mosw_customer" name="mosw_customer" class="wc-customer-search" data-placeholder="<?php esc_attr_e( 'Guest', 'manual-order-superpowers-for-woocommerce' ); ?>" data-allow_clear="true" style="width:100%"></select>
					<span class="description"><?php esc_html_e( 'Their saved billing and shipping addresses are filled in.', 'manual-order-superpowers-for-woocommerce' ); ?></span>
				</p>

				<p>
					<label for="mosw_new_currency"><?php esc_html_e( 'Currency', 'manual-order-superpowers-for-woocommerce' ); ?></label>
					<select id="mosw_new_currency" name="mosw_currency" class="wc-enhanced-select" style="width:100%">
						<?php mosw_currency_options( get_option( 'woocommerce_currency' ) ); ?>
					</select>
				</p>

				<p>
					<label for="mosw_copy_from"><?php esc_html_e( 'Copy products from an order (optional)', 'manual-order-superpowers-for-woocommerce' ); ?></label>
					<select id="mosw_copy_from" name="mosw_copy_from" style="width:100%" data-placeholder="<?php esc_attr_e( 'Latest orders, or search by name, email or number', 'manual-order-superpowers-for-woocommerce' ); ?>"></select>
					<span class="description"><?php esc_html_e( 'Same products and quantities, at today\'s prices.', 'manual-order-superpowers-for-woocommerce' ); ?></span>
				</p>

				<div class="mosw-modal__actions">
					<a href="#" class="mosw-skip"><?php esc_html_e( 'Skip, open an empty order', 'manual-order-superpowers-for-woocommerce' ); ?></a>
					<button type="button" class="button mosw-close"><?php esc_html_e( 'Cancel', 'manual-order-superpowers-for-woocommerce' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Start order', 'manual-order-superpowers-for-woocommerce' ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

<?php
/**
 * Plugin Name:          Advanced Manual Orders for WooCommerce
 * Description:          Create manual orders in WooCommerce without the pain: pick a currency, get real shipping rates, taxes that recalculate on their own, one-click duplicate and reorder, and payment links that work without login.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 * Author:               Rodolfo Melogli
 * Author URI:           https://businessbloomer.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          advanced-manual-orders-for-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'AMO_VERSION', '1.0.0' );
define( 'AMO_PLUGIN_FILE', __FILE__ );
define( 'AMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', 'amo_declare_compatibility' );
add_action( 'plugins_loaded', 'amo_init' );

/**
 * Declares HPOS compatibility.
 */
function amo_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/**
 * Loads the plugin once WooCommerce is available.
 */
function amo_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once AMO_PLUGIN_DIR . 'includes/amo-settings.php';
	require_once AMO_PLUGIN_DIR . 'includes/amo-orders.php';
	require_once AMO_PLUGIN_DIR . 'includes/amo-admin.php';
	require_once AMO_PLUGIN_DIR . 'includes/amo-shipping.php';
	require_once AMO_PLUGIN_DIR . 'includes/amo-products.php';
	require_once AMO_PLUGIN_DIR . 'includes/amo-payment.php';
	require_once AMO_PLUGIN_DIR . 'includes/amo-tax-fix.php';

	add_filter( 'plugin_action_links_' . plugin_basename( AMO_PLUGIN_FILE ), 'amo_action_links' );
}

/**
 * Adds a Settings link on the Plugins screen.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function amo_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( amo_settings_url() ) . '">' . esc_html__( 'Settings', 'advanced-manual-orders-for-woocommerce' ) . '</a>' );
	return $links;
}

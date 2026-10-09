<?php
/**
 * Plugin Name:          Manual Order Superpowers for WooCommerce
 * Description:          Create manual orders in WooCommerce without the pain: pick a currency, get real shipping rates, taxes that recalculate on their own, one-click duplicate and reorder, and payment links that work without login.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.1.2
 * Author:               Rodolfo Melogli
 * Author URI:           https://businessbloomer.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          manual-order-superpowers-for-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'MOSW_VERSION', '0.1.0' );
define( 'MOSW_PLUGIN_FILE', __FILE__ );
define( 'MOSW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', 'mosw_declare_compatibility' );
add_action( 'plugins_loaded', 'mosw_init' );

/**
 * Declares HPOS compatibility.
 */
function mosw_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/**
 * Loads the plugin once WooCommerce is available.
 */
function mosw_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once MOSW_PLUGIN_DIR . 'includes/mosw-settings.php';
	require_once MOSW_PLUGIN_DIR . 'includes/mosw-orders.php';
	require_once MOSW_PLUGIN_DIR . 'includes/mosw-admin.php';
	require_once MOSW_PLUGIN_DIR . 'includes/mosw-shipping.php';
	require_once MOSW_PLUGIN_DIR . 'includes/mosw-products.php';
	require_once MOSW_PLUGIN_DIR . 'includes/mosw-payment.php';

	add_filter( 'plugin_action_links_' . plugin_basename( MOSW_PLUGIN_FILE ), 'mosw_action_links' );
}

/**
 * Adds a Settings link on the Plugins screen.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function mosw_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( mosw_settings_url() ) . '">' . esc_html__( 'Settings', 'manual-order-superpowers-for-woocommerce' ) . '</a>' );
	return $links;
}

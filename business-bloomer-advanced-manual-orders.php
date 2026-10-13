<?php
/**
 * Plugin Name:          Business Bloomer Advanced Manual Orders for WooCommerce
 * Description:          Create manual orders in WooCommerce without the pain: pick a currency, get real shipping rates, taxes that recalculate on their own, one-click duplicate and reorder, and payment links that work without login.
 * Version:              1.0.1
 * Requires at least:    6.9
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 * Author:               Rodolfo Melogli
 * Author URI:           https://businessbloomer.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          business-bloomer-advanced-manual-orders
 */

defined( 'ABSPATH' ) || exit;

define( 'AMOWC_VERSION', '1.0.1' );
define( 'AMOWC_PLUGIN_FILE', __FILE__ );
define( 'AMOWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMOWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', 'amowc_declare_compatibility' );
add_action( 'plugins_loaded', 'amowc_init' );

/**
 * Declares HPOS compatibility.
 */
function amowc_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

/**
 * Loads the plugin once WooCommerce is available.
 */
function amowc_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-settings.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-orders.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-customers.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-rest.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-admin.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-shipping.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-products.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-payment.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/amowc-tax-fix.php';
	require_once AMOWC_PLUGIN_DIR . 'includes/class-amowc-abilities.php';

	add_filter( 'plugin_action_links_' . plugin_basename( AMOWC_PLUGIN_FILE ), 'amowc_action_links' );
}

/**
 * Adds a Settings link on the Plugins screen.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function amowc_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( amowc_settings_url() ) . '">' . esc_html__( 'Settings', 'business-bloomer-advanced-manual-orders' ) . '</a>' );
	return $links;
}

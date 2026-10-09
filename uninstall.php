<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'mosw_popup', 'mosw_auto_totals', 'mosw_currencies', 'mosw_guest_pay', 'mosw_email_on_new', 'mosw_same_price_fix' ) as $mosw_option ) {
	delete_option( $mosw_option );
}

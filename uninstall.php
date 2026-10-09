<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'amo_popup', 'amo_auto_totals', 'amo_currencies', 'amo_guest_pay', 'amo_email_on_new', 'amo_same_price_fix' ) as $amo_option ) {
	delete_option( $amo_option );
}

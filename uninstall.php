<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'amowc_popup', 'amowc_auto_totals', 'amowc_currencies', 'amowc_guest_pay', 'amowc_currency', 'amowc_dup_status', 'amowc_same_price_fix' ) as $amowc_option ) {
	delete_option( $amowc_option );
}

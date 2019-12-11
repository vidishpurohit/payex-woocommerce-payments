<?php
/*
 * Plugin Name: QC Mobile Pay
 * Author: QuanticEdge
 * Author URI: http://www.quanticedge.co.in
 * Version: 1.0 (Custom for Michael)
 * Requires at least: 4.0
 * Tested up to: 5.2.1
 * Description: QC Checkout Fields adds custom checkout fields that are required for site.
 * WC tested up to: 3.6.2
 * Text Domain: qc-checkout-fields
 * Domain Path: /languages/
 */

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	add_action('init', 'qc_load_mobile_pay');
	function qc_load_mobile_pay() {
		if ( class_exists('WC_Payment_Gateway', false) ) {
			require_once('includes/class-wc-gateway-payex-mobilepay.php');
		}
	}

}
<?php
/**
 * WooCommerce status page extension
 *
 * @class    Klarna_Checkout_For_WooCommerce_Status
 * @version  0.8.0
 * @package  Klarna_Checkout_For_WooCommerce/Classes
 * @category Class
 * @author   Krokedil
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Klarna_Checkout_For_WooCommerce_Status {
	public function __construct() {
		add_action( 'woocommerce_system_status_report', array( $this, 'add_status_page_box' ) );
	}
	public function add_status_page_box() {
		include_once( KCO_WC_PLUGIN_PATH . '/includes/klarna-checkout-for-woocommerce-status-report.php' );
	}
}
$wc_collector_checkout_status = new Klarna_Checkout_For_WooCommerce_Status();
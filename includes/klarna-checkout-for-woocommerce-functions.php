<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kco_wc_show_snippet' ) ) {
	/**
	 * Echoes Klarna Checkout iframe snippet.
	 */
	function kco_wc_show_snippet() {
		$kco_wc_api = new Klarna_Checkout_For_WooCommerce_API;
		$klarna_order = $kco_wc_api->get_order();
		echo $kco_wc_api->get_snippet( $klarna_order );
	}
}
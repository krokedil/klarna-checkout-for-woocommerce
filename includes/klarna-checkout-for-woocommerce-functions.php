<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kco_wc_show_snippet' ) ) {
	/**
	 * Echoes Klarna Checkout iframe snippet.
	 */
	function kco_wc_show_snippet() {
		$klarna_order = KCO_WC()->api->get_order();
		echo KCO_WC()->api->get_snippet( $klarna_order );
	}
}

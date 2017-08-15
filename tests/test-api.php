<?php
/**
 * Class SampleTest
 *
 * @package Klarna_Checkout_For_Woocommerce
 */

/**
 * Sample test case.
 */
class KCO_WC_API_Tests extends WP_UnitTestCase {

	function test_get_order_id_from_session() {
		$kco_wc_api = new Klarna_Checkout_For_WooCommerce_API();

		// Make sure we don't have a session value when starting.
		$this->assertTrue( null === $kco_wc_api->get_order_id_from_session() );

		// Set session value, make sure it is returned properly.
		WC()->session->set( 'kco_wc_order_id', 'some_string' );
		$this->assertTrue( null !== $kco_wc_api->get_order_id_from_session() );
	}

	/*
	function test_create_order() {

	}

	function test_retrieve_order() {

	}

	function test_show_iframe() {

	}
	*/

}

<?php
/**
 * Main assets file.
 *
 * @package WC_Klarna_Order_Management/Classes/Assets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets class.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Register and enqueue scripts for the admin.
	 *
	 * @return void
	 */
	public function enqueue_admin() {
		wp_enqueue_style( 'kom-admin-style', WC_KLARNA_ORDER_MANAGEMENT_CHECKOUT_URL . '/assets/css/klarna-order-management.css', array(), WC_KLARNA_ORDER_MANAGEMENT_VERSION );

		// Script Params.
		$params = array(
			'ajax_url'                                => admin_url( 'admin-ajax.php' ),
			'with_return_fee_text'                    => __( 'minus a return fee of', 'klarna-order-management-for-woocommerce' ),
			'refund_amount_less_than_return_fee_text' => __( 'Refund amount is less than the return fee.', 'klarna-order-management-for-woocommerce' ),
		);

		// Checkout script.
		wp_register_script(
			'kom-admin-js',
			WC_KLARNA_ORDER_MANAGEMENT_CHECKOUT_URL . '/assets/js/klarna-order-management.js',
			array( 'jquery' ),
			WC_KLARNA_ORDER_MANAGEMENT_VERSION,
			true
		);

		// Localize the script and add the params.
		wp_localize_script(
			'kom-admin-js',
			'kom_admin_params',
			$params
		);

		// Enqueue the script.
		wp_enqueue_script( 'kom-admin-js' );
	}
}
new Assets();

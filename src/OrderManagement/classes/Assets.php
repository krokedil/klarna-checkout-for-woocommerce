<?php
/**
 * Main assets file.
 *
 * @package OrderManagement/Classes/Assets
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
		wp_enqueue_style( 'kom-admin-style', KCO_WC_PLUGIN_URL . '/assets/css/klarna-order-management.css', array(), KCO_WC_VERSION );

		// Script Params.
		$params = array(
			'ajax_url'                                => admin_url( 'admin-ajax.php' ),
			'with_return_fee_text'                    => __( 'minus a return fee of', 'klarna-checkout-for-woocommerce' ),
			'refund_amount_less_than_return_fee_text' => __( 'Refund amount is less than the return fee.', 'klarna-checkout-for-woocommerce' ),
		);

		// Checkout script.
		wp_register_script(
			'kom-admin-js',
			KCO_WC_PLUGIN_URL . '/assets/js/klarna-order-management.js',
			array( 'jquery' ),
			KCO_WC_VERSION,
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

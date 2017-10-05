<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class Klarna_Checkout_For_WooCommerce_Gateway extends WC_Payment_Gateway {

	/**
	 * Klarna_Checkout_For_WooCommerce_Gateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'klarna_checkout_for_woocommerce';
		$this->method_title       = __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' );
		$this->method_description = __( 'Klarna Checkout replaces standard WooCommerce checkout page.', 'klarna-checkout-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = apply_filters( 'klarna_checkout_for_woocommerce_supports', array( 'products' ) );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description', '' );
		$this->enabled       = $this->get_option( 'enabled' );
		$this->testmode      = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id   = $this->testmode ? $this->get_option( 'test_merchant_id_us' ) : $this->get_option( 'merchant_id_us', '' ); // @TODO: Test if live credentials are pulled when needed.
		$this->shared_secret = $this->testmode ? $this->get_option( 'test_shared_secret_us' ) : $this->get_option( 'shared_secret_us', '' );
		$this->logging       = 'yes' === $this->get_option( 'logging' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options',
		) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );

		// Add quantity button in woocommerce_order_review() function.
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'add_quantity_field' ), 10, 3 );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Initialise settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = Klarna_Checkout_For_WooCommerce_Fields::fields();
	}

	/**
	 * Checks if method should be available.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( is_order_received_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'klarna_checkout_for_woocommerce',
			plugins_url( 'assets/js/klarna-checkout-for-woocommerce' . $suffix . '.js', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ),
			array( 'jquery', 'wc-cart' ),
			KLARNA_CHECKOUT_FOR_WOOCOMMERCE_VERSION,
			true
		);

		wp_register_style(
			'klarna_checkout_for_woocommerce',
			plugins_url( 'assets/css/klarna-checkout-for-woocommerce' . $suffix . '.css', KLARNA_CHECKOUT_FOR_WOOCOMMERCE_MAIN_FILE ),
			array(),
			KLARNA_CHECKOUT_FOR_WOOCOMMERCE_VERSION
		);

		$checkout_localize_params = array(
			'update_cart_url'                 => WC_AJAX::get_endpoint( 'kco_wc_update_cart' ),
			'update_cart_nonce'               => wp_create_nonce( 'kco_wc_update_cart' ),
			'update_shipping_url'             => WC_AJAX::get_endpoint( 'kco_wc_update_shipping' ),
			'update_shipping_nonce'           => wp_create_nonce( 'kco_wc_update_shipping' ),
			'update_order_notes_url'          => WC_AJAX::get_endpoint( 'kco_wc_update_order_notes' ),
			'update_order_notes_nonce'        => wp_create_nonce( 'kco_wc_update_order_notes' ),
			'refresh_checkout_fragment_url'   => WC_AJAX::get_endpoint( 'kco_wc_refresh_checkout_fragment' ),
			'refresh_checkout_fragment_nonce' => wp_create_nonce( 'kco_wc_refresh_checkout_fragment' ),
			'iframe_change_url'               => WC_AJAX::get_endpoint( 'kco_wc_iframe_change' ),
			'iframe_change_nonce'             => wp_create_nonce( 'kco_wc_iframe_change' ),
		);
		wp_localize_script( 'klarna_checkout_for_woocommerce', 'klarna_checkout_for_woocommerce_params', $checkout_localize_params );

		wp_enqueue_script( 'klarna_checkout_for_woocommerce' );
		wp_enqueue_style( 'klarna_checkout_for_woocommerce' );
	}

	/**
	 * Displays Klarna Checkout thank you iframe and clears Klarna order ID value from WC session.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function show_thank_you_snippet( $order_id ) {
		if ( ! WC()->session->get( 'kco_wc_order_id' ) ) {
			return;
		}

		$klarna_order = KCO_WC()->api->get_order();
		echo KCO_WC()->api->get_snippet( $klarna_order );

		add_post_meta( $order_id, '_wc_klarna_order_id', $klarna_order->order_id );

		$environment = $this->testmode ? 'test' : 'live';
		update_post_meta( $order_id, '_wc_klarna_environment', $environment );

		$klarna_country = WC()->checkout()->get_value( 'billing_country' );
		update_post_meta( $order_id, '_wc_klarna_country', $klarna_country );
	}

	/**
	 * Filters cart item quantity output.
	 *
	 * @param string $output HTML output.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return string $output
	 */
	public function add_quantity_field( $output, $cart_item, $cart_item_key ) {
		if ( is_checkout() && 'klarna_checkout_for_woocommerce' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( WC()->cart->get_cart() as $cart_key => $cart_value ) {
				if ( $cart_key === $cart_item_key ) {
					$_product = $cart_item['data'];

					if ( $_product->is_sold_individually() ) {
						$return_value = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_key );
					} else {
						$return_value = woocommerce_quantity_input( array(
							'input_name'  => 'cart[' . $cart_key . '][qty]',
							'input_value' => $cart_item['quantity'],
							'max_value'   => $_product->backorders_allowed() ? '' : $_product->get_stock_quantity(),
							'min_value'   => '1',
						), $_product, false );
					}

					$output = $return_value;
				}
			}
		}

		return $output;
	}

}

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
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description', '' );
		$this->enabled          = $this->get_option( 'enabled' );
		$this->testmode         = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id      = $this->testmode ? $this->get_option( 'test_merchant_id_us' ) : $this->get_option( 'merchant_id_us', '' ); // @TODO: Test if live credentials are pulled when needed.
		$this->shared_secret    = $this->testmode ? $this->get_option( 'test_shared_secret_us' ) : $this->get_option( 'shared_secret_us', '' );
		$this->logging          = 'yes' === $this->get_option( 'logging' );

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

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Initialise settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters( 'klarna_checkout_for_woocommerce_gateway_settings', array(
			'enabled'               => array(
				'title'       => __( 'Enable/Disable', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                 => array(
				'title'       => __( 'Title', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'           => array(
				'title'       => __( 'Description', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'Pay with Klarna Checkout.', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'test_merchant_id_us'   => array(
				'title'       => __( 'Test merchant ID (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_us' => array(
				'title'       => __( 'Test shared secret (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_us'        => array(
				'title'       => __( 'Live merchant ID (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_us'      => array(
				'title'       => __( 'Live shared secret (US)', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode'              => array(
				'title'       => __( 'Test mode', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'               => array(
				'title'       => __( 'Logging', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		) );
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

		wp_enqueue_script( 'klarna_checkout_for_woocommerce' );
		wp_enqueue_style( 'klarna_checkout_for_woocommerce' );
	}

	/**
	 * Displays Klarna Checkout thank you iframe and clears Klarna order ID value from WC session.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function show_thank_you_snippet( $order_id ) {
		$klarna_order = KCO_WC()->api->get_order();
		echo KCO_WC()->api->get_snippet( $klarna_order );

		add_post_meta( $order_id, '_klarna_order_id', $klarna_order->order_id );
		WC()->session->__unset( 'kco_wc_order_id' );
		WC()->session->__unset( 'kco_wc_order_notes' );
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

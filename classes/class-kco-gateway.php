<?php
/**
 * Class file for KCO_Gateway class.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KCO_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class KCO_Gateway extends WC_Payment_Gateway {

	/**
	 * KCO_Gateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'kco';
		$this->method_title       = __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' );
		$this->method_description = __( 'The current Klarna Checkout replaces standard WooCommerce checkout page.', 'klarna-checkout-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = apply_filters(
			'kco_wc_supports',
			array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'multiple_subscriptions',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
			)
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->enabled                    = $this->get_option( 'enabled' );
		$this->testmode                   = 'yes' === $this->get_option( 'testmode' );
		$this->logging                    = 'yes' === $this->get_option( 'logging' );
		$this->shipping_methods_in_iframe = $this->get_option( 'shipping_methods_in_iframe' );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );

		add_action( 'woocommerce_checkout_init', array( $this, 'prefill_consent' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
		add_action( 'woocommerce_thankyou', 'kco_unset_sessions', 100, 1 );

		// Remove WooCommerce footer text from our settings page.
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 999 );

		// Body class for KSS.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		add_action( 'woocommerce_receipt_kco', array( $this, 'receipt_page' ) );
	}


	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_src  = 'https://cdn.klarna.com/1.0/shared/image/generic/logo/en_us/basic/logo_black.png?width=100';
		$icon_html = '<img src="' . $icon_src . '" alt="Klarna Checkout" style="border-radius:0px"/>';
		return apply_filters( 'wc_klarna_checkout_icon_html', $icon_html );
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

		// Order-pay purchase (or subscription payment method change)
		// 1. Redirect to receipt page.
		// 2. Process the payment by displaying the KCO iframe via woocommerce_receipt_kco hook.
		if ( isset( $_GET['change_payment_method'] ) ) {
			$pay_url = add_query_arg(
				array(
					'kco-action' => 'change-subs-payment',
				),
				$order->get_checkout_payment_url( true )
			);

			return array(
				'result'   => 'success',
				'redirect' => $pay_url,
			);
		}
		// Regular purchase.
		// 1. Process the payment.
		// 2. Redirect to order received page.
		if ( $this->process_payment_handler( $order_id ) ) {
			return array(
				'result'   => 'success',
				'redirect' => '#klarna-success=' . base64_encode( microtime() ), // Base64 encoded timestamp to always have a fresh URL for on hash change event.
			);
		} else {
			return array(
				'result' => 'error',
			);
		}

	}

	/**
	 * Receipt page. Used to display the KCO iframe during subscription payment method change.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function receipt_page( $order ) {
		if ( isset( $_GET['kco-action'] ) && 'change-subs-payment' === $_GET['kco-action'] ) {
			kco_wc_show_snippet();
		}
	}

	/**
	 * This plugin doesn't handle order management, but it allows Klarna Order Management plugin to process refunds
	 * and then return true or false.
	 *
	 * @param int      $order_id WooCommerce order ID.
	 * @param null|int $amount Refund amount.
	 * @param string   $reason Reason for refund.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'wc_klarna_checkout_process_refund', false, $order_id, $amount, $reason );
	}

	/**
	 * Initialise settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = KCO_Fields::fields();
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

		// If we can't retrieve a set of credentials, disable KCO.
		if ( is_checkout() && ! KCO_WC()->credentials->get_credentials_from_session() ) {
			return false;
		}

		// If we have a subscription product in cart and the customer isn't from SE, NO, FI, DE or AT, disable KCO.
		if ( is_checkout() && class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$available_recurring_countries = array( 'SE', 'NO', 'FI', 'DE', 'AT' );
			if ( ! in_array( WC()->customer->get_billing_country(), $available_recurring_countries, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add sidebar to the settings page.
	 */
	public function admin_options() {
		ob_start();
		parent::admin_options();
		$parent_options = ob_get_contents();
		ob_end_clean();

		WC_Klarna_Banners::settings_sidebar( $parent_options );
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! is_checkout() ) {
			return;
		}

		if ( is_order_received_page() ) {
			return;
		}

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		if ( ! kco_wc_prefill_allowed() ) {
			add_thickbox();
		}
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'kco',
			plugins_url( 'assets/js/klarna-checkout-for-woocommerce' . $suffix . '.js', KCO_WC_MAIN_FILE ),
			array( 'jquery', 'wc-cart' ),
			KCO_WC_VERSION,
			true
		);

		wp_register_style(
			'kco',
			plugins_url( 'assets/css/klarna-checkout-for-woocommerce' . $suffix . '.css', KCO_WC_MAIN_FILE ),
			array(),
			KCO_WC_VERSION
		);

		$form = false;
		if ( WC()->session->get( 'kco_checkout_form' ) ) {
			$form = WC()->session->get( 'kco_checkout_form' );
		}

		$email_exists = 'no';
		if ( method_exists( WC()->customer, 'get_billing_email' ) && ! empty( WC()->customer->get_billing_email() ) ) {
			if ( email_exists( WC()->customer->get_billing_email() ) ) {
				// Email exist in a user account.
				$email_exists = 'yes';
			}
		}

		$standard_woo_checkout_fields = array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', 'account_username', 'account_password', '_wp_http_referer' );

		$checkout_localize_params = array(
			'update_cart_url'                      => WC_AJAX::get_endpoint( 'kco_wc_update_cart' ),
			'update_cart_nonce'                    => wp_create_nonce( 'kco_wc_update_cart' ),
			'update_shipping_url'                  => WC_AJAX::get_endpoint( 'kco_wc_update_shipping' ),
			'update_shipping_nonce'                => wp_create_nonce( 'kco_wc_update_shipping' ),
			'change_payment_method_url'            => WC_AJAX::get_endpoint( 'kco_wc_change_payment_method' ),
			'change_payment_method_nonce'          => wp_create_nonce( 'kco_wc_change_payment_method' ),
			'update_klarna_order_url'              => WC_AJAX::get_endpoint( 'kco_wc_update_klarna_order' ),
			'update_klarna_order_nonce'            => wp_create_nonce( 'kco_wc_update_klarna_order' ),
			'iframe_shipping_address_change_url'   => WC_AJAX::get_endpoint( 'kco_wc_iframe_shipping_address_change' ),
			'iframe_shipping_address_change_nonce' => wp_create_nonce( 'kco_wc_iframe_shipping_address_change' ),
			'get_klarna_order_url'                 => WC_AJAX::get_endpoint( 'kco_wc_get_klarna_order' ),
			'get_klarna_order_nonce'               => wp_create_nonce( 'kco_wc_get_klarna_order' ),
			'log_to_file_url'                      => WC_AJAX::get_endpoint( 'kco_wc_log_js' ),
			'log_to_file_nonce'                    => wp_create_nonce( 'kco_wc_log_js' ),
			'logging'                              => $this->logging,
			'standard_woo_checkout_fields'         => $standard_woo_checkout_fields,
			'is_confirmation_page'                 => ( is_kco_confirmation() ) ? 'yes' : 'no',
			'shipping_methods_in_iframe'           => $this->shipping_methods_in_iframe,
			'required_fields_text'                 => __( 'Please fill in all required checkout fields.', 'klarna-checkout-for-woocommerce' ),
			'email_exists'                         => $email_exists,
			'must_login_message'                   => apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) ),
			'timeout_message'                      => __( 'Please try again, something went wrong with processing your order.', 'klarna-checkout-for-woocommerce' ),
			'timeout_time'                         => apply_filters( 'kco_checkout_timeout_duration', 10 ),
		);

		if ( version_compare( WC_VERSION, '3.9', '>=' ) ) {
			$checkout_localize_params['force_update'] = true;
		}
		wp_localize_script( 'kco', 'kco_params', $checkout_localize_params );

		wp_enqueue_script( 'kco' );
		wp_enqueue_style( 'kco' );
	}


	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @hook admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['section'] ) || 'kco' !== $_GET['section'] ) {
			return;
		}

		$suffix              = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$store_base_location = wc_get_base_location();
		if ( 'US' === $store_base_location['country'] ) {
			$location = 'US';
		} else {
			$location = $this->check_if_eu( $store_base_location['country'] );
		}

		wp_register_script(
			'kco_admin',
			plugins_url( 'assets/js/klarna-checkout-for-woocommerce-admin' . $suffix . '.js', KCO_WC_MAIN_FILE ),
			array(),
			KCO_WC_VERSION
		);
		$admin_localize_params = array(
			'location' => $location,
		);
		wp_localize_script( 'kco_admin', 'kco_admin_params', $admin_localize_params );
		wp_enqueue_script( 'kco_admin' );
	}

	/**
	 * Detect if EU country.
	 *
	 * @param string $store_base_location The WooCommerce stores base country.
	 */
	private function check_if_eu( $store_base_location ) {
		$eu_countries = array(
			'AL',
			'AD',
			'AM',
			'AT',
			'BY',
			'BE',
			'BA',
			'BG',
			'CH',
			'CY',
			'CZ',
			'DE',
			'DK',
			'EE',
			'ES',
			'FO',
			'FI',
			'FR',
			'GB',
			'GE',
			'GI',
			'GR',
			'HU',
			'HR',
			'IE',
			'IS',
			'IT',
			'LT',
			'LU',
			'LV',
			'MC',
			'MK',
			'MT',
			'NO',
			'NL',
			'PL',
			'PT',
			'RO',
			'RU',
			'SE',
			'SI',
			'SK',
			'SM',
			'TR',
			'UA',
			'VA',
		);

		if ( in_array( $store_base_location, $eu_countries, true ) ) {
			return 'EU';
		} else {
			return '';
		}
	}

	/**
	 * Process the payment with information from Klarna and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function process_payment_handler( $order_id ) {
		// Get the Klarna order ID.
		$order = wc_get_order( $order_id );
		if ( is_object( $order ) && $order->get_transaction_id() ) {
			$klarna_order_id = $order->get_transaction_id();
		} else {
			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
		if ( ! $klarna_order ) {
			return false;
		}

		if ( $order_id && $klarna_order ) {

			// Set WC order transaction ID.
			update_post_meta( $order_id, '_wc_klarna_order_id', sanitize_key( $klarna_order['order_id'] ) );

			update_post_meta( $order_id, '_transaction_id', sanitize_key( $klarna_order['order_id'] ) );

			$environment = $this->testmode ? 'test' : 'live';
			update_post_meta( $order_id, '_wc_klarna_environment', $environment );

			$klarna_country = wc_get_base_location()['country'];
			update_post_meta( $order_id, '_wc_klarna_country', $klarna_country );

			// Set shipping phone and email.
			update_post_meta( $order_id, '_shipping_phone', sanitize_text_field( $klarna_order['shipping_address']['phone'] ) );
			update_post_meta( $order_id, '_shipping_email', sanitize_text_field( $klarna_order['shipping_address']['email'] ) );

			// Update the order with new confirmation page url.
			$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id, $order_id );

			$order->save();
			// Let other plugins hook into this sequence.
			do_action( 'kco_wc_process_payment', $order_id, $klarna_order );

			// Check that the transaction id got set correctly.
			if ( get_post_meta( $order_id, '_transaction_id', true ) === $klarna_order_id ) {
				return true;
			}
		}
		// Return false if we get here. Something went wrong.
		return false;
	}

	/**
	 * Displays Klarna Checkout thank you iframe and clears Klarna order ID value from WC session.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function show_thank_you_snippet( $order_id = null ) {
		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( is_object( $order ) && $order->get_transaction_id() ) {
				$klarna_order_id = $order->get_transaction_id();

				$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
				if ( $klarna_order ) {
					echo $klarna_order['html_snippet'];
				}

				// Check if we need to finalize purchase here. Should already been done in process_payment.
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					kco_confirm_klarna_order( $order_id, $klarna_order_id );
					WC()->cart->empty_cart();
				}
			}
		}
	}

	/**
	 * Changes footer text in KCO settings page.
	 *
	 * @param string $text Footer text.
	 *
	 * @return string
	 */
	public function admin_footer_text( $text ) {
		if ( isset( $_GET['section'] ) && 'kco' === $_GET['section'] ) {
			$text = 'If you like Klarna Checkout for WooCommerce, please consider <strong>assigning Krokedil as your integration partner.</strong>.';
		}

		return $text;
	}

	/**
	 * Adds can't edit address notice to KP EU orders.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 */
	public function address_notice( $order ) {
		if ( $this->id === $order->get_payment_method() ) {
			echo '<div style="clear:both; margin: 10px 0; padding: 10px; border: 1px solid #B33A3A; font-size: 12px">';
			esc_html_e( 'Order address should not be changed and any changes you make will not be reflected in Klarna system.', 'klarna-checkout-for-woocommerce' );
			echo '</div>';
		}
	}

	/**
	 * Adds prefill consent to WC session.
	 */
	public function prefill_consent() {
		if ( isset( $_GET['prefill_consent'] ) ) { // Input var okay.
			if ( 'yes' === sanitize_text_field( $_GET['prefill_consent'] ) ) {
				WC()->session->set( 'kco_wc_prefill_consent', true );
			}
		}
	}

	/**
	 * Add kco-shipping-display body class.
	 *
	 * @param array $class Array of classes.
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() && 'yes' === $this->shipping_methods_in_iframe ) {
			// Don't display KCO Shipping Display body classes if we have a cart that doesn't needs payment.
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $class;
			}

			$first_gateway = '';
			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}
			if ( 'kco' === $first_gateway ) {
				$class[] = 'kco-shipping-display';
			}
		}
		return $class;
	}
}

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
		$this->id                 = 'kco';
		$this->method_title       = __( 'Klarna Checkout', 'klarna-checkout-for-woocommerce' );
		$this->method_description = __( 'Klarna Checkout replaces standard WooCommerce checkout page.', 'klarna-checkout-for-woocommerce' );
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
			)
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->enabled  = $this->get_option( 'enabled' );
		$this->testmode = 'yes' === $this->get_option( 'testmode' );
		$this->logging  = 'yes' === $this->get_option( 'logging' );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'prefill_consent' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'show_log_in_notice' ) );

		// Remove WooCommerce footer text from our settings page.
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 999 );
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
		krokedil_set_order_gateway_version( $order_id, KCO_WC_VERSION );

		$this->process_payment_handler( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
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

		// If we can't retrieve a set of credentials, disable KCO.
		if ( is_checkout() && ! KCO_WC()->credentials->get_credentials_from_session() ) {
			return false;
		}

		// If we have a subscription product in cart and the customer isn't from SE, NO, FI, DE or AT, disable KCO.
		if ( is_checkout() && class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$available_recurring_countries = array( 'SE', 'NO', 'FI', 'DE', 'AT' );
			if ( ! in_array( WC()->customer->get_billing_country(), $available_recurring_countries ) ) {
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

		$standard_woo_checkout_fields = array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'terms', 'account_username', 'account_password' );

		$checkout_localize_params = array(
			'update_cart_url'                      => WC_AJAX::get_endpoint( 'kco_wc_update_cart' ),
			'update_cart_nonce'                    => wp_create_nonce( 'kco_wc_update_cart' ),
			'update_shipping_url'                  => WC_AJAX::get_endpoint( 'kco_wc_update_shipping' ),
			'update_shipping_nonce'                => wp_create_nonce( 'kco_wc_update_shipping' ),
			'update_extra_fields_url'              => WC_AJAX::get_endpoint( 'kco_wc_update_extra_fields' ),
			'update_extra_fields_nonce'            => wp_create_nonce( 'kco_wc_update_extra_fields' ),
			'change_payment_method_url'            => WC_AJAX::get_endpoint( 'kco_wc_change_payment_method' ),
			'change_payment_method_nonce'          => wp_create_nonce( 'kco_wc_change_payment_method' ),
			'update_klarna_order_url'              => WC_AJAX::get_endpoint( 'kco_wc_update_klarna_order' ),
			'update_klarna_order_nonce'            => wp_create_nonce( 'kco_wc_update_klarna_order' ),
			'iframe_shipping_address_change_url'   => WC_AJAX::get_endpoint( 'kco_wc_iframe_shipping_address_change' ),
			'iframe_shipping_address_change_nonce' => wp_create_nonce( 'kco_wc_iframe_shipping_address_change' ),
			'checkout_error_url'                   => WC_AJAX::get_endpoint( 'kco_wc_checkout_error' ),
			'checkout_error_nonce'                 => wp_create_nonce( 'kco_wc_checkout_error' ),
			'logging'                              => $this->logging,
			'save_form_data'                       => WC_AJAX::get_endpoint( 'kco_wc_save_form_data' ),
			'save_form_data_nonce'                 => wp_create_nonce( 'kco_wc_save_form_data' ),
			'form'                                 => $form,
			'standard_woo_checkout_fields'         => $standard_woo_checkout_fields,
			'is_confirmation_page'                 => ( is_kco_confirmation() ) ? 'yes' : 'no',
		);

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

		$suffix              = '';// defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$store_base_location = wc_get_base_location();
		if ( 'US' === $store_base_location['country'] ) {
			$location = 'US';
		} else {
			$location = $this->check_if_eu( $store_base_location['country'] );
		}

		wp_register_script(
			'kco_admin',
			plugins_url( 'assets/js/klarna-checkout-for-woocommerce-admin.js', KCO_WC_MAIN_FILE ),
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

		if ( in_array( $store_base_location, $eu_countries ) ) {
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
	 * @return void
	 */
	public function process_payment_handler( $order_id ) {
		// Get the Klarna order ID.
		$order = wc_get_order( $order_id );
		if ( is_object( $order ) && $order->get_transaction_id() ) {
			$klarna_order_id = $order->get_transaction_id();
		} else {
			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
			KCO_WC()->logger->log( '$klarna_order_id fetched from session in process_payment_handler. Order ID ' . $order_id . '.' );
		}

		$response = KCO_WC()->api->request_pre_get_order( $klarna_order_id, $order_id );

		if ( $order_id && ! is_wp_error( $response ) ) {

			$klarna_order = json_decode( $response['body'] );

			// Set WC order transaction ID.
			// update_post_meta( $order_id, '_wc_klarna_order_id', sanitize_key( $klarna_order->order_id ) );
			// update_post_meta( $order_id, '_transaction_id', sanitize_key( $klarna_order->order_id ) );
			$environment = $this->testmode ? 'test' : 'live';
			update_post_meta( $order_id, '_wc_klarna_environment', $environment );

			$klarna_country = wc_get_base_location()['country'];
			update_post_meta( $order_id, '_wc_klarna_country', $klarna_country );

			$response = KCO_WC()->api->request_post_get_order( $klarna_order->order_id, $order_id );
			if ( is_wp_error( $response ) ) {
				// Request error. Set order status to On hold and print the error message as a note.
				$error = KCO_WC()->api->extract_error_messages( $response );
				$note  = sprintf( __( 'Klarna post_order request in process_payment_handler failed. Error message: %s.', 'klarna-checkout-for-woocommerce' ), stripslashes_deep( json_encode( $error ) ) );
				$order->update_status( 'on-hold', $note );
			} else {
				$klarna_post_order = json_decode( $response['body'] );

				// Remove html_snippet from what we're logging.
				$log_order               = clone $klarna_post_order;
				$log_order->html_snippet = '';
				krokedil_log_events( $order_id, 'Klarna post_order in process_payment_handler', $log_order );
				KCO_WC()->logger->log( 'Order processed (process_payment_handler) for Klarna order ID ' . $klarna_order_id . '. WC order ID ' . $order_id . '. Post order info ' . stripslashes_deep( json_encode( $log_order ) ) );
				if ( 'ACCEPTED' === $klarna_post_order->fraud_status ) {
					$order->payment_complete();
					// translators: Klarna order ID.
					$note = sprintf( __( 'Payment via Klarna Checkout, order ID: %s', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order->order_id ) );
					$order->add_order_note( $note );
				} elseif ( 'REJECTED' === $klarna_post_order->fraud_status ) {
					$order->update_status( 'on-hold', __( 'Klarna Checkout order was rejected.', 'klarna-checkout-for-woocommerce' ) );
				} elseif ( 'PENDING' === $klarna_post_order->fraud_status ) {
					// translators: Klarna order ID.
					$note = sprintf( __( 'Klarna order is under review, order ID: %s.', 'klarna-checkout-for-woocommerce' ), sanitize_key( $klarna_order->order_id ) );
					$order->update_status( 'on-hold', $note );
				}
			}

			// Acknowledge order in Klarna.
			KCO_WC()->api->request_post_acknowledge_order( $klarna_order->order_id );
			KCO_WC()->api->request_post_set_merchant_reference(
				$klarna_order->order_id,
				array(
					'merchant_reference1' => $order->get_order_number(),
					'merchant_reference2' => $order->get_id(),
				)
			);
		}
	}

	/**
	 * Displays Klarna Checkout thank you iframe and clears Klarna order ID value from WC session.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function show_thank_you_snippet( $order_id = null ) {
		if ( ! WC()->session->get( 'kco_wc_order_id' ) ) {
			return;
		}

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( is_object( $order ) && $order->get_transaction_id() ) {
				$klarna_order_id = $order->get_transaction_id();
				$response        = KCO_WC()->api->request_pre_get_order( $klarna_order_id, $order_id );
				if ( ! is_wp_error( $response ) ) {
					$klarna_order = json_decode( $response['body'] );
					echo KCO_WC()->api->get_snippet( $klarna_order );
				}
			}

			// Check if we need to finalize purchase here. Should already been done in process_payment.
			if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
				$this->process_payment_handler( $order_id );
				$order->add_order_note( __( 'Order finalized in thankyou page.', 'klarna-checkout-for-woocommerce' ) );
				WC()->cart->empty_cart();
			}
		}

		// Clear session storage to prevent error.
		echo '<script>sessionStorage.orderSubmitted = false</script>';
	}

	/**
	 * Changes footer text in KCO settings page.
	 *
	 * @TODO: Get a URL to link to.
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
	 * Show notice that tells customers they need to log in.
	 */
	public function show_log_in_notice() {
		if ( isset( $_GET['login_required'] ) && 'yes' === $_GET['login_required'] ) {
			wc_add_notice(
				sanitize_textarea_field(
					__(
						'An account is already registered with your email address. Please log in.',
						'klarna-checkout-for-woocommerce'
					)
				),
				'error'
			);
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

}

<?php
namespace Krokedil\KustomCheckout\Blocks;

use Automattic\WooCommerce\StoreApi\SessionHandler;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class ValidationCallback
 */
class ValidationCallback {
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_api_kco-block-validation', array( $this, 'validate_kco_order' ) );
	}

	/**
	 * Validate the hashes to ensure the order is valid.
	 *
	 * @param string $klarna_hash The hash from Klarna.
	 * @param string $wc_hash The hash from WooCommerce.
	 *
	 * @return void
	 * @throws Exception If the hashes do not match.
	 */
	private function validate_hash( $klarna_hash, $wc_hash ) {
		if ( $klarna_hash !== $wc_hash ) {
			throw new Exception( 'Invalid request, hashes do not match.' );
		}
	}

	/**
	 * Validate the hashes to ensure the order is valid.
	 *
	 * @param array          $klarna_order The Klarna order.
	 * @param SessionHandler $session The WooCommerce session handler.
	 *
	 * @return void
	 * @throws Exception If the hashes do not match.
	 */
	private function validate_hashes( $klarna_order, $session ) {
		$klarna_merchant_data = json_decode( $klarna_order['merchant_data'], true ) ?? array();

		// Calculate the hashes for the cart and coupons applied in the session.
		$wc_totals       = $session->get( 'cart_totals', array() );
		$wc_cart_hash    = md5( wp_json_encode( $session->get( 'cart', array() ) ) . $wc_totals['total'] ?? 0 );
		$wc_coupons_hash = md5( wp_json_encode( $session->get( 'applied_coupons', array() ) ) );

		$klarna_cart_hash    = $klarna_merchant_data['wc_cart_hash'] ?? '';
		$klarna_coupons_hash = $klarna_merchant_data['wc_coupons_hash'] ?? '';

		// Compare the strings and ensure they are the same.
		$this->validate_hash( $klarna_cart_hash, $wc_cart_hash );
		$this->validate_hash( $klarna_coupons_hash, $wc_coupons_hash );
	}

	/**
	 * Get the WooCommerce order and ensure it exists.
	 *
	 * @param string $merchant_reference The merchant reference. Should match the order ID in WooCommerce.
	 *
	 * @return \WC_Order
	 * @throws Exception If the order could not be found.
	 */
	private function get_wc_order( $merchant_reference ) {
		$order = wc_get_order( $merchant_reference );

		if ( ! $order ) {
			throw new Exception( 'Could not find the WooCommerce order.' );
		}

		return $order;
	}

	/**
	 * Get the Klarna order and ensure that its valid.
	 *
	 * @return array
	 * @throws Exception If the request is invalid or the Klarna order could not be found.
	 */
	private function get_klarna_order() {
		$klarna_order_id = sanitize_text_field( wp_unslash( $_GET['kco_order_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $klarna_order_id ) ) {
			throw new Exception( 'Invalid request, klarna order id missing from the callback.' );
		}

		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

		if ( ! $klarna_order || is_wp_error( $klarna_order ) ) {
			throw new Exception( 'Could not find the Klarna order.' );
		}

		return $klarna_order;
	}

	/**
	 * Validate the price to ensure the order is valid.
	 *
	 * @param int   $klarna_price The price from Klarna.
	 * @param float $wc_price The price from WooCommerce.
	 *
	 * @return void
	 * @throws Exception If the prices do not match.
	 */
	private function validate_price( $klarna_price, $wc_price ) {
		// Divide the Klarna price by 100, to make it a floating point number.
		$klarna_price = floatval( $klarna_price ) / 100;

		$klarna_price = wc_format_decimal( $klarna_price, wc_get_price_decimals() );
		$wc_price     = wc_format_decimal( $wc_price, wc_get_price_decimals() );

		if ( $klarna_price !== $wc_price ) {
			throw new Exception( 'Prices do not match.' );
		}
	}

	/**
	 * Validate the order totals to ensure the order is valid.
	 *
	 * @param array    $klarna_order The Klarna order.
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	private function validate_order_totals( $klarna_order, $order ) {
		$selected_shipping                   = $klarna_order['selected_shipping_option'] ?? array();
		$klarna_order_amount                 = $klarna_order['order_amount'] ?? 0;
		$klarna_order_tax_amount             = $klarna_order['order_tax_amount'] ?? 0;
		$klarna_selected_shipping_price      = $selected_shipping['price'] ?? 0;
		$klarna_selected_shipping_tax_amount = $selected_shipping['tax_amount'] ?? 0;

		$klarna_total     = $klarna_order_amount + $klarna_selected_shipping_price;
		$klarna_tax_total = $klarna_order_tax_amount + $klarna_selected_shipping_tax_amount;

		$this->validate_price( $klarna_total, $order->get_total() );
		$this->validate_price( $klarna_tax_total, $order->get_total_tax() );
	}

	/**
	 * Validate the WooCommerce order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If the order is invalid.
	 */
	private function validate_wc_order( $order ) {
		// Check if the order is paid.
		if ( $order->is_paid() ) {
			throw new Exception( 'Order is already paid.' );
		}
	}

	/**
	 * Load the WooCommerce cart.
	 *
	 * @param array $klarna_order The Klarna order.
	 *
	 * @return SessionHandler
	 * @throws Exception If the cart could not be loaded.
	 */
	private function load_wc_session( $klarna_order ) {
		// Get the wc_cart_token from the klarna order merchant data.
		$klarna_merchant_data = json_decode( $klarna_order['merchant_data'], true ) ?? array();
		$wc_cart_token        = $klarna_merchant_data['wc_cart_token'] ?? '';

		if ( empty( $wc_cart_token ) ) {
			throw new Exception( 'Invalid request, cart token is missing.' );
		}

		if ( ! JsonWebToken::validate( $wc_cart_token, '@' . wp_salt() ) ) {
			throw new Exception( 'Invalid request, cart token is invalid.' );
		}

		// Set the CartToken header to the server session.
		$_SERVER['HTTP_CART_TOKEN'] = $wc_cart_token;
		$session                    = new SessionHandler();
		$session->init();
		// Unset the shutdown action on the session handler. To prevent saving the session after we are done with it.
		remove_action( 'shutdown', array( $session, 'save_data' ), 20 );

		return $session;
	}

	/**
	 * Submit the WooCommerce order.
	 *
	 * @param array    $klarna_order The Klarna order.
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If the order could not be submitted.
	 */
	private function submit_wc_order( $klarna_order, $order ) {
		$settings                = get_option( 'woocommerce_kco_settings', array() );
		$klarna_billing_address  = $klarna_order['billing_address'] ?? array();
		$klarna_shipping_address = $klarna_order['shipping_address'] ?? array();

		$klarna_merchant_data = json_decode( $klarna_order['merchant_data'], true ) ?? array();
		$wc_cart_token        = $klarna_merchant_data['wc_cart_token'] ?? '';
		$wc_nonce             = $klarna_merchant_data['wc_nonce'] ?? '';
		$wp_logged_in_cookie  = $klarna_merchant_data['wp_logged_in_cookie'] ?? null;

		$request_url = rest_url( 'wc/store/v1/checkout/' . $order->get_id() );

		$body = array(
			'key'              => $order->get_order_key(),
			'payment_method'   => 'kco',
			'payment_data'     => array(
				array(
					'key'   => '_wc_klarna_checkout_flow',
					'value' => 'embedded',
				),
				array(
					'key'   => '_wc_klarna_order_id',
					'value' => $klarna_order['order_id'],
				),
				array(
					'key'   => '_wc_klarna_environment',
					'value' => 'yes' === $settings['testmode'] ? 'test' : 'live',
				),
				array(
					'key'   => '_wc_klarna_country',
					'value' => wc_get_base_location()['country'],
				),
				array(
					'key'   => '_shipping_email',
					'value' => $klarna_shipping_address['email'] ?? '',
				),
				array(
					'key'   => '_shipping_phone',
					'value' => $klarna_shipping_address['phone'] ?? '',
				),
			),
			'billing_address'  => array(
				'first_name' => $klarna_billing_address['given_name'] ?? '',
				'last_name'  => $klarna_billing_address['family_name'] ?? '',
				'address_1'  => $klarna_billing_address['street_address'] ?? '',
				'city'       => $klarna_billing_address['city'] ?? '',
				'state'      => $klarna_billing_address['region'] ?? '',
				'postcode'   => $klarna_billing_address['postal_code'] ?? '',
				'country'    => $klarna_billing_address['country'] ?? '',
				'email'      => $klarna_billing_address['email'] ?? '',
				'phone'      => $klarna_billing_address['phone'] ?? '',
			),
			'shipping_address' => array(
				'first_name' => $klarna_shipping_address['given_name'] ?? '',
				'last_name'  => $klarna_shipping_address['family_name'] ?? '',
				'address_1'  => $klarna_shipping_address['street_address'] ?? '',
				'city'       => $klarna_shipping_address['city'] ?? '',
				'state'      => $klarna_shipping_address['region'] ?? '',
				'postcode'   => $klarna_shipping_address['postal_code'] ?? '',
				'country'    => $klarna_shipping_address['country'] ?? '',
				// 'phone'      => $klarna_shipping_address['phone'] ?? '',
			),
		);

		// If the klarna order is a recurring order.
		if ( isset( $klarna_order['recurring'] ) ) {
			$body[] = array(
				'key'   => '_kco_recurring_order',
				'value' => $klarna_order['recurring'] ? 'yes' : 'no',
			);
		}

		if ( isset( $klarna_order['recurring_token'] ) ) {
			$body[] = array(
				'key'   => '_kco_recurring_token',
				'value' => $klarna_order['recurring_token'],
			);
		}

		// Set the request args.
		$request_args = array(
			'method'  => 'POST',
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
				'CartToken'    => sanitize_text_field( wp_unslash( $wc_cart_token ) ),
				'Nonce'        => $wc_nonce,
			),
			'body'    => wp_json_encode( $body ),
		);

		if ( $wp_logged_in_cookie ) {
			$request_args['cookies'] = array(
				new \WP_Http_Cookie(
					array(
						'name'  => LOGGED_IN_COOKIE,
						'value' => $wp_logged_in_cookie,
					)
				),
			);
		}

		// Submit the order to the store api.
		$response = wp_remote_post( $request_url, $request_args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Could not submit the order to WooCommerce.' );
		}

		// Check if the response is not a 2xx.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 > $response_code || 300 <= $response_code ) {
			throw new Exception( 'Could not submit the order to WooCommerce.' );
		}

		$order_response = json_decode( wp_remote_retrieve_body( $response ), true );
		$updated_order  = wc_get_order( $order_response['order_id'] ?? null );

		if ( ! $updated_order ) {
			throw new Exception( 'Could not find the WooCommerce order.' );
		}

		// Compare the order hashes to the once stored in the merchant data to ensure the order is valid.
		$this->validate_hash( $klarna_merchant_data['wc_cart_hash'], $updated_order->get_cart_hash() );
		$this->validate_hash( $klarna_merchant_data['wc_shipping_hash'], $updated_order->get_meta( '_shipping_hash' ) );
		$this->validate_hash( $klarna_merchant_data['wc_fees_hash'], $updated_order->get_meta( '_fees_hash' ) );
		$this->validate_hash( $klarna_merchant_data['wc_coupons_hash'], $updated_order->get_meta( '_coupons_hash' ) );
		$this->validate_hash( $klarna_merchant_data['wc_taxes_hash'], $updated_order->get_meta( '_taxes_hash' ) );
	}

	/**
	 * Validate the Klarna order.
	 *
	 * @return void
	 * @throws Exception If the request is invalid or the order could not be verified.
	 */
	public function validate_kco_order() {
		try {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['kco_validation'] ) || 'yes' !== $_GET['kco_validation'] ) {
				throw new Exception( 'Invalid request, missing kco_validation.' );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$klarna_order = $this->get_klarna_order();

			// Get the merchant reference from the klarna order.
			$merchant_reference = $klarna_order['merchant_reference2'];

			// Get the order from the merchant reference.
			$order = $this->get_wc_order( $merchant_reference );

			$this->validate_wc_order( $order );

			// Attempt to submit the order to WooCommerce using the store api.
			$this->submit_wc_order( $klarna_order, $order );

			header( 'HTTP/1.1 200 OK' );
			die();
		} catch ( Exception $e ) {
			$klarna_order_id = sanitize_text_field( wp_unslash( $_GET['kco_order_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			\KCO_Logger::log( "$klarna_order_id - Failed order validation: " . $e->getMessage() );
			echo wp_json_encode(
				array(
					'error_type' => 'approval_failed',
					'error_text' => __( 'The order could not be verified, please reload checkout page and try again.', 'klarna-checkout-for-woocommerce' ),
				)
			);
			header( 'HTTP/1.1 400 Bad Request' );
			die();
		}
	}
}

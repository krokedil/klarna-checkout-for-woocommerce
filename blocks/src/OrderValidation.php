<?php
namespace Krokedil\KustomCheckout\Blocks;

use Automattic\WooCommerce\StoreApi\SessionHandler;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderValidation.
 *
 * Handles the order validation and submission for Kustom Checkout orders placed with the block checkout.
 * Ensures the order is valid, and able to be submitted to WooCommerce.
 */
class OrderValidation {
	/**
	 * Validate the Klarna order.
	 *
	 * @param string $klarna_order_id The Klarna order ID.
	 *
	 * @return void
	 * @throws Exception If the request is invalid or the order could not be verified.
	 */
	public static function validate_kco_order( $klarna_order_id ) {
		if ( empty( $klarna_order_id ) ) {
			throw new Exception( 'Could not validate the order, please try again.', 400 );
		}

		$klarna_order         = self::get_klarna_order( $klarna_order_id );
		$merchant_reference   = $klarna_order['merchant_reference2']; // Get the merchant reference from the klarna order.
		$klarna_merchant_data = json_decode( $klarna_order['merchant_data'], true ) ?? array();
		// If the merchant reference or klarna merchant data is empty, throw an exception.
		if ( empty( $merchant_reference ) || empty( $klarna_merchant_data ) ) {
			throw new Exception( 'Could not validate the order, please try again.', 400 );
		}

		// Get the order from the merchant reference.
		$order = self::get_wc_order( $merchant_reference );

		// Set the klarna order id in the order meta data.
		$order->update_meta_data( '_wc_klarna_order_id', sanitize_key( $klarna_order_id ) );
		$order->save_meta_data();
		self::validate_wc_order( $order );

		// Attempt to submit the order to WooCommerce using the store api.
		$updated_order = self::submit_wc_order( $klarna_order, $order );

		// Compare the order hashes to the once stored in the merchant data to ensure the order is valid.
		self::validate_hash( $klarna_merchant_data['wc_cart_hash'], $updated_order->get_cart_hash() );
		self::validate_hash( $klarna_merchant_data['wc_shipping_hash'], $updated_order->get_meta( '_shipping_hash' ) );
		self::validate_hash( $klarna_merchant_data['wc_fees_hash'], $updated_order->get_meta( '_fees_hash' ) );
		self::validate_hash( $klarna_merchant_data['wc_coupons_hash'], $updated_order->get_meta( '_coupons_hash' ) );
		self::validate_hash( $klarna_merchant_data['wc_taxes_hash'], $updated_order->get_meta( '_taxes_hash' ) );
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
	private static function validate_hash( $klarna_hash, $wc_hash ) {
		if ( $klarna_hash !== $wc_hash ) {
			throw new Exception( 'Could not validate the order, please try again.', 401 );
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
	private static function validate_hashes( $klarna_order, $session ) {
		$klarna_merchant_data = json_decode( $klarna_order['merchant_data'], true ) ?? array();

		// Calculate the hashes for the cart and coupons applied in the session.
		$wc_totals       = $session->get( 'cart_totals', array() );
		$wc_cart_hash    = md5( wp_json_encode( $session->get( 'cart', array() ) ) . $wc_totals['total'] ?? 0 );
		$wc_coupons_hash = md5( wp_json_encode( $session->get( 'applied_coupons', array() ) ) );

		$klarna_cart_hash    = $klarna_merchant_data['wc_cart_hash'] ?? '';
		$klarna_coupons_hash = $klarna_merchant_data['wc_coupons_hash'] ?? '';

		// Compare the strings and ensure they are the same.
		self::validate_hash( $klarna_cart_hash, $wc_cart_hash );
		self::validate_hash( $klarna_coupons_hash, $wc_coupons_hash );
	}

	/**
	 * Get the WooCommerce order and ensure it exists.
	 *
	 * @param string $merchant_reference The merchant reference. Should match the order ID in WooCommerce.
	 *
	 * @return \WC_Order
	 * @throws Exception If the order could not be found.
	 */
	private static function get_wc_order( $merchant_reference ) {
		$order = wc_get_order( $merchant_reference );

		if ( ! $order ) {
			throw new Exception( 'Could not find the order, please try again.', 404 );
		}

		return $order;
	}

	/**
	 * Get the Klarna order and ensure that its valid.
	 *
	 * @param string $klarna_order_id The Klarna order ID.
	 *
	 * @return array
	 * @throws Exception If the request is invalid or the Klarna order could not be found.
	 */
	private static function get_klarna_order( $klarna_order_id ) {
		$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );

		if ( ! $klarna_order || is_wp_error( $klarna_order ) ) {
			throw new Exception( 'Could not find the order, please try again.', 404 );
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
	private static function validate_price( $klarna_price, $wc_price ) {
		// Divide the Klarna price by 100, to make it a floating point number.
		$klarna_price = floatval( $klarna_price ) / 100;

		$klarna_price = wc_format_decimal( $klarna_price, wc_get_price_decimals() );
		$wc_price     = wc_format_decimal( $wc_price, wc_get_price_decimals() );

		if ( $klarna_price !== $wc_price ) {
			throw new Exception( 'Failed to validate the order, please try again', 401 );
		}
	}

	/**
	 * Validate the order totals to ensure the order is valid.
	 *
	 * @param array     $klarna_order The Klarna order.
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	private static function validate_order_totals( $klarna_order, $order ) {
		$selected_shipping                   = $klarna_order['selected_shipping_option'] ?? array();
		$klarna_order_amount                 = $klarna_order['order_amount'] ?? 0;
		$klarna_order_tax_amount             = $klarna_order['order_tax_amount'] ?? 0;
		$klarna_selected_shipping_price      = $selected_shipping['price'] ?? 0;
		$klarna_selected_shipping_tax_amount = $selected_shipping['tax_amount'] ?? 0;

		$klarna_total     = $klarna_order_amount + $klarna_selected_shipping_price;
		$klarna_tax_total = $klarna_order_tax_amount + $klarna_selected_shipping_tax_amount;

		self::validate_price( $klarna_total, $order->get_total() );
		self::validate_price( $klarna_tax_total, $order->get_total_tax() );
	}

	/**
	 * Validate the WooCommerce order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If the order is invalid.
	 */
	private static function validate_wc_order( $order ) {
		// Check if the order is paid.
		if ( $order->is_paid() ) {
			throw new Exception( 'Order is already paid.', 400 );
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
	private static function load_wc_session( $klarna_order ) {
		// Get the wc_cart_token from the klarna order merchant data.
		$klarna_merchant_data = json_decode( $klarna_order['merchant_data'], true ) ?? array();
		$wc_cart_token        = $klarna_merchant_data['wc_cart_token'] ?? '';

		if ( empty( $wc_cart_token ) ) {
			throw new Exception( 'Failed to validate the order, please try again.', 400 );
		}

		if ( ! JsonWebToken::validate( $wc_cart_token, '@' . wp_salt() ) ) {
			throw new Exception( 'Failed to validate the order, please try again.', 401 );
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
	 * @param array     $klarna_order The Klarna order.
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return \WC_Order The updated WooCommerce order after submission.
	 * @throws Exception If the order could not be submitted.
	 */
	private static function submit_wc_order( $klarna_order, $order ) {
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
			'billing_email'    => $klarna_billing_address['email'] ?? '',
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
			),
		);

		// Set the billing company name if it exists.
		if ( isset( $klarna_billing_address['organization_name'] ) ) {
			$body['billing_address']['company'] = $klarna_billing_address['organization_name'];
		}

		// Set the shipping company name if it exists.
		if ( isset( $klarna_shipping_address['organization_name'] ) ) {
			$body['shipping_address']['company'] = $klarna_shipping_address['organization_name'];
		}

		// If the klarna order is a recurring order.
		if ( isset( $klarna_order['recurring'] ) ) {
			$body['payment_data'][] = array(
				'key'   => '_kco_recurring_order',
				'value' => $klarna_order['recurring'] ? 'yes' : 'no',
			);
		}

		if ( isset( $klarna_order['recurring_token'] ) ) {
			$body['payment_data'][] = array(
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

		return $updated_order;
	}
}

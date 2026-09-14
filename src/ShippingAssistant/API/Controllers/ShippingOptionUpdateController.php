<?php
namespace Krokedil\KustomCheckout\ShippingAssistant\API\Controllers;

\defined( 'ABSPATH' ) || exit;

/**
 * Class ShippingOptionUpdateController.
 *
 * Controller for handling the shipping option update callback from Kustom for TMS-controlled shipping.
 */
class ShippingOptionUpdateController extends BaseController {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'callback';

	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the callback route for the controller.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'shipping-option-update' ),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_shipping_option_update' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);
	}

	/**
	 * Verify the request before processing it.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return bool True if the request is valid, false otherwise.
	 */
	public function verify_request( $request ) {
		// Get the KCO order ID from the request parameters.
		$kco_id = $request->get_param( 'kco_id' );

		// If the KCO order ID is not present, return false.
		if ( ! $kco_id ) {
			return false;
		}

		// Get the order from Kustom.
		$kco_order = KCO_WC()->api->get_klarna_order( $kco_id );

		// If the order is not found return false.
		if ( is_wp_error( $kco_order ) ) {
			return false;
		}

		// Ensure the order has the correct status.
		if ( 'checkout_incomplete' !== $kco_order['status'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle the shipping option update callback for Kustom Shipping Assistant.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 * @throws \Exception If an error occurs while processing the request.
	 */
	public function handle_shipping_option_update( $request ) {
		$body   = $request->get_json_params();
		$kco_id = $request->get_param( 'kco_id' );

		try {
			// If the body is empty, return an error response.
			if ( empty( $body ) ) {
				throw new \Exception( 'Request body is empty' );
			}

			// Get the selected shipping option from the request body.
			$selected_shipping_option = $body['selected_shipping_option'] ?? null;

			// If the selected shipping option is not set, return an error response.
			if ( ! $selected_shipping_option ) {
				throw new \Exception( 'Selected shipping option not provided' );
			}

			// Return the response body.
			return $this->success_response( $this->get_response_body( $body, $kco_id ) );
		} catch ( \Exception $e ) {
			KCO_WC()->logger->log( '[KSA Callback] shipping option update failed for kco_id ' . $kco_id . ': ' . $e->getMessage() );
			return new \WP_REST_Response( array( 'error' => 'An error occurred while processing the request' ), 500 );
		}
	}

	/**
	 * Get response body
	 *
	 * @param array  $body The request body.
	 * @param string $kco_id The KCO order id.
	 *
	 * @return array The response body.
	 */
	private function get_response_body( $body, $kco_id ) {
		$shipping_option     = $this->build_shipping_option( $body );
		$shipping_order_line = $this->build_shipping_order_line( $shipping_option );

		// If the order is a free trial subscription, shipping must be free, so zero out the price and tax.
		if ( $this->is_free_trial_shipping( $body ) ) {
			$this->apply_free_trial_override( $shipping_order_line );
		}

		$order_lines = $this->build_order_lines( $body, $shipping_order_line );
		$response    = $this->build_response( $body, $order_lines );

		// Set the override transient to be used in the shipping method class.
		$this->store_override_data( $kco_id, $shipping_option );

		return $this->filter_response( $response );
	}

	/**
	 * Build the shipping option from the request body, including the calculated shipping tax.
	 *
	 * @param array $body The request body.
	 *
	 * @return array The shipping option.
	 */
	private function build_shipping_option( $body ) {
		$shipping_tax_rates  = $this->get_shipping_tax_rates( $body['billing_address'] ?? array(), $body['shipping_address'] ?? array() );
		$shipping_tax_amount = $this->calculate_shipping_tax_amount( $body['selected_shipping_option']['price'] ?? 0, $shipping_tax_rates );

		$selected_shipping_option = $body['selected_shipping_option'] ?? array();

		return array(
			'id'               => $selected_shipping_option['id'] ?? 'default_shipping_option',
			'name'             => $selected_shipping_option['name'] ?? 'Default Shipping Option',
			'price'            => $selected_shipping_option['price'] ?? 0,
			'tax_amount'       => $shipping_tax_amount,
			'tax_rate'         => $this->get_tax_rate_percentage( $shipping_tax_rates ),
			'preselected'      => $selected_shipping_option['preselected'] ?? true,
			'shipping_method'  => $selected_shipping_option['shipping_method'] ?? null,
			'delivery_details' => $selected_shipping_option['delivery_details'] ?? null,
			'tms_reference'    => $selected_shipping_option['tms_reference'] ?? null,
			'tos_id'           => $selected_shipping_option['tos_id'] ?? null,
			'selected_addons'  => $selected_shipping_option['selected_addons'] ?? null,
		);
	}

	/**
	 * Get the shipping tax rate as a whole-number percentage.
	 *
	 * @param array $shipping_tax_rates The shipping tax rates.
	 *
	 * @return int The tax rate as a percentage (e.g. 25 for 25%).
	 */
	private function get_tax_rate_percentage( $shipping_tax_rates ) {
		$tax_rate = $shipping_tax_rates ? reset( $shipping_tax_rates ) : null;
		$tax_rate = $tax_rate ? $tax_rate['rate'] : 0;

		return intval( round( $tax_rate * 100 ) ); // Convert to percentage.
	}

	/**
	 * Build the shipping order line from the shipping option.
	 *
	 * @param array $shipping_option The shipping option.
	 *
	 * @return array The shipping order line.
	 */
	private function build_shipping_order_line( $shipping_option ) {
		return array(
			'type'             => 'shipping_fee',
			'reference'        => $shipping_option['id'],
			'name'             => $shipping_option['name'],
			'quantity'         => 1,
			'unit_price'       => $shipping_option['price'],
			'tax_rate'         => $shipping_option['tax_rate'],
			'total_amount'     => $shipping_option['price'],
			'total_tax_amount' => $shipping_option['tax_amount'],
		);
	}

	/**
	 * Whether the order is a free trial subscription where shipping should be free.
	 *
	 * @param array $body The request body.
	 *
	 * @return bool True if the free trial shipping tag is present.
	 */
	private function is_free_trial_shipping( $body ) {
		return in_array( 'ksa_subscription_free_trial_shipping', $body['tags'] ?? array(), true );
	}

	/**
	 * Zero out the shipping price and tax on the shipping order line.
	 *
	 * @param array $shipping_order_line The shipping order line (modified by reference).
	 *
	 * @return void
	 */
	private function apply_free_trial_override( &$shipping_order_line ) {
		$shipping_order_line['unit_price']       = 0;
		$shipping_order_line['total_amount']     = 0;
		$shipping_order_line['total_tax_amount'] = 0;
	}

	/**
	 * Build the order lines, replacing any existing shipping line with the new one.
	 *
	 * @param array $body                The request body.
	 * @param array $shipping_order_line The shipping order line to append.
	 *
	 * @return array The order lines.
	 */
	private function build_order_lines( $body, $shipping_order_line ) {
		$order_lines = $body['order_lines'] ?? array();

		// Remove any existing shipping order lines from the order lines array.
		$order_lines = array_filter(
			$order_lines,
			function ( $line ) {
				return ( $line['type'] ?? '' ) !== 'shipping_fee';
			}
		);

		$order_lines[] = $shipping_order_line;

		return $order_lines;
	}

	/**
	 * Sum a given amount field across all order lines.
	 *
	 * @param array  $order_lines The order lines.
	 * @param string $key         The order line field to sum (e.g. 'total_amount').
	 *
	 * @return int The summed amount.
	 */
	private function calculate_order_total( $order_lines, $key ) {
		return array_reduce(
			$order_lines,
			function ( $carry, $line ) use ( $key ) {
				return $carry + ( $line[ $key ] ?? 0 );
			},
			0
		);
	}

	/**
	 * Build the response body returned to Kustom.
	 *
	 * @param array $body        The request body.
	 * @param array $order_lines The order lines.
	 *
	 * @return array The response body.
	 */
	private function build_response( $body, $order_lines ) {
		return array(
			'order_amount'             => $this->calculate_order_total( $order_lines, 'total_amount' ),
			'order_tax_amount'         => $this->calculate_order_total( $order_lines, 'total_tax_amount' ),
			'merchant_data'            => $body['merchant_data'] ?? null,
			'order_lines'              => $order_lines,
			'attachments'              => $body['attachments'] ?? null,
			'purchase_currency'        => $body['purchase_currency'] ?? get_woocommerce_currency(),
			'locale'                   => $body['locale'] ?? get_locale(),
			'external_payment_methods' => $body['external_payment_methods'] ?? null,
			'tags'                     => $body['tags'] ?? null,
		);
	}

	/**
	 * Store the shipping override data in a transient for use in the shipping method class.
	 *
	 * @param string $kco_id          The KCO order id.
	 * @param array  $shipping_option The shipping option.
	 *
	 * @return void
	 */
	private function store_override_data( $kco_id, $shipping_option ) {
		$kss_override_data = array(
			'name'       => $shipping_option['name'] ?? null,
			'price'      => $shipping_option['price'] ?? null,
			'tax_rate'   => $shipping_option['tax_rate'] ?? null,
			'tax_amount' => $shipping_option['tax_amount'] ?? null,
		);

		set_transient( "kss_override_data_$kco_id", $kss_override_data, HOUR_IN_SECONDS );
	}

	/**
	 * Get the WooCommerce tax rates to use for the customer address and shipping price.
	 *
	 * @param array $billing_address  The customer billing address details.
	 * @param array $shipping_address The customer shipping address details.
	 *
	 * @return array The tax rates to use for the shipping option.
	 */
	private function get_shipping_tax_rates( $billing_address, $shipping_address ) {
		$customer = new \WC_Customer();
		$customer->set_billing_country( $billing_address['country'] ?? null );
		$customer->set_billing_state( $billing_address['state'] ?? null );
		$customer->set_billing_postcode( $billing_address['postal_code'] ?? null );
		$customer->set_shipping_country( $shipping_address['country'] ?? null );
		$customer->set_shipping_state( $shipping_address['state'] ?? null );
		$customer->set_shipping_postcode( $shipping_address['postal_code'] ?? null );

		/**
		 *  Get the shipping tax rate for the customer's location.
		 *
		 *  We need to pass the default tax class (empty string) to ensure we don't attempt to get it from the cart, which is not available in this context.
		 *  If the tax class is not configured with any rates that matches the customers location, this will return an empty array,
		 *  but only if there is no set shipping tax class, that always overrides the passed shipping tax class. Meaning the calculation will still
		 *  match what WooCommerce would calculate as well, since if no shipping tax is found that matches the cart if its set to inherit, it will return an empty array as well.
		 *
		 * @see https://github.com/woocommerce/woocommerce/blob/04789354c046f36835f629b7e3ccfc66d4173f17/plugins/woocommerce/includes/class-wc-tax.php#L566-L583
		 */
		$tax_rates = \WC_Tax::get_shipping_tax_rates( '', $customer );

		// If we don't have any tax rates, return an empty array.
		if ( empty( $tax_rates ) ) {
			return array();
		}

		// Get the first key and rate from the tax rates.
		$rate_id = key( $tax_rates );
		$rate    = $tax_rates[ $rate_id ];

		return array(
			$rate_id => $rate,
		);
	}

	/**
	 * Calculate the shipping tax amount based on the price including vat and the customer address details.
	 *
	 * @param int   $price The price including vat.
	 * @param array $tax_rates The tax rates for the shipping option.
	 *
	 * @return int The calculated tax amount.
	 */
	private function calculate_shipping_tax_amount( $price, $tax_rates ) {
		// Divide the price by 100 to get it in major units.
		$price = $price / 100;

		// Calculate the tax amount using the WooCommerce tax functions.
		$tax_amount = \WC_Tax::calc_tax( $price, $tax_rates, true );

		// Sum the array and multiply by 100 to get it back in minor units.
		$tax_amount = intval( round( array_sum( $tax_amount ) * 100 ) );

		return $tax_amount;
	}

	/**
	 * Filter out any null values from the response to prevent Kustom from throwing an error.
	 *
	 * @param array $response The response to filter.
	 *
	 * @return array The filtered response.
	 */
	private function filter_response( $response ) {
		foreach ( $response as $key => $value ) {
			// If the value is an array, we need to filter it recursively.
			if ( is_array( $value ) ) {
				$value = $this->filter_response( $value );
				if ( empty( $value ) ) {
					unset( $response[ $key ] );
					continue;
				}
				$response[ $key ] = $value;
				continue;
			}

			// If the value is null, remove it from the response.
			if ( null === $value ) {
				unset( $response[ $key ] );
			}
		}
		return $response;
	}

	/**
	 * Return a successful response.
	 *
	 * @param array $response_body The response body to return.
	 *
	 * @return \WP_REST_Response
	 */
	public function success_response( $response_body = array() ) {
		return new \WP_REST_Response( $response_body, 200 );
	}
}

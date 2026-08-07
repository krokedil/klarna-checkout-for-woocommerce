<?php
namespace Krokedil\KustomCheckout\Blocks;

use Krokedil\KustomCheckout\Blocks\Api\Registry;
use Krokedil\KustomCheckout\Blocks\Checkout\CheckoutBlock;
use Krokedil\KustomCheckout\Blocks\Schema\AddressSchema;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class BlockExtension.
 *
 * Handles all block registration with WooCommerce, and loading of the blocks dependencies,
 * and registers the needed callback hooks for the WooCommerce Store API thats needed.
 */
class BlockExtension {
	/**
	 * Order controller instance.
	 *
	 * @var Registry
	 */
	private $api_registry;

	/**
	 * Overrides instance.
	 *
	 * @var Overrides
	 */
	private $overrides;

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Initialize the checkout block dependencies.
		$this->init_checkout_block();
	}

	/**
	 * Initialize checkout block dependencies.
	 *
	 * @return void
	 */
	public function init_checkout_block() {
		// Register woocommerce_blocks_loaded callbacks immediately — this action fires
		// during plugins_loaded so hooks must be registered before it fires.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_callbacks' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_method' ) );

		// Defer the is_checkout_block_enabled() check to init so wc_get_page_id() is not
		// called before WordPress has initialized — prevents _doing_it_wrong notices in
		// WP 6.7+ from third-party plugins that call is_page() or load text domains early.
		add_action(
			'init',
			function () {
				if ( ! BlocksUtility::is_checkout_block_enabled() ) {
					return;
				}
				$this->overrides    = new Overrides();
				$this->api_registry = new Registry();
			},
			5
		);
	}

	/**
	 * Register the callbacks for the block.
	 *
	 * @return void
	 */
	public function register_callbacks() {
		// Register the callback for the update API.
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'kco-block',
				'callback'  => function ( $data ) {
					$this->block_callback( $data );
				},
			)
		);

		// Register the schema and callback for the extended cart data.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => 'kco_address',
				'data_callback'   => array( $this, 'get_address' ),
				'schema_callback' => array( AddressSchema::class, 'get_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Callback for the update API.
	 *
	 * @param array $data The data from the API.
	 *
	 * @return void
	 */
	public function block_callback( $data ) {
		switch ( $data['action'] ) {
			case 'address_changed':
				$this->address_changed( $data );
				break;
			case 'shipping_address_changed':
				// Kept for shoppers still running a cached copy of the previous script.
				$this->shipping_address_changed( $data );
				break;
			case 'shipping_option_changed':
				// Ensure we have KCO set as the chosen payment method,
				// this is needed to ensure that the Kustom Shipping Assistant is available for the package.
				WC()->session->set( 'chosen_payment_method', 'kco' );

				kco_update_wc_shipping( $data );
				break;
			case 'load':
				// No action needed here for now. We just want to trigger an update of the WooCommerce cart.
				break;
			default:
				break;
		}
	}

	/**
	 * Register the payment method with the instance of the class.
	 *
	 * @return void
	 */
	public function register_method() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			if ( ! file_exists( __DIR__ . '/Checkout/CheckoutBlock.php' ) ) {
				return;
			}

			require_once __DIR__ . '/Checkout/CheckoutBlock.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( $payment_method_registry ) {
					$payment_method_registry->register( new CheckoutBlock() );
				}
			);
		}
	}

	/**
	 * Update the customer addresses in WooCommerce on change from Kustom.
	 *
	 * Kustom only emits shipping_address_change once the customer has entered a separate shipping
	 * address. While they ship to their billing address we only get billing_address_change, so the
	 * script sends the billing address as the shipping address as well in that case.
	 *
	 * @param array $data The data from the API.
	 *
	 * @return void
	 */
	public function address_changed( $data ) {
		$this->set_customer_address( 'billing', $data['billing'] ?? array() );
		$this->set_customer_address( 'shipping', $data['shipping'] ?? array() );
	}

	/**
	 * Set one of the customer addresses in WooCommerce from a Kustom address.
	 *
	 * @param string $type    The address type, either 'billing' or 'shipping'.
	 * @param array  $address The address from Kustom.
	 *
	 * @return void
	 */
	private function set_customer_address( $type, $address ) {
		if ( ! is_array( $address ) || empty( $address ) ) {
			return;
		}

		$fields = array(
			'postal_code' => 'postcode',
			'city'        => 'city',
			'country'     => 'country',
			'given_name'  => 'first_name',
			'family_name' => 'last_name',
		);

		foreach ( $fields as $klarna_field => $wc_field ) {
			// Only set the data if the field is set, and skip empty values to not clear what we already have.
			if ( ! isset( $address[ $klarna_field ] ) || '' === $address[ $klarna_field ] ) {
				continue;
			}

			$setter = "set_{$type}_{$wc_field}";

			// Ensure the method exists before calling it to avoid fatal errors.
			if ( ! method_exists( WC()->customer, $setter ) ) {
				continue;
			}

			WC()->customer->$setter( $address[ $klarna_field ] );
		}
	}

	/**
	 * Update the shipping address in WooCommerce on change from Kustom.
	 *
	 * @deprecated Superseded by address_changed(), which handles both address types.
	 *
	 * @param array $data The data from the API.
	 *
	 * @return void
	 */
	public function shipping_address_changed( $data ) {
		$this->set_customer_address( 'shipping', $data );
	}

	/**
	 * Get the address data for the Kustom Checkout block. Also updates the Kustom order if needed.
	 *
	 * @return array
	 * @throws Exception If we can't get the Kustom order.
	 */
	public function get_address() {
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		// Only run this if we have a Kustom order id.
		if ( ! $klarna_order_id ) {
			return array();
		}

		// Maybe update the Kustom order.
		$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );

		// If we did not get a Kustom order, get it instead.
		if ( ! $klarna_order ) {
			$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
		}

		// If we still don't have a Kustom order, throw an exception.
		if ( ! $klarna_order ) {
			throw new Exception( 'Could not get Kustom order' );
		}

		// Convert the billing region to unicode format.
		if ( isset( $klarna_order['billing_address']['region'] ) ) {
			$region                                    = $klarna_order['billing_address']['region'];
			$country                                   = $klarna_order['billing_address']['country'];
			$klarna_order['billing_address']['region'] = kco_convert_region( $region, $country );
		}

		// Convert the shipping region to unicode format.
		if ( isset( $klarna_order['shipping_address']['region'] ) ) {
			$region                                     = $klarna_order['shipping_address']['region'];
			$country                                    = $klarna_order['shipping_address']['country'];
			$klarna_order['shipping_address']['region'] = kco_convert_region( $region, $country );
		}

		return array(
			'billing_address'  => isset( $klarna_order['billing_address'] ) ? $klarna_order['billing_address'] : array(),
			'shipping_address' => isset( $klarna_order['shipping_address'] ) ? $klarna_order['shipping_address'] : array(),
		);
	}
}

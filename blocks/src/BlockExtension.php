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
		if ( ! BlocksUtility::is_checkout_block_enabled() ) {
			return;
		}

		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			if ( file_exists( __DIR__ . '/Checkout/CheckoutBlock.php' ) ) {
				require_once __DIR__ . '/Checkout/CheckoutBlock.php';
				add_action(
					'woocommerce_blocks_payment_method_type_registration',
					function ( $payment_method_registry ) {
						$payment_method_registry->register( new CheckoutBlock() );
					}
				);
			}
		}

		$this->register_callbacks();

		// Load dependencies for the checkout block.
		$this->overrides    = new Overrides();
		$this->api_registry = new Registry();
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
			case 'shipping_address_changed':
				$this->shipping_address_changed( $data );
				break;
			case 'shipping_option_changed':
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
	 * Update the shipping address in WooCommerce on change from Klarna.
	 *
	 * @param array $data The data from the API.
	 *
	 * @return void
	 */
	public function shipping_address_changed( $data ) {
		// Only set the data if the field is set.
		if ( isset( $data['postal_code'] ) ) {
			WC()->customer->set_shipping_postcode( $data['postal_code'] );
		}

		if ( isset( $data['country'] ) ) {
			WC()->customer->set_shipping_country( $data['country'] );
		}

		if ( isset( $data['given_name'] ) ) {
			WC()->customer->set_shipping_first_name( $data['given_name'] );
		}

		if ( isset( $data['family_name'] ) ) {
			WC()->customer->set_shipping_last_name( $data['family_name'] );
		}
	}

	/**
	 * Get the address data for the Klarna Checkout block. Also updates the Klarna order if needed.
	 *
	 * @return array
	 * @throws Exception If we can't get the Klarna order.
	 */
	public function get_address() {
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );

		// Only run this if we have a Klarna order id.
		if ( ! $klarna_order_id ) {
			return array();
		}

		// Maybe update the Klarna order.
		$klarna_order = KCO_WC()->api->update_klarna_order( $klarna_order_id );

		// If we did not get a Klarna order, get it instead.
		if ( ! $klarna_order ) {
			$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
		}

		// If we still don't have a Klarna order, throw an exception.
		if ( ! $klarna_order ) {
			throw new Exception( 'Could not get Klarna order' );
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

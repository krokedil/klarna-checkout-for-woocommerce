<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping method class.
 *
 * Represents a TMS-controlled shipping method. Its rate is populated from the `kss_data_*` transient that
 * Kustom Checkout writes when the customer selects a shipping option inside the Kustom iframe.
 */
class ShippingMethod extends \WC_Shipping_Method {

	/**
	 * The shipping tax amount.
	 *
	 * @var false|float
	 */
	public $kss_tax_amount = false;

	/**
	 * The shipping total amount.
	 *
	 * @var float
	 */
	public $kss_total_amount = 0;

	/**
	 * Class constructor.
	 *
	 * @param integer $instance_id The instance id.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'klarna_kss';
		$this->instance_id        = absint( $instance_id );
		$this->title              = 'Kustom Shipping Assistant';
		$this->method_title       = __( 'Kustom Shipping Assistant', 'klarna-checkout-for-woocommerce' );
		$this->method_description = __( 'Enables Kustom Shipping Assistant for WooCommerce', 'klarna-checkout-for-woocommerce' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);
		$this->init_form_fields();
		$this->init_settings();
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Kustom Shipping Assistant', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'There are currently no settings for Kustom Shipping Assistant since this is controlled by the TMS-provider. If other plugins adds settings, these are shown below.', 'klarna-checkout-for-woocommerce' ),
			),
		);
	}

	/**
	 * Check if shipping method should be available.
	 *
	 * @param array $package The shipping package.
	 * @return boolean
	 */
	public function is_available( $package ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WC_Shipping_Method::is_available() signature.
		if ( null !== WC()->session->get( 'kco_kss_enabled' ) && WC()->session->get( 'kco_kss_enabled' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Calculate shipping cost.
	 *
	 * @param array $package The shipping package.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by WC_Shipping_Method::calculate_shipping() signature.
		$label           = 'Kustom Shipping Assistant';
		$cost            = 0;
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		$shipping_data   = get_transient( 'kss_data_' . $klarna_order_id );
		$rate            = array();

		if ( ! empty( $shipping_data ) ) {
			// If we have the override data for the shipping option, use that.
			$override_data = get_transient( "kss_override_data_$klarna_order_id" );

			if ( $override_data ) {
				$shipping_data['price']      = $override_data['price'] ?? $shipping_data['price'];
				$shipping_data['name']       = $override_data['name'] ?? $shipping_data['name'];
				$shipping_data['tax_rate']   = $override_data['tax_rate'] ?? $shipping_data['tax_rate'];
				$shipping_data['tax_amount'] = $override_data['tax_amount'] ?? $shipping_data['tax_amount'];
			}

			if ( isset( $shipping_data['shipping_method'] ) && 'digital' === strtolower( $shipping_data['shipping_method'] ) ) {
				add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
				return;
			}

			$label = $shipping_data['name'];
			if ( apply_filters( 'woocommerce_shipping_prices_include_tax', false ) ) {
				// Shipping prices are entered including tax in WooCommerce — pass Kustom's price through as-is and let WooCommerce reverse-calculate the tax.
				$cost = floatval( $shipping_data['price'] ) / 100;
			} else {
				// Shipping prices are entered excluding tax in WooCommerce (default) - we need to calculate the actual tax cost and subtract that from the total.
				$cost = floatval( round( $shipping_data['price'] / ( 1 + ( $shipping_data['tax_rate'] / 10000 ) ), 2 ) ) / 100;
			}
			$tax_amount             = floatval( $shipping_data['tax_amount'] ) / 100;
			$this->kss_tax_amount   = $tax_amount;
			$this->kss_total_amount = $cost;
			$rate                   = array(
				'id'    => $this->get_rate_id(),
				'label' => $label,
				'cost'  => $cost,
			);

			/* Kustom already converts the shipping cost to the purchase currency. To avoid double-conversion, we must pass the currency onto the currency switchers. */
			if ( isset( $shipping_data['currency'] ) ) {
				$rate['meta_data'] = array(
					'currency' => $shipping_data['currency'],
				);

				/* WPML do not respect the meta data currency property. */
				global $woocommerce_wpml;
				if ( isset( $woocommerce_wpml ) && \WCML_MULTI_CURRENCIES_INDEPENDENT === $woocommerce_wpml->settings['enable_multi_currency'] ) {
					$rate['cost'] = $woocommerce_wpml->multi_currency->prices->unconvert_price_amount( $rate['cost'], $shipping_data['currency'] );
				}
			}
		}

		$this->add_rate( apply_filters( 'klarna_kss_shipping_method_add_rate', $rate ) );
	}
}

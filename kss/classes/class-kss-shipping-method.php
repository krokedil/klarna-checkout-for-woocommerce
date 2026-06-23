<?php // phpcs:ignore
/**
 * Shipping method class file.
 *
 * @package KlarnaShippingService/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Shipping_Method' ) ) {

	/**
	 * Shipping method class.
	 */
	class KSS_Shipping_Method extends WC_Shipping_Method {

		/**
		 *
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
			$this->method_title       = __( 'Kustom Shipping Assistant', 'klarna-shipping-service-for-woocommerce' );
			$this->method_description = __( 'Enables Kustom Shipping Assistant for WooCommerce', 'klarna-shipping-service-for-woocommerce' );
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
					'title'       => __( 'Kustom Shipping Assistant', 'klarna-shipping-service-for-woocommerce' ),
					'type'        => 'title',
					'description' => __( 'There are currently no settings for Kustom Shipping Assistant since this is controlled by the TMS-provider. If other plugins adds settings, these are shown below.', 'klarna-shipping-service-for-woocommerce' ),
				),
			);
		}

		/**
		 * Check if shipping method should be available.
		 *
		 * @param array $package The shipping package.
		 * @return boolean
		 */
		public function is_available( $package ) {
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
		public function calculate_shipping( $package = array() ) {
			$label           = 'Kustom Shipping Assistant';
			$cost            = 0;
			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
			$shipping_data   = get_transient( 'kss_data_' . $klarna_order_id );
			$rate            = array();
			if ( ! empty( $shipping_data ) ) {
				if ( isset( $shipping_data['shipping_method'] ) && 'digital' === strtolower( $shipping_data['shipping_method'] ) ) {
					add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
					return;
				}

				$label = $shipping_data['name'];
				// To prevent rounding issues from Kustom sending us a max of 2 decimals, we need to calculate the actual tax cost and subtract that from the total.
				$cost                   = floatval( round( $shipping_data['price'] / ( 1 + ( $shipping_data['tax_rate'] / 10000 ) ), 2 ) ) / 100;
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
					if ( isset( $woocommerce_wpml ) && $woocommerce_wpml->settings['enable_multi_currency'] == WCML_MULTI_CURRENCIES_INDEPENDENT ) {
						$rate['cost'] = $woocommerce_wpml->multi_currency->prices->unconvert_price_amount( $rate['cost'], $shipping_data['currency'] );
					}
				}
			}
			$this->add_rate( apply_filters( 'klarna_kss_shipping_method_add_rate', $rate ) );
		}
	}

	add_filter( 'woocommerce_shipping_methods', 'add_kss_shipping_method' );
	/**
	 * Registers the shipping method.
	 *
	 * @param array $methods WooCommerce shipping methods.
	 * @return array
	 */
	function add_kss_shipping_method( $methods ) {
		$methods['klarna_kss'] = 'KSS_Shipping_Method';
		return $methods;
	}
}

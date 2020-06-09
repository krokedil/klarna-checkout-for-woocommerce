<?php // phpcs:ignore
/**
 * Helper product class
 */

/**
 * This is the class just for testing purpose
 *
 * @package Krokedil/tests
 */
class Krokedil_Shipping_Rate {

	/**
	 * Stores data for this rate.
	 *
	 * @var   array $data data.
	 */
	protected $data = array(
		'id'          => '',
		'method_id'   => '',
		'instance_id' => 0,
		'label'       => '',
		'cost'        => 0,
		'taxes'       => array(),
	);

	/**
	 * Shipping rate
	 *
	 * @var WC_Shipping_Rate $shipping_rate
	 */
	protected $shipping_rate = null;

	/**
	 * Shipping taxes
	 *
	 * @var array $shipping_taxes taxes.
	 */
	protected $shipping_taxes = null;

	/**
	 * Krokedil_Shipping_Rate constructor.
	 *
	 * @param array $data data.
	 * @param array $shipping_taxes taxes.
	 */
	public function __construct( array $data = [], array $shipping_taxes = [] ) {
		$this->data = wp_parse_args( $data, $this->data );
		$this->set_shipping_taxes( $shipping_taxes );
		$this->set_shipping_rate();
	}

	/**
	 * Sets taxes.
	 *
	 * @param array $shipping_taxes taxes.
	 */
	private function set_shipping_taxes( $shipping_taxes ) {
		if ( empty( $shipping_taxes ) ) {
			$this->shipping_taxes = WC_Tax::calc_shipping_tax( '10', WC_Tax::get_shipping_tax_rates() );
		} else {
			$this->shipping_rate = $shipping_taxes;
		}
	}

	/**
	 * Sets shipping rate.
	 */
	private function set_shipping_rate() {
		$id                  = 'flat_rate_shipping';
		$label               = 'Flat rate shipping';
		$flat_rate           = 'flat_rate';
		$this->shipping_rate = new WC_Shipping_Rate( $id, $label, $this->shipping_taxes, $flat_rate );
	}

	/**
	 * Returns shipping rate.
	 */
	public function get_shipping_rate() :WC_Shipping_Rate {
		if ( null !== $this->shipping_rate ) {
			return $this->shipping_rate;
		}

		return null;
	}
}

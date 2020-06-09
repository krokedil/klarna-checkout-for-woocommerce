<?php // phpcs:ignore
/**
 * Helper product class
 */

/**
 * This is the class just for testing purpose
 *
 * @package Krokedil/tests
 */
class Krokedil_Order_Item_Shipping {

	/**
	 * Data.
	 *
	 * @var array $data data.
	 */
	protected $data = [
		'method_title' => '',
		'method_id'    => '',
		'instance_id'  => '',
		'total'        => 0,
		'total_tax'    => 0,
		'taxes'        => array(
			'total' => array(),
		),
	];

	/**
	 * Item shipping.
	 *
	 * @var WC_Order_Item_Shipping $order_item_shipping item shipping.
	 */
	protected $order_item_shipping = null;

	/**
	 * Krokedil_Order_Item_Shipping constructor.
	 *
	 * @param array $data data.
	 */
	public function __construct( array $data = [] ) {
		$this->data                = wp_parse_args( $data, $this->data );
		$this->order_item_shipping = new WC_Order_Item_Shipping();
		$this->order_item_shipping->set_props( $this->data );
	}

	/**
	 * Returns data.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Returns order item shipping.
	 *
	 * @return WC_Order_Item_Shipping
	 */
	public function get_order_items_shipping(): WC_Order_Item_Shipping {
		return $this->order_item_shipping;
	}
}

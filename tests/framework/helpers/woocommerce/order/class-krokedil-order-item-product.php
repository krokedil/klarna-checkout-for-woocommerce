<?php // phpcs:ignore
/**
 * Helper order class
 */

/**
 * This is the class just for testing purpose
 *
 * @package Krokedil/tests
 */

/**
 * Class Krokedil_Order.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class Krokedil_Order_Item_Product implements IKrokedil_Order_Item_Product {

	/**
	 * Order Data array
	 *
	 * @var array $data
	 */
	protected $data = [
		'product_id'   => 0,
		'variation_id' => 0,
		'quantity'     => 1,
		'tax_class'    => '',
		'subtotal'     => 0,
		'subtotal_tax' => 0,
		'total'        => 0,
		'total_tax'    => 0,
		'taxes'        => [
			'subtotal' => [],
			'total'    => [],
		],
	];

	/**
	 * Krokedil_Order_item_Product constructor.
	 *
	 * @param array $data order data.
	 */
	public function __construct( $data = [] ) {
		$this->data = wp_parse_args( $data, $this->data );
	}


	/**
	 * Indicate whether to save or not.
	 *
	 * @return bool
	 */
	public function save(): bool {
		return true;
	}

	/**
	 * Creates order item.
	 */
	public function create(): WC_Order_Item_Product {
		$item = new WC_Order_Item_Product();
		$item->set_props( $this->data );
		if ( $this->save() ) {
			$item->save();
		}
		return $item;
	}
}

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
 * Krokedil helper class.
 */
interface IKrokedil_Order_Item_Product {

	/**
	 * Indicate whether to save or not.
	 *
	 * @return bool
	 */
	public function save() : bool;

	/**
	 * Creates WooCommerce product
	 *
	 * @return WC_Product
	 */
	public function create() : WC_Order_Item_Product;

}

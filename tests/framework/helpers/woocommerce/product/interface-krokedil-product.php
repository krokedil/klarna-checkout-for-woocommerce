<?php //phpcs:ignore

/**
 * Interface
 *
 * @package Krokedil/tests
 */

/**
 * Interface.
 */
interface IKrokedil_WC_Product extends IKrokedil_WC {

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
	public function create() : WC_Product;

}

<?php //phpcs:ignore

/**
 * Interface
 *
 * @package Krokedil/tests
 */

/**
 * Interface.
 */
interface IKrokedil_WC_Order extends IKrokedil_WC {

	/**
	 * Indicate whether to save or not.
	 *
	 * @return bool
	 */
	public function save() : bool;

	/**
	 * Creates WooCommerce order.
	 *
	 * @return WC_Order
	 */
	public function create() : WC_Order;


}

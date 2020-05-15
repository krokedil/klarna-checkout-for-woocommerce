<?php //phpcs:ignore

/**
 * Interface
 *
 * @package Krokedil/tests
 */

/**
 * Interface.
 */
interface IKrokedil_WC {

	/**
	 * Indicate whether to save or not.
	 *
	 * @return bool
	 */
	public function save() : bool;

	/**
	 * Creates WooCommerce object
	 */
	public function create();

}

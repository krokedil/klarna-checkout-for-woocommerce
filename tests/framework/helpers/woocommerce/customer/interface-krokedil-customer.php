<?php //phpcs:ignore

/**
 *
 */
interface IKrokedil_Customer extends IKrokedil_WC {

	/**
	 * Creates customer
	 *
	 * @return WC_Customer
	 */
	public function create() : WC_Customer;

}

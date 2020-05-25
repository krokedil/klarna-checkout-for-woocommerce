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
interface Krokedil_Order_Status {

	/**
	 * List of order statuses
	 */
	const STATUSES = [
		self::PENDING,
		self::ON_HOLD,
		self::FAILED,
		self::PROCESSING,
		self::CANCELLED,
		self::COMPLETED,
		self::REFUNDED,
	];

	const PENDING    = 'Pending';
	const ON_HOLD    = 'On-Hold';
	const FAILED     = 'Failed';
	const PROCESSING = 'Processing';
	const CANCELLED  = 'Cancelled';
	const COMPLETED  = 'Completed';
	const REFUNDED   = 'Refunded';

	/**
	 * Returns order statuses
	 *
	 * @return array order statuses
	 */
	public function get_statuses() : array;

}

<?php
/**
 * Class Krokedil_DB_Helper file.
 *
 * @package Krokedil_DB_Helper\Helpers
 */

/**
 * Class Krokedil_DB_Helper file.
 */
class Krokedil_DB_Helper {

	/**
	 * Singleton instance
	 *
	 * @var Krokedil_DB_Helper
	 */
	private static $instance = null;

	/**
	 * Krokedil_DB_Helper constructor.
	 */
	protected function __construct() {}

	/**
	 * Deletes all records from a table
	 *
	 * @param string $table_name table name.
	 */
	public static function truncate_table( string $table_name ) {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );// phpcs:ignore
	}

	/**
	 * Get the single class instance.
	 *
	 * @since 1.0.0
	 * @return Krokedil_DB_Helper
	 */
	public static function get_instance(): \Krokedil_DB_Helper {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

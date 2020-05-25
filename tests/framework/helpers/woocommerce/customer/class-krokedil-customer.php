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
 * Class Krokedil_Customer.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class Krokedil_Customer implements IKrokedil_Customer {

	/**
	 * @var array
	 */
	protected $data = [
		'id'                  => 1,
		'date_modified'       => null,
		'billing_country'     => 'US',
		'billing_state'       => 'PA',
		'billing_postcode'    => '19123',
		'billing_city'        => 'Philadelphia',
		'billing_address'     => '123 South Street',
		'billing_address_2'   => 'Apt 1',
		'shipping_country'    => 'US',
		'shipping_state'      => 'PA',
		'shipping_postcode'   => '19123',
		'shipping_city'       => 'Philadelphia',
		'shipping_address'    => '123 South Street',
		'shipping_address_2'  => 'Apt 1',
		'is_vat_exempt'       => false,
		'calculated_shipping' => false,
		'username'            => 'testusername',
		'password'            => 'testpass',
		'email'               => 'customer@local.com',

	];

	/**
	 * Krokedil_Customer constructor.
	 *
	 * @param array $data data.
	 */
	public function __construct( array $data = [] ) {
		$this->data = wp_parse_args( $data, $this->data );
	}

	/**
	 * Creates customer
	 *
	 * @return WC_Customer
	 * @throws Exception If customer not exist.
	 */
	public function create(): WC_Customer {
		$customer = new WC_Customer();
		foreach ( $this->data as $key => $value ) {
			$customer->{"set_$key"}( $value );
		}

		if ( $this->save() ) {
			$customer->save();
		}
		return $customer;

	}

	/**
	 * Get the expected output for the store's base location settings.
	 *
	 * @return array
	 */
	public static function get_expected_store_location() {
		return array( 'GB', '', '', '' );
	}

	/**
	 * Get the customer's shipping and billing info from the session.
	 *
	 * @return array
	 */
	public static function get_customer_details() {
		return WC()->session->get( 'customer' );
	}

	/**
	 * Get the user's chosen shipping method.
	 *
	 * @return array
	 */
	public static function get_chosen_shipping_methods(): array {
		return WC()->session->get( 'chosen_shipping_methods' );
	}

	/**
	 * Get the "Tax Based On" WooCommerce option.
	 *
	 * @return string base or billing
	 */
	public static function get_tax_based_on(): string {
		return get_option( 'woocommerce_tax_based_on' );
	}

	/**
	 * Set the the current customer's billing details in the session.
	 *
	 * @param $customer_details
	 */
	public static function set_customer_details( $customer_details ) {
		WC()->session->set( 'customer', array_map( 'strval', $customer_details ) );
	}

	/**
	 * Set the user's chosen shipping method.
	 *
	 * @param $chosen_shipping_methods
	 */
	public static function set_chosen_shipping_methods( $chosen_shipping_methods ) {
		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Set the "Tax Based On" WooCommerce option.
	 *
	 * @param string $default_shipping_method Shipping Method slug
	 */
	public static function set_tax_based_on( $default_shipping_method ) {
		update_option( 'woocommerce_tax_based_on', $default_shipping_method );
	}

	/**
	 * Indicate whether to save or not.
	 *
	 * @return bool
	 */
	public function save(): bool {
		return true;
	}
}

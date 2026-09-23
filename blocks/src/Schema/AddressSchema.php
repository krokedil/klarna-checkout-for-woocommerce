<?php
namespace Krokedil\KustomCheckout\Blocks\Schema;

/**
 * Schema for the Kustom Checkout address data extension for the WooCommerce Store API.
 */
class AddressSchema {
	/**
	 * Returns the schema for the Kustom Checkout address data.
	 *
	 * @return array
	 */
	public static function get_schema() {
		return array(
			'kco_address' => array(
				'description' => __( 'Kustom Checkout address data', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'object',
				'readonly'    => true,
				'properties'  => array(
					'billing_address'  => array(
						'description' => __( 'Kustom Checkout billing address data', 'klarna-checkout-for-woocommerce' ),
						'type'        => 'object',
						'readonly'    => true,
						'properties'  => array(
							'given_name'     => array(
								'description' => __( 'Kustom Checkout billing address given name', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'family_name'    => array(
								'description' => __( 'Kustom Checkout billing address family name', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'email'          => array(
								'description' => __( 'Kustom Checkout billing address email', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'phone'          => array(
								'description' => __( 'Kustom Checkout billing address phone', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'street_address' => array(
								'description' => __( 'Kustom Checkout billing address street address', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'postal_code'    => array(
								'description' => __( 'Kustom Checkout billing address postal code', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'city'           => array(
								'description' => __( 'Kustom Checkout billing address city', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'region'         => array(
								'description' => __( 'Kustom Checkout billing address region', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'country'        => array(
								'description' => __( 'Kustom Checkout billing address country', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
						),
					),
					'shipping_address' => array(
						'description' => __( 'Kustom Checkout shipping address data', 'klarna-checkout-for-woocommerce' ),
						'type'        => 'object',
						'readonly'    => true,
						'properties'  => array(
							'given_name'     => array(
								'description' => __( 'Kustom Checkout shipping address given name', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'family_name'    => array(
								'description' => __( 'Kustom Checkout shipping address family name', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'email'          => array(
								'description' => __( 'Kustom Checkout shipping address email', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'phone'          => array(
								'description' => __( 'Kustom Checkout shipping address phone', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'street_address' => array(
								'description' => __( 'Kustom Checkout shipping address street address', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'postal_code'    => array(
								'description' => __( 'Kustom Checkout shipping address postal code', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'city'           => array(
								'description' => __( 'Kustom Checkout shipping address city', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'region'         => array(
								'description' => __( 'Kustom Checkout shipping address region', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
							'country'        => array(
								'description' => __( 'Kustom Checkout shipping address country', 'klarna-checkout-for-woocommerce' ),
								'type'        => 'string',
								'readonly'    => true,
							),
						),
					),
				),
			),
		);
	}
}

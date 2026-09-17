<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Settings;

use Krokedil\KustomCheckout\InPersonPayments\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The In-Person Payments section of the gateway settings page.
 */
class Fields {

	/**
	 * Returns the fields for the In-Person Payments section.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'in_person_payments'     => array(
				'title'       => __( 'In-person payments', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'krokedil_section_start',
				'id'          => 'in_person_payments',
				'description' => __( 'Take payments at the counter on a paired Kustom POS device.', 'klarna-checkout-for-woocommerce' ),
			),
			'ipp_enabled'            => array(
				'title'       => __( 'Enable/Disable', 'klarna-checkout-for-woocommerce' ),
				'label'       => __( 'Enable in-person payments', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Adds a separate in-person payment method to the checkout for staff with the capability selected below.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'ipp_title'              => array(
				'title'       => __( 'Title', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The payment method title shown at checkout.', 'klarna-checkout-for-woocommerce' ),
				'default'     => __( 'In-Person Payment', 'klarna-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'ipp_capability'         => array(
				'title'       => __( 'Required capability', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'manage_woocommerce' => __( 'Manage WooCommerce (shop managers and administrators)', 'klarna-checkout-for-woocommerce' ),
					'edit_shop_orders'   => __( 'Edit orders', 'klarna-checkout-for-woocommerce' ),
					'manage_options'     => __( 'Manage options (administrators only)', 'klarna-checkout-for-woocommerce' ),
				),
				'description' => __( 'Only logged in users with this capability can see the in-person payment method.', 'klarna-checkout-for-woocommerce' ),
				'default'     => 'manage_woocommerce',
				'desc_tip'    => true,
			),
			'ipp_webhook_secret'     => array(
				'title'             => __( 'Webhook signing secret', 'klarna-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => self::webhook_secret_description(),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'ipp_location_id'        => array(
				'title'       => __( 'Location', 'klarna-checkout-for-woocommerce' ),
				'type'        => 'kco_ipp_location',
				'description' => __( 'The Kustom location to enroll devices at. Required unless the merchant account has exactly one location.', 'klarna-checkout-for-woocommerce' ),
				'default'     => '',
			),
			'ipp_enrollment'         => array(
				'title' => __( 'Pair a device', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'kco_ipp_enrollment',
			),
			'ipp_devices'            => array(
				'title' => __( 'Paired devices', 'klarna-checkout-for-woocommerce' ),
				'type'  => 'kco_ipp_devices',
			),
			'in_person_payments_end' => array(
				'type' => 'krokedil_section_end',
			),
		);
	}

	/**
	 * What the webhook secret field explains, with the callback URL when there is one.
	 *
	 * @return string
	 */
	private static function webhook_secret_description() {
		$url = Rest::webhook_url();

		if ( '' === $url ) {
			return __( 'The <code>whsec_</code> secret from the Kustom Merchant Portal. Leave empty to rely on browser polling only.', 'klarna-checkout-for-woocommerce' );
		}

		return sprintf(
			/* translators: %s: the callback URL to enter in the Kustom Merchant Portal. */
			__( 'The <code>whsec_</code> secret from the Kustom Merchant Portal, where the callback URL is set to <code>%s</code>. Leave empty to rely on browser polling only.', 'klarna-checkout-for-woocommerce' ),
			esc_url( $url )
		);
	}
}

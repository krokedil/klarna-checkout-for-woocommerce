<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Picks the device an in-person refund is fiscally registered on. Kustom accepts any
 * device with an open cash register, so the merchant is not asked to choose one.
 */
class RefundDevice {

	/**
	 * The device to register a refund of the order on.
	 *
	 * @param \WC_Order $order The in-person order being refunded.
	 * @return array|\WP_Error The device, or an error when no cash register is open.
	 */
	public static function for_order( $order ) {
		$devices = Devices::with_open_register();
		if ( is_wp_error( $devices ) ) {
			return $devices;
		}

		// The refund addresses the device by its Kustom id, so one without it cannot be used.
		$devices = array_values( array_filter( $devices, fn( $device ) => ! empty( $device['kco_id'] ) ) );

		/**
		 * Filters the device an in-person refund is registered on.
		 *
		 * @since x.x.x
		 *
		 * @param array|null $device  The chosen device, or null when no cash register is open.
		 * @param array      $devices The devices with an open cash register.
		 * @param \WC_Order  $order   The order being refunded.
		 */
		$device = apply_filters( 'kco_ipp_refund_device', self::pick( $devices, $order ), $devices, $order );

		if ( empty( $device['kco_id'] ) ) {
			return new \WP_Error( 'no_open_cash_register', __( 'No Kustom POS device has an open cash register. Open one in the Kustom app and try the refund again.', 'klarna-checkout-for-woocommerce' ) );
		}

		return $device;
	}

	/**
	 * Prefer the device the sale was made on, then one at the same location, then any.
	 *
	 * @param array     $devices The devices with an open cash register.
	 * @param \WC_Order $order The order being refunded.
	 * @return array|null
	 */
	private static function pick( $devices, $order ) {
		$preferences = array(
			'id'          => $order->get_meta( Gateway::DEVICE_META ),
			'location_id' => $order->get_meta( Gateway::LOCATION_META ),
		);

		foreach ( $preferences as $key => $wanted ) {
			foreach ( $devices as $device ) {
				if ( '' !== (string) $wanted && ( $device[ $key ] ?? '' ) === $wanted ) {
					return $device;
				}
			}
		}

		return $devices[0] ?? null;
	}
}

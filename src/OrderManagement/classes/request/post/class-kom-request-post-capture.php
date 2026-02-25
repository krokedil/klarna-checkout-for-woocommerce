<?php
/**
 * POST request class for order capture
 *
 * @package WC_Klarna_Order_Management/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST request class for order capture
 */
class KOM_Request_Post_Capture extends KOM_Request_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Capture Klarna order';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		// If force full capture is enabled, set to true.
		$settings                 = WC_Klarna_Order_Management::get_instance()->settings->get_settings( $this->order_id );
		$force_capture_full_order = ( isset( $settings['kom_force_full_capture'] ) && 'yes' === $settings['kom_force_full_capture'] ) ? true : false;
		$order                    = wc_get_order( $this->order_id );

		// If force capture is enabled, send the full remaining authorized amount.
		$data = array(
			'captured_amount' => ( $force_capture_full_order ) ? $this->klarna_order->remaining_authorized_amount : round( $order->get_total() * 100, 0 ),
		);

		$kss_shipment_data = $this->get_kss_shipment_data();
		if ( isset( $kss_shipment_data ) && ! empty( $kss_shipment_data ) ) {
			$kss_shipment_arr['shipping_info'] = array( $kss_shipment_data );
			$data                              = array_merge( $kss_shipment_arr, $data );
		}

		// Don't add order lines if we are forcing a full order capture.
		if ( ! $force_capture_full_order ) {

			$lines_processor = new WC_Klarna_Order_Management_Order_Lines( $this->order_id, 'capture' );
			$order_lines     = $lines_processor->order_lines();

			if ( isset( $order_lines ) && ! empty( $order_lines ) ) {
				$data = array_merge( $order_lines, $data );
			}
		}

		return apply_filters( 'kom_order_capture_args', $data, $this->order_id );

	}

	/**
	 * Returns KSS shipment information.
	 *
	 * @return array
	 */
	protected function get_kss_shipment_data() {
		$kss_shipment_data = array();
		$order             = wc_get_order( $this->order_id );

		$kco_kss_data     = json_decode( $order->get_meta( '_kco_kss_data', true ), true );
		$kss_tracking_id  = $order->get_meta( '_kss_tracking_id', true );
		$kss_tracking_url = $order->get_meta( '_kss_tracking_url', true );
		isset( $kco_kss_data['delivery_details']['carrier'] ) ? $kss_shipment_data['shipping_company'] = $kco_kss_data['delivery_details']['carrier'] : '';
		isset( $kco_kss_data['shipping_method'] ) ? $kss_shipment_data['shipping_method']              = $kco_kss_data['shipping_method'] : '';
		isset( $kss_tracking_id ) ? $kss_shipment_data['tracking_number']                              = $kss_tracking_id : '';
		isset( $kss_tracking_url ) ? $kss_shipment_data['tracking_uri']                                = $kss_tracking_url : '';

		return $kss_shipment_data;
	}
}

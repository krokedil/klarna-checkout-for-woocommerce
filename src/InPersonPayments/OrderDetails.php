<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The order screen box that shows where and by whom an in-person sale was taken.
 */
class OrderDetails {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
	}

	/**
	 * Add the box to in-person orders, on both the HPOS and the legacy order screen.
	 *
	 * @param string             $screen_id The screen id.
	 * @param \WP_Post|\WC_Order $post_or_order The post or order being edited.
	 * @return void
	 */
	public function add_meta_box( $screen_id, $post_or_order ) {
		if ( wc_get_page_screen_id( 'shop-order' ) !== $screen_id ) {
			return;
		}

		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order );
		if ( ! $order || Gateway::ID !== $order->get_payment_method() ) {
			return;
		}

		add_meta_box( 'kco-ipp-order-details', __( 'In-person payment', 'klarna-checkout-for-woocommerce' ), array( $this, 'render' ), $screen_id, 'side', 'default' );
	}

	/**
	 * Print the box.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order The post or order being edited.
	 * @return void
	 */
	public function render( $post_or_order ) {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$staff    = get_userdata( (int) $order->get_meta( Gateway::STAFF_META ) );
		$location = $order->get_meta( Gateway::LOCATION_META );
		$channels = Gateway::payment_channels();

		$rows = array(
			__( 'Staff', 'klarna-checkout-for-woocommerce' )          => $staff ? $staff->display_name : '',
			__( 'Device', 'klarna-checkout-for-woocommerce' )         => $order->get_meta( Gateway::DEVICE_NAME_META ) ? $order->get_meta( Gateway::DEVICE_NAME_META ) : $order->get_meta( Gateway::DEVICE_META ),
			__( 'Location', 'klarna-checkout-for-woocommerce' )       => $location ? Locations::name( $location ) : '',
			__( 'Cash register', 'klarna-checkout-for-woocommerce' )  => $order->get_meta( Gateway::REGISTER_META ),
			__( 'Payment method', 'klarna-checkout-for-woocommerce' ) => $channels[ $order->get_meta( Gateway::CHANNEL_META ) ] ?? $order->get_meta( Gateway::CHANNEL_META ),
			__( 'Session status', 'klarna-checkout-for-woocommerce' ) => $order->get_meta( Gateway::STATUS_META ),
			__( 'Session ID', 'klarna-checkout-for-woocommerce' )     => $order->get_meta( Gateway::SESSION_META ),
		);

		/**
		 * Filters the rows of the in-person payment box on the order screen.
		 *
		 * @since 2.22.0
		 *
		 * @param array<string, string> $rows  The values by label. Empty values are left out.
		 * @param \WC_Order             $order The order.
		 */
		$rows = apply_filters( 'kco_ipp_order_details_rows', $rows, $order );

		echo '<dl class="kco-ipp-order-details">';
		foreach ( array_filter(
			$rows,
			function ( $value ) {
				return '' !== (string) $value;
			}
		) as $label => $value ) {
			printf( '<dt><strong>%1$s</strong></dt><dd style="margin:0 0 8px;word-break:break-all">%2$s</dd>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</dl>';
	}
}

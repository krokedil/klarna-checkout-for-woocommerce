<?php
namespace Krokedil\KlarnaOrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax class.
 */
class Ajax {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'kom_wc_set_order_sync' => false,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
			// WC AJAX can be used for frontend ajax requests.
			add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	/**
	 * Set order sync status.
	 *
	 * @return void
	 */
	public static function kom_wc_set_order_sync() {
		$nonce     = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id  = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
		$om_status = filter_input( INPUT_POST, 'om_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! wp_verify_nonce( $nonce, 'kom_wc_set_order_sync' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		if ( ! $order_id ) {
			wp_send_json_error( 'no_order_id' );
			exit;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'no_order' );
			exit;
		}

		// Toggle the _kom_disconnect meta based on the received status.
		if ( 'disabled' === $om_status ) {
			$order->delete_meta_data( '_kom_disconnect' );
		} elseif ( 'enabled' === $om_status ) {
			$order->update_meta_data( '_kom_disconnect', 1 );
		}

		$order->save();

		wp_send_json_success();
	}
}
Ajax::init();

<?php
namespace Krokedil\KustomCheckout\Upsell;

/**
 * Fallback handler invoked via Action Scheduler when the Kustom push callback
 * has not arrived within the configured delay.
 */
class UpsellFallback {
	const HOOK  = 'kco_upsell_push_fallback';
	const GROUP = 'kco_upsell';

	/**
	 * UpsellFallback constructor.
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_kco_settings', array() );

		if ( ! wc_string_to_bool( $settings['enable_upsell'] ?? 'no' ) ) {
			return;
		}

		add_action( self::HOOK, array( $this, 'handle_fallback' ), 10, 1 );
	}

	/**
	 * Schedule a single fallback action for a Kustom order ID.
	 *
	 * @param string $klarna_order_id The Kustom order ID.
	 * @return void
	 */
	public static function schedule( $klarna_order_id ) {
		if ( empty( $klarna_order_id ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$delay = (int) apply_filters( 'kco_upsell_push_fallback_delay', 30 * MINUTE_IN_SECONDS, $klarna_order_id );
		if ( $delay <= 0 ) {
			return;
		}

		as_schedule_single_action( time() + $delay, self::HOOK, array( $klarna_order_id ), self::GROUP );
		\KCO_Logger::log( $klarna_order_id . ': Scheduled upsell push fallback in ' . $delay . ' seconds.' );
	}

	/**
	 * Cancel any pending fallback action for a Kustom order ID.
	 *
	 * @param string $klarna_order_id The Kustom order ID.
	 * @return void
	 */
	public static function cancel( $klarna_order_id ) {
		if ( empty( $klarna_order_id ) || ! function_exists( 'as_unschedule_action' ) ) {
			return;
		}

		as_unschedule_action( self::HOOK, array( $klarna_order_id ), self::GROUP );
	}

	/**
	 * Handle the fallback: if the order is still awaiting upsell, run the same
	 * processing the push callback would have run.
	 *
	 * @param string $klarna_order_id The Kustom order ID.
	 * @return void
	 */
	public function handle_fallback( $klarna_order_id ) {
		if ( empty( $klarna_order_id ) ) {
			return;
		}

		\KCO_Logger::log( $klarna_order_id . ': Upsell push fallback triggered.' );

		$order = kco_get_order_by_klarna_id( $klarna_order_id );
		if ( empty( $order ) ) {
			\KCO_Logger::log( $klarna_order_id . ': Upsell push fallback could not find a matching WC order.' );
			return;
		}

		if ( UpsellStatus::get_configured_status() !== $order->get_status() ) {
			\KCO_Logger::log( $klarna_order_id . ': Upsell push fallback skipped; order #' . $order->get_order_number() . ' is no longer in the waiting status.' );
			return;
		}

		\KCO_API_Callbacks::get_instance()->process_push_for_order( $klarna_order_id );
	}
}

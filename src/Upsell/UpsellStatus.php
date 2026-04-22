<?php
namespace Krokedil\KustomCheckout\Upsell;

/**
 * Registers and exposes the custom "Kustom Awaiting Upsell" order status.
 */
class UpsellStatus {
	const STATUS_SLUG          = 'kco-upsell-wait';
	const STATUS_SLUG_PREFIXED = 'wc-kco-upsell-wait';
	const SETTING_KEY          = 'upsell_waiting_status';

	/**
	 * UpsellStatus constructor.
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_kco_settings', array() );

		if ( ! wc_string_to_bool( $settings['enable_upsell'] ?? 'no' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_post_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_order_status' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'allow_payment_from_custom_status' ), 10, 2 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'allow_payment_from_custom_status' ), 10, 2 );
	}

	/**
	 * Register the custom order post status.
	 *
	 * @return void
	 */
	public function register_post_status() {
		register_post_status(
			self::STATUS_SLUG_PREFIXED,
			array(
				'label'                     => _x( 'Kustom Awaiting Upsell', 'Order status', 'klarna-checkout-for-woocommerce' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: Number of orders. */
				'label_count'               => _n_noop( 'Kustom Awaiting Upsell <span class="count">(%s)</span>', 'Kustom Awaiting Upsell <span class="count">(%s)</span>', 'klarna-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Add the custom status to the WooCommerce order statuses list.
	 *
	 * @param array $order_statuses The existing order statuses.
	 * @return array
	 */
	public function add_order_status( $order_statuses ) {
		$order_statuses[ self::STATUS_SLUG_PREFIXED ] = _x( 'Kustom Awaiting Upsell', 'Order status', 'klarna-checkout-for-woocommerce' );
		return $order_statuses;
	}

	/**
	 * Allow orders in the custom waiting status to transition via payment_complete().
	 *
	 * @param array     $statuses The statuses that are valid for payment.
	 * @param \WC_Order $order The order being processed.
	 * @return array
	 */
	public function allow_payment_from_custom_status( $statuses, $order ) {
		if ( ! in_array( self::STATUS_SLUG, $statuses, true ) ) {
			$statuses[] = self::STATUS_SLUG;
		}
		return $statuses;
	}

	/**
	 * Get the order status merchants have configured for the upsell waiting state.
	 *
	 * Returns the slug without the `wc-` prefix, matching the format expected by
	 * {@see \WC_Order::set_status()}.
	 *
	 * @return string
	 */
	public static function get_configured_status() {
		$settings  = get_option( 'woocommerce_kco_settings', array() );
		$configured = $settings[ self::SETTING_KEY ] ?? self::STATUS_SLUG;

		return ! empty( $configured ) ? $configured : self::STATUS_SLUG;
	}
}

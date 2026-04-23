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
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'allow_payment_from_custom_status' ), 10 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'allow_payment_from_custom_status' ), 10 );
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
	 * @param array $statuses The statuses that are valid for payment.
	 * @return array
	 */
	public function allow_payment_from_custom_status( $statuses ) {
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
		$settings   = get_option( 'woocommerce_kco_settings', array() );
		$configured = $settings[ self::SETTING_KEY ] ?? self::STATUS_SLUG;

		return ! empty( $configured ) ? $configured : self::STATUS_SLUG;
	}

	/**
	 * Build the list of selectable waiting statuses for the settings UI.
	 *
	 * Starts from all registered WooCommerce order statuses (so custom statuses
	 * from other plugins are available), removes statuses that would break the
	 * upsell waiting flow, and ensures the custom Kustom status is always first.
	 *
	 * @return array<string, string> Map of unprefixed status slug => label.
	 */
	public static function get_waiting_status_options() {
		// Statuses that cannot be used as a "waiting for upsell push" state — either
		// terminal, or collide with the post-push status and would cause the
		// fallback to misfire.
		$disallowed = apply_filters(
			'kco_upsell_waiting_status_disallowed',
			array( 'processing', 'completed', 'cancelled', 'refunded', 'failed', 'checkout-draft' )
		);

		$options = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $slug => $label ) {
				$unprefixed = 0 === strpos( $slug, 'wc-' ) ? substr( $slug, 3 ) : $slug;
				if ( \in_array( $unprefixed, $disallowed, true ) ) {
					continue;
				}
				$options[ $unprefixed ] = $label;
			}
		}

		// Guarantee the default Kustom status is present and first in the list.
		$default_label = _x( 'Kustom Awaiting Upsell', 'Order status', 'klarna-checkout-for-woocommerce' );
		unset( $options[ self::STATUS_SLUG ] );
		$options = array( self::STATUS_SLUG => $default_label ) + $options;

		return $options;
	}
}

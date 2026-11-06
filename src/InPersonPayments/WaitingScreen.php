<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the salesperson watches while the customer pays on the device. It only presents;
 * the session handler takes every decision about the order.
 */
class WaitingScreen {

	/**
	 * How often the screen asks, in milliseconds.
	 *
	 * @var int
	 */
	public const INTERVAL = 2000;

	/**
	 * How long it keeps asking that often, in milliseconds.
	 *
	 * @var int
	 */
	public const BACKOFF_AFTER = 60000;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_thankyou_' . Gateway::ID, array( $this, 'render' ), 5 );
	}

	/**
	 * Render the waiting screen on the order received page.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function render( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->is_paid() || empty( $order->get_meta( Gateway::SESSION_META ) ) ) {
			return;
		}

		$this->enqueue_scripts( $order );

		?>
		<div class="kco-ipp-waiting" id="kco-ipp-waiting">
			<p class="kco-ipp-waiting-status">
				<?php esc_html_e( 'Waiting for the customer to pay on the device…', 'klarna-checkout-for-woocommerce' ); ?>
			</p>
			<p class="kco-ipp-waiting-error" style="display:none"></p>
			<p>
				<button type="button" class="button kco-ipp-cancel-session">
					<?php esc_html_e( 'Cancel on device', 'klarna-checkout-for-woocommerce' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue the poll.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return void
	 */
	private function enqueue_scripts( $order ) {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'kco_ipp_checkout',
			plugins_url( 'src/InPersonPayments/assets/css/kustom-ipp-checkout' . $suffix . '.css', KCO_WC_MAIN_FILE ),
			array(),
			KCO_WC_VERSION
		);

		wp_enqueue_script(
			'kco_ipp_checkout',
			plugins_url( 'src/InPersonPayments/assets/js/kustom-ipp-checkout' . $suffix . '.js', KCO_WC_MAIN_FILE ),
			array( 'jquery' ),
			KCO_WC_VERSION,
			true
		);

		wp_localize_script(
			'kco_ipp_checkout',
			'kco_ipp_checkout_params',
			array(
				'status_url'    => rest_url( Rest::REST_NAMESPACE . '/ipp/sessions/' . $order->get_id() ),
				'cancel_url'    => rest_url( Rest::REST_NAMESPACE . '/ipp/sessions/' . $order->get_id() . '/cancel' ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'order_key'     => $order->get_order_key(),
				'interval'      => self::INTERVAL,
				'backoff_after' => self::BACKOFF_AFTER,
				'error_message' => __( 'Could not reach Kustom. Still waiting for the device.', 'klarna-checkout-for-woocommerce' ),
			)
		);
	}
}

<?php
/**
 * Adds the possiblity to add Kustom data to the end of order confirmation emails.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KCO_Email' ) ) {
	/**
	 * The class for email handling for KCO.
	 */
	class KCO_Email {
		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'woocommerce_email_after_order_table', array( $this, 'add_klarna_data_to_mail' ), 10, 2 );
		}

		/**
		 * Adds Kustom data to the order email.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @param bool     $sent_to_admin If the email is being sent to the admin, and not the customer.
		 *
		 * @return void
		 */
		public function add_klarna_data_to_mail( $order, $sent_to_admin ) {
			$gateway_used = $order->get_payment_method();
			$settings     = get_option( 'woocommerce_kco_settings' );
			$add_to_email = isset( $settings['add_to_email'] ) && 'yes' === $settings['add_to_email'] ? true : false;
			if ( 'kco' === $gateway_used && $add_to_email ) {
				$klarna_cs_url = '<a href="https://help.kustom.co/en/">' . esc_html__( 'Kustom', 'klarna-checkout-for-woocommerce' ) . '</a>';
				?>
				<p><?php echo esc_html__( 'Kustom order id:', 'klarna-checkout-for-woocommerce' ) . ' ' . esc_html( $order->get_transaction_id() ); ?></p>
				<?php if ( ! $sent_to_admin ) { ?>
					<p>
						<?php
						echo wp_kses(
							sprintf(
								// translators: Kustom customer service URL.
								__(
									'Your payment is processed by our partner %1$s. You will shortly receive instructions on how to complete your payment. You can manage all your payments via kustom.co.',
									'klarna-checkout-for-woocommerce'
								),
								$klarna_cs_url
							),
							array( 'a' => array( 'href' => array() ) )
						);
						?>
					</p>
					<?php
				}
			}
		}
	}
	new KCO_Email();
}

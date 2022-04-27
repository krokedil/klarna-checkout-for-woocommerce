<?php
/**
 * Adds the possiblity to add Klarna data to the end of order confirmation emails.
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
			add_action( 'woocommerce_email_after_order_table', array( $this, 'add_klarna_data_to_mail' ), 10, 4 );
		}

		/**
		 * Adds Klarna data to the order email.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @param bool     $sent_to_admin If the email is being sent to the admin, and not the customer.
		 * @param bool     $plain_text If the email is as plain text or HTML.
		 * @param WC_Email $email The current WooCommerce email object.
		 *
		 * @return void
		 */
		public function add_klarna_data_to_mail( $order, $sent_to_admin, $plain_text, $email ) {
			$gateway_used = $order->get_payment_method();
			$settings     = get_option( 'woocommerce_kco_settings' );
			$add_to_email = isset( $settings['add_to_email'] ) && 'yes' === $settings['add_to_email'] ? true : false;
			if ( 'kco' === $gateway_used && $add_to_email ) {
				$klarna_cs_url  = '<a href="https://www.klarna.com/customer-service">' . esc_html__( 'Klarna', 'klarna-checkout-for-woocommerce' ) . '</a>';
				$klarna_app_url = '<a href="https://app.klarna.com/">' . esc_html__( 'Klarna App', 'klarna-checkout-for-woocommerce' ) . '</a>';
				?>
				<p><?php echo esc_html__( 'Klarna order id:', 'klarna-checkout-for-woocommerce' ) . ' ' . esc_html( $order->get_transaction_id() ); ?></p>
				<?php if ( ! $sent_to_admin ) { ?>
					<p>
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1. Klarna customer service URL. 2. Klarnas app url.
								__(
									'Your payment is processed by our partner %1$s. You will shortly receive instructions on how to complete your payment. You can manage all your payments via Klarna.com or in the %2$s',
									'klarna-checkout-for-woocommerce'
								),
								$klarna_cs_url,
								$klarna_app_url
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

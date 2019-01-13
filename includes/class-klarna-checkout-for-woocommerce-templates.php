<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klarna_Checkout_For_WooCommerce_Templates class.
 */
class Klarna_Checkout_For_WooCommerce_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Override template if Klarna Checkout page.
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );

		// Template hooks.
		add_action( 'kco_wc_before_checkout_form', 'kco_wc_print_notices' );
		add_action( 'kco_wc_before_checkout_form', 'kco_wc_calculate_totals', 1 );
		add_action( 'kco_wc_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'kco_wc_before_checkout_form', 'woocommerce_checkout_coupon_form', 20 );
		add_action( 'kco_wc_after_order_review', 'kco_wc_show_extra_fields', 10 );
		add_action( 'kco_wc_after_order_review', 'kco_wc_show_another_gateway_button', 20 );
		add_action( 'kco_wc_before_snippet', 'kco_wc_prefill_consent', 10 );
		add_action( 'kco_wc_after_snippet', 'kco_wc_show_payment_method_field', 10 );
	}

	/**
	 * Override checkout form template if Klarna Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name, $template_path ) {
		if ( is_checkout() ) {
			// Fallback Klarna Order Received, used when WooCommerce checkout form submission fails.
			if ( 'checkout/thankyou.php' === $template_name ) {
				if ( isset( $_GET['kco_checkout_error'] ) && 'true' === $_GET['kco_checkout_error'] ) {
					$template = KCO_WC_PLUGIN_PATH . '/templates/klarna-checkout-order-received.php';
				}
			}

			// Don't display KCO template if we have a cart that doesn't needs payment
			if ( ! WC()->cart->needs_payment() ) {
				return $template;
			}

			// Klarna Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/klarna-checkout.php' ) ) {
					$klarna_checkout_template = locate_template( 'woocommerce/klarna-checkout.php' );
				} else {
					$klarna_checkout_template = KCO_WC_PLUGIN_PATH . '/templates/klarna-checkout.php';
				}

				// Klarna checkout page.
				if ( array_key_exists( 'kco', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! isset( $_GET['confirm'] ) ) {
							$template = $klarna_checkout_template;
						}
					}

					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'kco' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['confirm'] ) ) {
								$template = $klarna_checkout_template;
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'kco' === key( $available_gateways ) ) {
								if ( ! isset( $_GET['confirm'] ) ) {
									$template = $klarna_checkout_template;
								}
							}
						}
					}
				}
			}
		}

		return $template;
	}

}

Klarna_Checkout_For_WooCommerce_Templates::get_instance();

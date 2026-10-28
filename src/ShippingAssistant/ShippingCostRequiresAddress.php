<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping cost requires address class.
 *
 * Disables the WooCommerce "Hide shipping costs until an address is entered" setting while Kustom drives shipping on the classic checkout.
 *
 * The filter is registered by ShippingAssistant, which also exposes this instance so it can be unhooked.
 */
class ShippingCostRequiresAddress {
	/**
	 * Whether the setting has already been disabled for this request.
	 *
	 * @var bool
	 */
	private $disabled = false;

	/**
	 * Report the setting as disabled once Kustom has returned a shipping option.
	 *
	 * @param mixed $value The stored option value.
	 *
	 * @return mixed The option value, or 'no' to allow shipping to be calculated.
	 */
	public function maybe_disable( $value ) {
		if ( 'yes' !== $value ) {
			return $value;
		}

		// Stay disabled for the rest of the request, so the render pass agrees with the calculation pass.
		if ( $this->disabled ) {
			return 'no';
		}

		if ( ! $this->is_kustom_driven_checkout() ) {
			return $value;
		}

		$this->disabled = true;
		return 'no';
	}

	/**
	 * Whether Kustom has returned a shipping option for a classic checkout request.
	 *
	 * @return bool
	 */
	private function is_kustom_driven_checkout() {
		// The Store API uses its own cart, so the block cart and checkout are left untouched.
		if ( ! WC()->cart || ! $this->is_shortcode_cart() ) {
			return false;
		}

		if ( ! WC()->session ) {
			return false;
		}

		// Kustom Checkout must be the chosen gateway.
		if ( 'kco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return false;
		}

		// Kustom must have taken over shipping.
		if ( ! WC()->session->get( 'kco_kss_enabled' ) ) {
			return false;
		}

		// And it must have given us a shipping option we can turn into a rate.
		$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
		if ( empty( $klarna_order_id ) || empty( get_transient( "kss_data_$klarna_order_id" ) ) ) {
			return false;
		}

		return $this->is_checkout_request();
	}

	/**
	 * Whether the cart belongs to the shortcode context rather than the Store API.
	 *
	 * @return bool
	 */
	private function is_shortcode_cart() {
		// WC_Cart::$cart_context was added in WooCommerce 9.9. Older versions have no equivalent, so the Store API is excluded by its request type instead.
		if ( ! property_exists( WC()->cart, 'cart_context' ) ) {
			return ! WC()->is_rest_api_request();
		}

		return 'shortcode' === WC()->cart->cart_context;
	}

	/**
	 * Whether we are rendering or processing the classic checkout rather than the cart.
	 *
	 * @return bool
	 */
	private function is_checkout_request() {
		// Defined by WC_AJAX and WC_Checkout, but not when rendering the checkout shortcode.
		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			return true;
		}

		// is_checkout() resolves the checkout page ID, which must not happen before 'wp_loaded'.
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}

		return is_checkout() && ! is_cart() && ! is_wc_endpoint_url( 'order-pay' );
	}
}

<?php
namespace Krokedil\KustomCheckout\ShippingAssistant;

use Krokedil\KustomCheckout\ShippingAssistant\API\Controllers\ShippingOptionUpdateController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merchant URLs class.
 *
 * Injects the shipping option update callback URL into Kustom's merchant URLs, and tags requests that
 * need the callback even when the setting is off (free-trial subscriptions, where shipping must start
 * at zero).
 */
class MerchantUrls {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'kco_wc_merchant_urls', array( $this, 'maybe_add_shipping_option_change_callback_url' ) );
		add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_add_subscription_free_trial_tag' ) );
	}

	/**
	 * Maybe add the shipping option change callback URL to the merchant URLs array.
	 *
	 * @param array $merchant_urls The merchant URLs array.
	 *
	 * @return array The modified merchant URLs array.
	 */
	public function maybe_add_shipping_option_change_callback_url( $merchant_urls ) {
		// Only if we have an actual cart that is not empty.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return $merchant_urls;
		}

		// If the cart does not need shipping, we don't need to add the shipping option update callback URL.
		if ( ! WC()->cart->needs_shipping() ) {
			return $merchant_urls;
		}

		// If the cart only has free trial subscriptions, we will always need to add the callback handler.
		$is_free_trial_subscription = $this->cart_contains_only_free_trial_subscription();
		$setting_enabled            = Settings::is_shipping_option_update_callback_enabled();

		// Only if the cart has a subscription and it's a free trial subscription. Otherwise only if the setting is enabled.
		if ( ! $is_free_trial_subscription && ! $setting_enabled ) {
			return $merchant_urls;
		}

		$api_registry = KCO_WC()->shipping_assistant->api_registry();
		if ( ! $api_registry ) {
			return $merchant_urls;
		}

		$merchant_urls['shipping_option_update'] = add_query_arg(
			array( 'kco_id' => '{checkout.order.id}' ),
			$api_registry->get_request_path( ShippingOptionUpdateController::class, 'shipping-option-update' )
		);

		return $merchant_urls;
	}

	/**
	 * Maybe add the subscription free trial tag to the Kustom Checkout request args.
	 *
	 * This is needed to ensure that the shipping option update callback URL is added for free trial subscriptions, since Kustom only adds the shipping option update callback URL if the "ksa_free_shipping" tag is present in the order.
	 *
	 * @param array $request_args The request args for Kustom Checkout.
	 *
	 * @return array The modified request args for Kustom Checkout.
	 */
	public function maybe_add_subscription_free_trial_tag( $request_args ) {
		$tags = isset( $request_args['tags'] ) ? $request_args['tags'] : array();

		if ( $this->cart_contains_only_free_trial_subscription() ) {
			$tags[] = 'ksa_subscription_free_trial_shipping';
		}

		$request_args['tags'] = $tags;
		return $request_args;
	}

	/**
	 * Helper method to see if the current cart contains only a free trial subscription and the shipping should be zero for the initial purchase.
	 *
	 * @return bool True if the cart contains only a free trial subscription, false otherwise.
	 */
	private function cart_contains_only_free_trial_subscription() {
		// If there is no cart or the cart is empty, return false.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		// Check if the cart contains only free trial subscriptions.
		return class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::all_cart_items_have_free_trial();
	}
}

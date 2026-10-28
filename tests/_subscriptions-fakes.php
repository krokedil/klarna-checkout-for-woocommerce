<?php
/*
 * WooCommerce Subscriptions stand-ins.
 *
 * Required by the Integration and Harness suite bootstraps, not by the shared
 * tests/_bootstrap.php, which EndToEnd also loads and which should run against
 * a store that has no subscriptions surface at all.
 *
 * The plugin guards most of these with `function_exists()`, so declaring them makes
 * the plugin believe WooCommerce Subscriptions is installed for the whole run.
 * PHP cannot undefine a function, so the per-test switch cannot live in
 * `function_exists`, it lives in SubscriptionsRegistry, which answers empty
 * or false until a test registers something through `CanFakeSubscriptions`.
 * An unprepared test therefore sees the same store it saw before these
 * existed.
 *
 * Only the functions the plugin calls are declared, with the signatures it calls them
 * with. Note the two shapes: `wcs_get_subscriptions_for_order()` takes an
 * `$args` array with an `order_type` key, while
 * `wcs_order_contains_subscription()` takes the order type list directly.
 */

use Tests\Support\Fakes\SubscriptionOrder;
use Tests\Support\Fakes\SubscriptionsRegistry;

/*
 * KCO_Subscription guards its token handling on `class_exists( 'WC_Subscription' )`,
 * so the class has to exist for a subscription test to reach any of it. Deliberately
 * *not* WC_Subscriptions_Cart: KCO_Subscription::cart_has_subscription() would then
 * call into it, and the cart-state fakes are the switch a test is supposed to use.
 */
if ( ! class_exists( 'WC_Subscription' ) ) {
	class WC_Subscription extends SubscriptionOrder {}
}

if ( ! function_exists( 'wcs_is_subscription' ) ) {
	function wcs_is_subscription( $order ) {
		return SubscriptionsRegistry::isSubscription( $order );
	}
}

if ( ! function_exists( 'wcs_get_subscription' ) ) {
	function wcs_get_subscription( $subscription ) {
		return SubscriptionsRegistry::get( $subscription );
	}
}

if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
	function wcs_get_subscriptions_for_order( $order, $args = [] ) {
		return SubscriptionsRegistry::forOrder( $order, (array) $args );
	}
}

if ( ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
	function wcs_get_subscriptions_for_renewal_order( $order ) {
		return SubscriptionsRegistry::forRenewalOrder( $order );
	}
}

if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
	function wcs_order_contains_subscription( $order, $order_type = [ 'parent', 'resubscribe', 'switch' ] ) {
		return SubscriptionsRegistry::orderContains( $order, $order_type );
	}
}

if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
	function wcs_cart_contains_renewal() {
		return SubscriptionsRegistry::cartContains( 'renewal' );
	}
}

if ( ! function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) ) {
	function wcs_cart_contains_failed_renewal_order_payment() {
		return SubscriptionsRegistry::cartContains( 'failed_renewal_order_payment' );
	}
}

if ( ! function_exists( 'wcs_cart_contains_resubscribe' ) ) {
	function wcs_cart_contains_resubscribe() {
		return SubscriptionsRegistry::cartContains( 'resubscribe' );
	}
}

if ( ! function_exists( 'wcs_cart_contains_early_renewal' ) ) {
	function wcs_cart_contains_early_renewal() {
		return SubscriptionsRegistry::cartContains( 'early_renewal' );
	}
}

if ( ! function_exists( 'wcs_cart_contains_switches' ) ) {
	function wcs_cart_contains_switches() {
		return SubscriptionsRegistry::cartContains( 'switches' );
	}
}

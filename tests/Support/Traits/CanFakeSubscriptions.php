<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Tests\Support\Fakes\SubscriptionOrder;
use Tests\Support\Fakes\SubscriptionsRegistry;

/** WooCommerce Subscriptions fixtures for the Integration suite. */
trait CanFakeSubscriptions {

	/** Creates a subscription with no parent order. */
	protected function haveSubscription( array $args = [] ): \WC_Order {
		return $this->markAsSubscription( $this->haveOrder( $args ) );
	}

	/** Creates a subscription bought on the given parent order. */
	protected function haveSubscriptionFor( \WC_Order $parent, array $args = [] ): \WC_Order {
		$subscription = $this->haveOrder( $args );
		$subscription->set_parent_id( $parent->get_id() );
		$subscription->save();

		return $this->markAsSubscription( $subscription, $parent );
	}

	/** Creates a renewal order for a subscription. */
	protected function haveRenewalOrderFor( \WC_Order $subscription, array $args = [] ): \WC_Order {
		$renewal = $this->haveOrder( $args );

		SubscriptionsRegistry::addRenewalOrder( $subscription->get_id(), $renewal->get_id() );

		return $renewal;
	}

	/** Registers an existing order as a subscription. */
	protected function markAsSubscription( \WC_Order $order, ?\WC_Order $parent = null ): \WC_Order {
		SubscriptionsRegistry::register( $order->get_id(), $parent ? $parent->get_id() : null );

		if ( ! has_filter( 'woocommerce_order_class', [ $this, 'subscriptionOrderClass' ] ) ) {
			add_filter( 'woocommerce_order_class', [ $this, 'subscriptionOrderClass' ], 10, 3 );
		}

		return wc_get_order( $order->get_id() );
	}

	/** Makes the named `wcs_cart_contains_*()` checks answer true. */
	protected function haveCartContaining( string ...$kinds ): void {
		foreach ( $kinds as $kind ) {
			if ( ! in_array( $kind, SubscriptionsRegistry::CART_FLAGS, true ) ) {
				$this->fail(
					sprintf(
						'No wcs_cart_contains_%s() exists to fake. Known kinds: %s',
						$kind,
						implode( ', ', SubscriptionsRegistry::CART_FLAGS )
					)
				);
			}

			SubscriptionsRegistry::setCartFlag( $kind );
		}
	}

	/** The `woocommerce_order_class` callback. Public so WordPress can call it. */
	public function subscriptionOrderClass( $classname, $order_type, $order_id ) {
		return SubscriptionsRegistry::isSubscription( $order_id ) ? SubscriptionOrder::class : $classname;
	}

	/**
	 * Forgets every faked subscription. Static state, so this has to run
	 * between tests whether or not the test used the fixtures.
	 */
	protected function resetFakeSubscriptions(): void {
		SubscriptionsRegistry::reset();
	}
}

<?php

declare(strict_types=1);

namespace Tests\Harness\Fixtures;

use Tests\Support\IntegrationTestCase;

/**
 * Pins the WooCommerce Subscriptions fakes: the `wcs_*` stubs are declared for
 * the whole run, and a fake that answers *more* than it should turns a
 * subscription test green for the wrong reason. Calls the globals the plugin
 * calls, not the registry, because the wiring between the two is part of what
 * can break.
 *
 * @covers \Tests\Support\Fakes\SubscriptionsRegistry
 * @covers \Tests\Support\Traits\CanFakeSubscriptions
 */
class FakeSubscriptionsTest extends IntegrationTestCase {

	/** The order types KCO_Subscription::cart_has_subscription() and friends ask about. */
	private const ORDER_TYPES = [ 'parent', 'resubscribe', 'switch', 'renewal' ];

	public function test_a_test_that_registers_nothing_sees_a_store_without_subscriptions(): void {
		$order = $this->haveOrder();

		$this->assertFalse( wcs_is_subscription( $order->get_id() ) );
		$this->assertFalse( wcs_get_subscription( $order->get_id() ) );
		$this->assertSame( [], wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] ) );
		$this->assertSame( [], wcs_get_subscriptions_for_renewal_order( $order->get_id() ) );
		$this->assertFalse( wcs_order_contains_subscription( $order, self::ORDER_TYPES ) );
		$this->assertFalse( wcs_cart_contains_renewal() );
		$this->assertFalse( wcs_cart_contains_failed_renewal_order_payment() );
		$this->assertFalse( wcs_cart_contains_resubscribe() );
		$this->assertFalse( wcs_cart_contains_early_renewal() );
		$this->assertFalse( wcs_cart_contains_switches() );
	}

	public function test_a_renewal_order_finds_its_subscription_only_when_the_order_type_allows_it(): void {
		$subscription = $this->haveSubscriptionFor( $this->haveOrder() );
		$renewal      = $this->haveRenewalOrderFor( $subscription );

		$this->assertSame(
			[],
			wcs_get_subscriptions_for_order( $renewal ),
			'The default order type is parent, and the renewal order is not the parent.'
		);
		$this->assertSame(
			[ $subscription->get_id() ],
			array_keys( wcs_get_subscriptions_for_order( $renewal, [ 'order_type' => 'any' ] ) ),
			'The plugin asks for "any" when saving a recurring token, and expects the renewal to resolve.'
		);
		$this->assertSame(
			[ $subscription->get_id() ],
			array_keys( wcs_get_subscriptions_for_renewal_order( $renewal->get_id() ) )
		);
	}

	public function test_only_the_named_cart_states_answer_true(): void {
		$this->haveCartContaining( 'renewal', 'switches' );

		$this->assertTrue( wcs_cart_contains_renewal() );
		$this->assertTrue( wcs_cart_contains_switches() );
		$this->assertFalse( wcs_cart_contains_resubscribe() );
		$this->assertFalse( wcs_cart_contains_early_renewal() );
		$this->assertFalse( wcs_cart_contains_failed_renewal_order_payment() );
	}
}

<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

/**
 * Stands in for `WC_Subscription`: a real `WC_Order` plus the three things the plugin calls.
 * `payment_complete()` stays `WC_Order`'s, so a subscription with no line items lands
 * on `completed` rather than `processing`.
 */
class SubscriptionOrder extends \WC_Order {

	/** The order type WooCommerce Subscriptions registers. */
	public function get_type() {
		return 'shop_subscription';
	}

	/** The order the subscription was bought on. */
	public function get_parent() {
		$parent_id = $this->get_parent_id();

		return $parent_id ? wc_get_order( $parent_id ) : false;
	}

	/** Marks a renewal attempt as failed. */
	public function payment_failed( $new_status = 'failed' ) {
		$this->update_status( $new_status );
	}
}

<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Canned API responses for the checkout flow, the sibling of
 * CanDriveOrderManagement, for the calls a purchase makes rather than
 * the ones an admin makes afterwards.
 */
trait CanDriveCheckout {

	/**
	 * Queues a successful create-order response, the one the iframe snippet comes from.
	 *
	 * Kustom answers with the whole order, not just the snippet, and the confirmation
	 * update reads that answer back field by field, so the canned body carries every
	 * key `KCO_Request_Create` sends.
	 */
	protected function willCreateOrder( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'order_id'           => 'checkout-order-123',
					'status'             => 'checkout_incomplete',
					'purchase_country'   => 'SE',
					'purchase_currency'  => 'SEK',
					'locale'             => 'sv-SE',
					'html_snippet'       => '<div id="checkout-snippet"></div>',
					'merchant_urls'      => $this->kustomMerchantUrls(),
					'billing_countries'  => [ 'SE' ],
					'shipping_countries' => [ 'SE' ],
					'merchant_data'      => '',
					'options'            => [],
					'order_amount'       => 12500,
					'order_tax_amount'   => 2500,
					'order_lines'        => [
						[
							'type'             => 'physical',
							'reference'        => 'kustom-test-product',
							'name'             => 'Kustom test product',
							'quantity'         => 1,
							'unit_price'       => 12500,
							'tax_rate'         => 2500,
							'total_amount'     => 12500,
							'total_tax_amount' => 2500,
						],
					],
				],
				$overrides
			),
			200,
			'/checkout/v3/orders'
		);
	}

	/**
	 * The merchant URLs Kustom echoes back, shaped like the ones
	 * `KCO_Merchant_URLs::get_urls()` sends.
	 *
	 * @return array<string, string>
	 */
	private function kustomMerchantUrls(): array {
		return [
			'terms'        => home_url( '/terms/' ),
			'checkout'     => home_url( '/checkout/' ),
			'confirmation' => home_url( '/checkout/order-received/?kco_confirm=yes&kco_order_id={checkout.order.id}' ),
			'push'         => home_url( '/wc-api/KCO_WC_Push/?kco_order_id={checkout.order.id}' ),
		];
	}

	/** Queues a successful order read-back, which every confirmation path starts with. */
	protected function willRetrieveOrder( array $overrides = [] ): void {
		$this->willCreateOrder(
			array_merge(
				[
					'status' => 'checkout_complete',
				],
				$overrides
			)
		);
	}

	/** Queues a successful hosted payment page. */
	protected function willCreateHpp( string $redirect_url = 'https://pay.playground.kustom.co/eu/hpp/payment/hpp-1' ): void {
		$this->willRespondWith(
			[
				'session_id'   => 'hpp-session-1',
				'redirect_url' => $redirect_url,
			],
			201,
			'hpp/v1/sessions'
		);
	}

	/** Queues a successful recurring order, the call a subscription renews on. */
	protected function willCreateRecurringOrder( string $recurring_token = 'customer-token-1', array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'order_id'     => 'recurring-order-123',
					'fraud_status' => 'ACCEPTED',
					'redirect_url' => '',
				],
				$overrides
			),
			200,
			"/customer-token/v1/tokens/{$recurring_token}/order"
		);
	}
}

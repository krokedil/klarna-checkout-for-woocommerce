<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Whether a post purchase upsell can be added to a paid order.
 *
 * @covers \KCO_Gateway::upsell_available
 */
class UpsellAvailabilityTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * Post Purchase Upsell asks once per offer on every page load, so the answer must not cost a lookup each time.
	 *
	 * @dataProvider provide_payment_methods
	 */
	public function test_the_answer_is_looked_up_once_per_order( string $payment_method, string $country, bool $expected ): void {
		$order = $this->markAsGatewayOrder( $this->haveCapturableGatewayOrder() );
		$this->willRetrieveManagedOrder(
			[
				'initial_payment_method' => [ 'type' => $payment_method ],
				'billing_address'        => [ 'country' => $country ],
			]
		);

		foreach ( range( 1, 3 ) as $attempt ) {
			$this->assertSame( $expected, $this->gateway()->upsell_available( $order->get_id() ), "Attempt $attempt." );
		}

		$this->assertGatewayRequestCount( 1, 'ordermanagement/v1/orders' );
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_payment_methods(): array {
		return [
			'invoice'                             => [ 'INVOICE', 'SE', true ],
			'card'                                => [ 'CARD', 'SE', false ],
			'fixed amount, outside its countries' => [ 'FIXED_AMOUNT', 'US', false ],
		];
	}

	public function test_a_failed_lookup_is_tried_again(): void {
		$order = $this->markAsGatewayOrder( $this->haveCapturableGatewayOrder() );

		$this->assertFalse( $this->gateway()->upsell_available( $order->get_id() ), 'No response queued, so the lookup fails.' );

		$this->willRetrieveManagedOrder(
			[
				'initial_payment_method' => [ 'type' => 'INVOICE' ],
				'billing_address'        => [ 'country' => 'SE' ],
			]
		);

		$this->assertTrue( $this->gateway()->upsell_available( $order->get_id() ) );
		$this->assertGatewayRequestCount( 2, 'ordermanagement/v1/orders' );
	}

	private function gateway(): \KCO_Gateway {
		return new \KCO_Gateway();
	}
}

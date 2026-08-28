<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Canned order management responses for the Integration suite. */
trait CanDriveOrderManagement {

	/** Queues the response to the order lookup every OM path starts with. */
	protected function willRetrieveManagedOrder( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'order_id'                    => 'kustom-order-123',
					'status'                      => 'AUTHORIZED',
					'fraud_status'                => 'ACCEPTED',
					'remaining_authorized_amount' => 25000,
					'purchase_currency'           => 'SEK',
				],
				$overrides
			),
			200,
			'ordermanagement/v1/orders'
		);
	}

	/**
	 * Queues a successful capture. The API answers with an empty body and the
	 * capture id in a `capture-id` header.
	 */
	protected function willCapture( string $capture_id = 'capture-123' ): void {
		$this->willRespondWith( [], 201, '/captures', [ 'capture-id' => $capture_id ] );
	}

	/** Queues a successful cancellation. */
	protected function willCancel(): void {
		$this->willRespondWith( [], 204, '/cancel' );
	}

	/** Queues a successful order line update. */
	protected function willAcceptTheUpdate(): void {
		$this->willRespondWith( [], 204, '/authorization' );
	}

	/** Queues a successful refund. */
	protected function willRefund(): void {
		$this->willRespondWith( [], 201, '/refunds' );
	}
}

<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Assert;
use Tests\Support\EndToEndTester;

/**
 * The push callback takes the same per-order lock as the confirmation page.
 *
 * push_cb() reads its arguments with filter_input( INPUT_GET ), so only a real request
 * reaches it. Kustom is never contacted, see tests/_mu-plugins/09-kustom-push-lock.php.
 */
class PushCallbackCest
{
	private const KUSTOM_ORDER_ID = 'e2e-push-lock-order';

	public function a_push_for_an_order_already_being_confirmed_is_skipped(EndToEndTester $I): void
	{
		$orderId   = $this->haveOrderAwaitingPush($I);
		$heldSince = (string) time();
		$I->haveOptionInDatabase('kco_confirmation_lock_' . $orderId, $heldSince, 'no');

		$this->receivePush($I);

		Assert::assertEmpty(
			$I->grabOptionFromDatabase('kco_tests_push_lookups'),
			'The push must stop before it asks Kustom about an order another request holds.'
		);
		$I->seeOptionInDatabase(['option_name' => 'kco_confirmation_lock_' . $orderId, 'option_value' => $heldSince]);
	}

	public function a_push_holds_the_lock_while_it_works_and_releases_it(EndToEndTester $I): void
	{
		$orderId = $this->haveOrderAwaitingPush($I);

		$this->receivePush($I);

		$lookups = $I->grabOptionFromDatabase('kco_tests_push_lookups');
		Assert::assertCount(1, (array) $lookups, 'The push must look the order up in Kustom once.');
		Assert::assertTrue($lookups[0]['lock_held'], 'The push asked Kustom without holding the lock.');
		$I->dontSeeOptionInDatabase(['option_name' => 'kco_confirmation_lock_' . $orderId]);
	}

	private function haveOrderAwaitingPush(EndToEndTester $I): int
	{
		$I->haveOptionInDatabase('kco_tests_push_lock', 'yes');

		return $I->havePostInDatabase([
			'post_type'   => 'shop_order',
			'post_status' => 'wc-pending',
			'meta_input'  => [
				'_wc_klarna_order_id' => self::KUSTOM_ORDER_ID,
				'_payment_method'     => 'kco',
				'_order_total'        => '100.00',
			],
		]);
	}

	/** Kustom POSTs the push, but push_cb() only reads the query string. */
	private function receivePush(EndToEndTester $I): void
	{
		$I->amOnPage('/?wc-api=kco_wc_push&kco_wc_order_id=' . self::KUSTOM_ORDER_ID);
	}
}

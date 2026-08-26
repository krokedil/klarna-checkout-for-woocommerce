<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Facebook\WebDriver\Exception\WebDriverException;
use PHPUnit\Framework\Assert;

/**
 * The admin half of an order's life: drive the WooCommerce order screen and the gateway's
 * Order Management metabox, then read back what the gateway and WooCommerce made of it.
 *
 * Sibling of CanDriveE2ECheckout, which buys the order these steps then manage.
 */
trait CanDriveE2EOrderManagement {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/** The order management metabox, which is only on the screen once the order page has rendered. */
	private const KOM_METABOX = '#kustom-om';

	/** How long a save that talks to the gateway may take, in seconds. */
	private const KOM_SAVE_TIMEOUT = 90;

	/** Whether this test has already moved the browser to wp-admin and logged in. */
	private bool $inOrderAdmin = false;

	/** Merges settings over the ones already in the database, keyed by setting name. */
	public function haveGatewaySettingsInDatabase( array $overrides ): void {
		$settings = $this->grabOptionFromDatabase( 'woocommerce_kco_settings' );

		$this->haveOptionInDatabase(
			'woocommerce_kco_settings',
			array_replace( is_array( $settings ) ? $settings : [], $overrides )
		);
	}

	/**
	 * Opens the order edit screen, which is itself one live GET of the gateway's order.
	 * Logs in once per test.
	 */
	public function amEditingOrder( int $orderId ): void {
		if ( ! $this->inOrderAdmin ) {
			$this->loginAsAdmin();

			$this->inOrderAdmin = true;
		}

		$this->waitOutOrderScreen( fn () => $this->amOnAdminPage( "post.php?post={$orderId}&action=edit" ) );
	}

	/** The gateway's own status for the order, as the metabox just read it back. */
	public function seeGatewayOrderStatusIs( string $status ): void {
		$actual = trim(
			(string) $this->grabTextFrom(
				'//div[@id="kustom-om"]//h4[contains(.,"Kustom order status")]/following-sibling::span[1]'
			)
		);

		Assert::assertSame( $status, $actual, "The gateway says the order is '{$actual}', expected '{$status}'." );
	}

	/** Moves the order to a status and waits out whatever it makes the plugin send. */
	public function changeOrderStatusTo( string $status ): void {
		$this->selectOption( '#order_status', "wc-{$status}" );
		$this->saveOrderScreen();
	}

	/** Applies an order management metabox action, which only renders when its kom_auto_* setting is off. */
	public function applyOrderManagementAction( string $action ): void {
		$this->selectOption( '#kom_order_actions', $action );
		$this->saveOrderScreen();
	}

	/**
	 * Edits the first line item down to a quantity, which fires
	 * `woocommerce_saved_order_items`. The order has to be on an editable status.
	 */
	public function reduceLineItemQuantityTo( int $quantity ): void {
		$this->click( '#order_line_items tr.item a.edit-order-item' );
		$this->waitForElementVisible( '#order_line_items input.quantity' );
		$this->fillField( '#order_line_items input.quantity', (string) $quantity );

		// The line totals only follow the quantity on a change event, which typing does not fire.
		$this->executeJS( "jQuery('#order_line_items input.quantity').trigger('change');" );

		$this->click( '.wc-order-add-item button.save-action' );
		$this->waitForJS(
			"return typeof jQuery !== 'undefined' && jQuery.active === 0"
			. " && !document.querySelector('#woocommerce-order-items .blockUI');",
			self::KOM_SAVE_TIMEOUT
		);
	}

	/** Refunds an amount through the gateway. */
	public function refundOrderViaGateway( string $amount ): void {
		$this->click( '.button.refund-items' );
		$this->waitForElementVisible( '#refund_amount' );

		// WooCommerce's JS reads these back with the store's decimal separator, so a dot
		// would be parsed as a thousands separator.
		$decimal = (string) $this->executeJS( 'return woocommerce_admin.mon_decimal_point;' );
		$amount  = str_replace( '.', $decimal, $amount );

		$this->fillField( '#order_line_items tr.item input.refund_line_total', $amount );

		// #refund_amount is readonly, and WooCommerce only recomputes it from the line field
		// on a change event that typing does not fire. Set it outright, since it is the value
		// the refund actually posts.
		$this->executeJS( 'jQuery("#refund_amount").val(' . json_encode( $amount ) . ').trigger("change");' );

		// Both dialogs block the driver; take them over so the refund goes through and
		// whatever WooCommerce would have alerted stays readable.
		$this->executeJS(
			'window.kcoRefunding = true; window.kcoRefundError = null;'
			. ' window.confirm = () => true;'
			. ' window.alert = message => { window.kcoRefundError = message; };'
		);

		$this->click( 'button.do-api-refund' );

		// WooCommerce reloads the screen on success, which is what drops the marker.
		$this->waitForJS(
			'return window.kcoRefunding === undefined || !!window.kcoRefundError;',
			self::KOM_SAVE_TIMEOUT
		);

		$error = $this->executeJS( 'return window.kcoRefundError || null;' );
		if ( ! empty( $error ) ) {
			$posted = $this->executeJS( 'return jQuery("#refund_amount").val();' );

			Assert::fail( "The refund of {$posted} was refused: {$error}" );
		}

		$this->waitForElement( self::KOM_METABOX, 30 );
	}

	/** Asserts every needle appears somewhere in the order's notes. */
	public function seeOrderNotes( int $orderId, array $needles ): void {
		$notes = $this->grabOrderNotes( $orderId );

		foreach ( $needles as $needle ) {
			Assert::assertStringContainsString( $needle, $notes, "Order {$orderId} notes:\n{$notes}" );
		}
	}

	/** Asserts no needle appears in the order's notes, ignoring case. */
	public function dontSeeOrderNotes( int $orderId, array $needles ): void {
		$notes = $this->grabOrderNotes( $orderId );

		foreach ( $needles as $needle ) {
			Assert::assertStringNotContainsStringIgnoringCase( $needle, $notes, "Order {$orderId} notes:\n{$notes}" );
		}
	}

	/** Asserts post meta, where a null expected value means "any non-empty value". */
	public function seeOrderMeta( int $orderId, array $expected ): void {
		foreach ( $expected as $meta_key => $meta_value ) {
			// Read back rather than asserted, so a failure reports what was written.
			$actual = $this->grabFromDatabase(
				'wp_postmeta',
				'meta_value',
				[
					'post_id'  => $orderId,
					'meta_key' => $meta_key,
				]
			);

			if ( $meta_value === null ) {
				Assert::assertNotEmpty( $actual, "Order {$orderId} has no {$meta_key}." );
				continue;
			}

			Assert::assertSame(
				$meta_value,
				$actual,
				"Order {$orderId} has {$meta_key} = " . var_export( $actual, true )
					. ', expected ' . var_export( $meta_value, true )
			);
		}
	}

	/** Asserts the order carries none of the given meta keys. */
	public function dontSeeOrderMeta( int $orderId, array $metaKeys ): void {
		foreach ( $metaKeys as $meta_key ) {
			$actual = $this->grabFromDatabase(
				'wp_postmeta',
				'meta_value',
				[
					'post_id'  => $orderId,
					'meta_key' => $meta_key,
				]
			);

			Assert::assertEmpty(
				$actual,
				"Order {$orderId} has {$meta_key} = " . var_export( $actual, true ) . ', expected none.'
			);
		}
	}

	/**
	 * How many refunds the order has. WooCommerce deletes the refund again when the
	 * gateway errors, so the count surviving is the gateway having taken the body.
	 */
	public function seeRefundCount( int $orderId, int $expected ): void {
		$actual = $this->countRowsInDatabase(
			'wp_posts',
			[
				'post_parent' => $orderId,
				'post_type'   => 'shop_order_refund',
			]
		);

		Assert::assertSame( $expected, $actual, "Order {$orderId} has {$actual} refunds, expected {$expected}." );
	}

	/** The newest refund on the order, so its meta can be asserted with seeOrderMeta(). */
	public function grabLatestRefundId( int $orderId ): int {
		$refund_ids = $this->grabColumnFromDatabase(
			'wp_posts',
			'ID',
			[
				'post_parent' => $orderId,
				'post_type'   => 'shop_order_refund',
			]
		);

		Assert::assertNotEmpty( $refund_ids, "Order {$orderId} has no refund." );

		return (int) max( array_map( 'intval', $refund_ids ) );
	}

	/** Submits the order screen and waits for the page the save redirects to. */
	private function saveOrderScreen(): void {
		// Out from under the admin bar, which otherwise swallows the click.
		$this->scrollTo( 'button.save_order', 0, -120 );

		$this->waitOutOrderScreen( fn () => $this->click( 'button.save_order' ) );
	}

	/**
	 * Loads an order screen and waits for the metabox, tolerating a page load that outruns
	 * the driver's budget: saving one is an API round trip before the response even
	 * starts, and a capture spends ~30s of it. The driver stops the load when it gives up,
	 * which leaves the screen it had already rendered, so the metabox is the real signal.
	 */
	private function waitOutOrderScreen( callable $navigate ): void {
		try {
			$navigate();
		} catch ( WebDriverException $e ) {
			$this->comment( 'kom: the order screen outran the page load timeout, reading it anyway' );
		}

		$this->waitForElement( self::KOM_METABOX, self::KOM_SAVE_TIMEOUT );
	}

	/** Every note on the order as one string, so a failure prints all of them. */
	private function grabOrderNotes( int $orderId ): string {
		return implode(
			"\n",
			$this->grabColumnFromDatabase(
				'wp_comments',
				'comment_content',
				[
					'comment_post_ID' => $orderId,
					'comment_type'    => 'order_note',
				]
			)
		);
	}
}

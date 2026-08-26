<?php

declare(strict_types=1);

namespace Tests\Support;

use lucatume\WPBrowser\TestCase\WPTestCase;
use Qameta\Allure\Allure;
use Tests\Support\Reporting\Redactor;
use Tests\Support\Reporting\SecretRegistry;
use Tests\Support\Traits\CanBuildCartsAndOrders;
use Tests\Support\Traits\CanConfigureStore;
use Tests\Support\Traits\CanDriveCheckout;
use Tests\Support\Traits\CanDriveOrderManagement;
use Tests\Support\Traits\CanFakeSubscriptions;
use Tests\Support\Traits\CanInterceptHttp;
use Tests\Support\Traits\CanManageProducts;
use Tests\Support\Traits\CanSnapshotRequests;

/**
 * Base class for Integration tests. Resets the WooCommerce state a transaction
 * rollback does not cover before and after every test, and blocks outbound HTTP.
 */
abstract class IntegrationTestCase extends WPTestCase {

	use CanConfigureStore;
	use CanManageProducts;
	use CanBuildCartsAndOrders;
	use CanInterceptHttp;
	use CanDriveOrderManagement;
	use CanDriveCheckout;
	use CanFakeSubscriptions;
	use CanSnapshotRequests;

	/** @var \Tests\Support\Reporting\Redactor|null */
	private static $reportRedactor = null;

	/**
	 * Store profile applied after the reset, before each test. One of `se`
	 * (SE/SEK, 25% VAT), `se-no-tax`, `us` (US/USD, 8.5% sales tax), or null for
	 * WooCommerce defaults with no credentials.
	 *
	 * The gateway resolves its credential set from the store's base country, so a
	 * Swedish store signs with the `eu` pair and a US store with the `us` one.
	 */
	protected ?string $storeProfile = null;

	protected function setUp(): void {
		parent::setUp();

		// On before the store reset, so a fixture can never reach the network.
		$this->interceptHttp();
		$this->resetHttpInterception();

		$this->resetStore();
		$this->applyStoreProfile();
	}

	/**
	 * Applies $storeProfile. Fails loud on a typo rather than silently testing US/USD.
	 */
	private function applyStoreProfile(): void {
		switch ( $this->storeProfile ) {
			case null:
				return;
			case 'se':
				$this->configureSwedishStore();
				$this->haveGatewayCredentials( 'eu' );
				return;
			case 'se-no-tax':
				$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
				$this->haveGatewayCredentials( 'eu' );
				return;
			case 'us':
				$this->configureUsStore();
				$this->haveGatewayCredentials( 'us' );
				$this->haveCustomerAddress( $this->usAddress(), $this->usAddress() );
				return;
			default:
				throw new \InvalidArgumentException( sprintf( 'Unknown store profile "%s".', $this->storeProfile ) );
		}
	}

	protected function tearDown(): void {
		// Before resetHttpInterception(), which throws the recording away.
		$this->attachHttpRequestsToReport();

		$this->resetStore();
		$this->resetHttpInterception();

		parent::tearDown();
	}

	/**
	 * Puts the API traffic this test provoked into the test report, pass or fail —
	 * a green report doubles as a record of what the plugin actually sends.
	 */
	private function attachHttpRequestsToReport(): void {
		$json = $this->describeHttpRequestsForReport();

		if ( null === $json ) {
			return;
		}

		Allure::attachment( 'http-requests.json', $json, 'application/json' );
	}

	/**
	 * The intercepted requests as scrubbed, pretty-printed JSON. Null when the
	 * test made no requests.
	 */
	protected function describeHttpRequestsForReport(): ?string {
		$requests = $this->httpRequests();

		if ( empty( $requests ) ) {
			return null;
		}

		$json = wp_json_encode( $requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $json ) ) {
			return null;
		}

		return $this->reportRedactor()->scrub( $json );
	}

	private function reportRedactor(): Redactor {
		if ( null === self::$reportRedactor ) {
			self::$reportRedactor = SecretRegistry::fromEnvironment();
		}

		return self::$reportRedactor;
	}

	/**
	 * Puts the store back to a blank slate.
	 */
	protected function resetStore(): void {
		$this->deleteAllTaxRates();
		$this->haveStorePages();
		$this->emptyCart();
		$this->haveCustomerAddress();
		$this->setGatewaySettings( [] );
		delete_option( 'kom_settings' );
		$this->resetGatewaySession();
		$this->resetFakeSubscriptions();
	}

	/**
	 * Reads an order back from the database.
	 */
	protected function reload( \WC_Order $order ): \WC_Order {
		return wc_get_order( $order->get_id() );
	}

	/**
	 * The order's status as persisted, not as the test's copy remembers it.
	 */
	protected function statusOf( \WC_Order $order ): string {
		return $this->reload( $order )->get_status();
	}

	/**
	 * The order's notes, newest first.
	 *
	 * @return array<int, string>
	 */
	protected function orderNotes( \WC_Order $order ): array {
		return array_column( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ), 'content' );
	}

	/**
	 * @return array<int, string>
	 */
	private function orderNotesContaining( \WC_Order $order, string $text ): array {
		return array_values(
			array_filter(
				$this->orderNotes( $order ),
				static function ( $note ) use ( $text ) {
					return false !== strpos( $note, $text );
				}
			)
		);
	}

	protected function assertOrderHasNote( \WC_Order $order, string $expected ): void {
		$this->assertNotEmpty(
			$this->orderNotesContaining( $order, $expected ),
			sprintf(
				'No order note containing "%s". Got: %s',
				$expected,
				implode( ' | ', $this->orderNotes( $order ) )
			)
		);
	}

	protected function assertOrderHasNoNote( \WC_Order $order, string $unexpected ): void {
		$this->assertSame(
			[],
			$this->orderNotesContaining( $order, $unexpected ),
			sprintf( 'Expected no order note containing "%s".', $unexpected )
		);
	}

	/**
	 * @param mixed $result The value to check.
	 */
	protected function assertWpErrorCode( string $expected_code, $result ): void {
		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			sprintf( 'Expected a WP_Error with code "%s".', $expected_code )
		);
		$this->assertSame( $expected_code, $result->get_error_code() );
	}

	/**
	 * The first order line of the given type, e.g. `physical` or `shipping_fee`.
	 */
	protected function findOrderLine( array $order_lines, string $type ): ?array {
		foreach ( $order_lines as $order_line ) {
			if ( ( $order_line['type'] ?? null ) === $type ) {
				return $order_line;
			}
		}

		return null;
	}

	/**
	 * Asserts that an order line of the given type exists, and returns it.
	 */
	protected function assertHasOrderLine( array $order_lines, string $type ): array {
		$order_line = $this->findOrderLine( $order_lines, $type );

		$this->assertNotNull(
			$order_line,
			sprintf(
				'Expected an order line of type "%s", got types: %s',
				$type,
				implode( ', ', array_column( $order_lines, 'type' ) )
			)
		);

		return $order_line;
	}
}

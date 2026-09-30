<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * What reaches the log file and the system status report. Every request logs its full
 * payload, so the assertion that matters is which parts of a Kustom order a support log keeps.
 *
 * @covers \Krokedil\KustomCheckout\Logging\LogMasking
 * @covers \KCO_Logger
 */
class LogMaskingTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** The customer details the fixtures put into every payload. */
	private const PERSONAL_DATA = [
		'karl@example.com' => 'billing email',
		'+46701234567'     => 'billing phone',
		'Karlsson'         => 'family name',
		'Storgatan 1'      => 'street address',
		'Lgh 1102'         => 'second address line',
		'1980-01-01'       => 'date of birth',
		'800101-1234'      => 'national identification number',
	];

	/** What Kustom answers with once the customer has filled in the checkout. */
	private const CUSTOMER = [
		'billing_address'  => [
			'given_name'      => 'Karl',
			'family_name'     => 'Karlsson',
			'email'           => 'karl@example.com',
			'phone'           => '+46701234567',
			'street_address'  => 'Storgatan 1',
			'street_address2' => 'Lgh 1102',
			'postal_code'     => '41106',
			'city'            => 'Göteborg',
			'country'         => 'se',
		],
		'shipping_address' => [
			'given_name'     => 'Karl',
			'family_name'    => 'Karlsson',
			'street_address' => 'Storgatan 1',
			'postal_code'    => '41106',
			'city'           => 'Göteborg',
			'country'        => 'se',
		],
		'customer'         => [
			'type'                           => 'person',
			'date_of_birth'                  => '1980-01-01',
			'national_identification_number' => '800101-1234',
		],
	];

	protected function setUp(): void {
		parent::setUp();

		delete_option( 'krokedil_debuglog_kco' );
		$this->haveLoggingEnabled();
	}

	/** @dataProvider provide_requests_with_customer_data */
	public function test_a_request_logs_no_customer_data( string $request ): void {
		$this->makeRequest( $request );

		$this->assertNotLogged( self::PERSONAL_DATA );
	}

	/** @return array<string, array{0: string}> */
	public function provide_requests_with_customer_data(): array {
		return [
			'create order'   => [ 'create' ],
			'update order'   => [ 'update' ],
			'retrieve order' => [ 'retrieve' ],
		];
	}

	public function test_a_request_logs_no_credentials(): void {
		$this->makeRequest( 'retrieve' );

		$request = $this->loggedEntry( 'KCO get order' )['request'];

		$this->assertSame( '[REDACTED]', $request['headers']['Authorization'] );
		$this->assertStringNotContainsString( base64_encode( 'mid:secret' ), $this->loggedText() );
	}

	/**
	 * Masking a whole payload is easy and useless. These are the fields a support
	 * case is actually read for, and they have to survive.
	 *
	 * @dataProvider provide_fields_kept_readable
	 */
	public function test_the_log_keeps_what_support_reads( string $value, string $description ): void {
		$this->makeRequest( 'retrieve' );

		$this->assertStringContainsString( $value, $this->loggedText(), "The log lost the {$description}." );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_fields_kept_readable(): array {
		return [
			'the city'         => [ 'Göteborg', 'billing city' ],
			'the postal code'  => [ '41106', 'billing postal code' ],
			// Basket contents are kept deliberately: an order line is what a dispute is about.
			'the order lines'  => [ 'Kustom test product', 'product name' ],
			// The order id is an identifier, not a credential, and is how logs are correlated.
			'the order id'     => [ 'checkout-order-123', 'Kustom order id' ],
			// A callback Kustom cannot reach is a common case, and the push URL carries no secret.
			'the push URL'     => [ '/wc-api/KCO_WC_Push/', 'push URL' ],
			'the customer type' => [ '"type":"person"', 'customer type' ],
		];
	}

	/** The checkout iframe is a live session for whoever holds it. */
	public function test_the_checkout_snippet_never_reaches_the_log(): void {
		$this->makeRequest( 'create' );

		$this->assertSame( '[REDACTED]', $this->loggedEntry( 'KCO create order' )['response']['body']['html_snippet'] );
		$this->assertStringNotContainsString( 'checkout-snippet', $this->loggedText() );
	}

	/** The signing key is what the confirmation of an external payment method is validated against. */
	public function test_the_signing_key_never_reaches_the_log(): void {
		$this->makeRequest( 'create', [ 'merchant_data' => wp_json_encode( [ 'signing_key' => 'signing-key-1' ] ) ] );

		$this->assertStringNotContainsString( 'signing-key-1', $this->loggedText() );
	}

	/**
	 * The allow list is what makes the masking hold when Kustom adds a field, so a
	 * field no rule or key name describes has to be masked too.
	 */
	public function test_a_field_no_rule_names_is_masked(): void {
		$this->makeRequest(
			'retrieve',
			[
				'billing_address' => [ 'title' => 'Mr', 'city' => 'Göteborg' ],
				'customer'        => [ 'type' => 'person', 'gender' => 'male' ],
			]
		);

		$body = $this->loggedEntry( 'KCO get order' )['response']['body'];

		$this->assertSame( '[REDACTED]', $body['billing_address']['title'] );
		$this->assertSame( '[REDACTED]', $body['customer']['gender'] );
		$this->assertSame( 'Göteborg', $body['billing_address']['city'], 'The allow list kept the city.' );
	}

	/** The confirmation and hosted payment page URLs carry the order key, which is enough to read the order. */
	public function test_the_order_key_never_reaches_the_log(): void {
		$order = $this->haveGatewayOrder();

		$this->willCreateHpp();
		KCO_WC()->api->create_klarna_hpp_url( 'checkout-order-123', $order->get_id() );

		$entry = $this->loggedEntry( 'KCO create HPP' );

		$this->assertSame( '[REDACTED]', $entry['request']['body']['merchant_urls']['success'] );
		$this->assertStringNotContainsString( $order->get_order_key(), $this->loggedText() );
		// The hosted payment page URL is a single use capability: whoever holds it can pay with it.
		$this->assertStringNotContainsString( '/hpp/payment/hpp-1', $this->loggedText() );
	}

	/** Kustom addresses a recurring token by the path, so logging the URL would log the token. */
	public function test_a_recurring_token_never_reaches_the_log(): void {
		$order = $this->haveGatewayOrder();

		$this->willCreateRecurringOrder( 'customer-token-1' );
		KCO_WC()->api->create_recurring_order( $order->get_id(), 'customer-token-1' );

		$entry = $this->loggedEntry( 'KCO create recurring order' );

		$this->assertStringEndsWith( '/customer-token/v1/tokens/[REDACTED]/order', $entry['request_url'] );
		$this->assertStringNotContainsString( 'customer-token-1', $this->loggedText() );
	}

	/**
	 * The order management layer logs what Kustom returns, which is the richest
	 * customer record the plugin ever handles.
	 */
	public function test_an_order_management_response_logs_no_customer_data(): void {
		$order = $this->haveCapturableGatewayOrder();

		$this->willRetrieveManagedOrder( self::CUSTOMER );
		$this->willCancel();

		KCO_WC()->order_management->cancel_klarna_order( $order->get_id(), false );

		$this->assertNotLogged( self::PERSONAL_DATA );
		$this->assertStringContainsString( 'Göteborg', $this->loggedText(), 'The order management log lost the city.' );
	}

	/**
	 * A failed request is also kept in an option and printed in the system status
	 * report, which is the copy most often pasted into a support ticket.
	 */
	public function test_a_failed_request_is_stored_masked(): void {
		$order        = $this->haveGatewayOrder();
		$klarna_order = $this->kustomRetrievedOrder( [ 'merchant_data' => wp_json_encode( [ 'signing_key' => 'signing-key-1' ] ) ] );

		$this->willRejectWith( '/checkout/v3/orders/', 'Bad value: merchant_urls', 400 );
		KCO_WC()->api->update_klarna_confirmation( $klarna_order['order_id'], $klarna_order, $order->get_id() );

		$stored = $this->storedLogText();

		$this->assertStringContainsString( 'Bad value: merchant_urls', $stored, 'The failed request was not stored.' );
		$this->assertNotLogged(
			[
				$order->get_order_key() => 'order key',
				'signing-key-1'         => 'signing key',
				base64_encode( 'mid:secret' ) => 'credentials',
			],
			$stored
		);
	}

	/** Drives a request of the given kind, with the customer's details in Kustom's answer. */
	private function makeRequest( string $request, array $overrides = [] ): void {
		$answer = array_merge( self::CUSTOMER, $overrides );

		switch ( $request ) {
			case 'create':
				$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
				$this->haveCartWith( [ $this->haveSimpleProduct( [ 'name' => 'Kustom test product', 'price' => '100.00' ] ) ] );
				$this->willCreateOrder( $answer );
				KCO_WC()->api->create_klarna_order();
				return;
			case 'update':
				$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
				$this->haveCartWith( [ $this->haveSimpleProduct( [ 'name' => 'Kustom test product', 'price' => '100.00' ] ) ] );
				$this->willCreateOrder( $answer );
				KCO_WC()->api->update_klarna_order( 'checkout-order-123', null, true );
				return;
			default:
				$this->willRetrieveOrder( $answer );
				KCO_WC()->api->get_klarna_order( 'checkout-order-123' );
		}
	}
}

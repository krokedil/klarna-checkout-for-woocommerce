<?php

declare(strict_types=1);

namespace Tests\Integration;

use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Tests\Support\IntegrationTestCase;

/**
 * The cart token the Store API loads the shopper's session from when Kustom validates a block checkout order.
 * WooCommerce 11.3.1 rejects tokens whose `iss` is not `store-api`, and then sees an empty cart.
 *
 * @covers \Krokedil\KustomCheckout\Utility\BlocksUtility::create_cart_token
 * @covers \Krokedil\KustomCheckout\Utility\BlocksUtility::refresh_cart_token
 */
class CartTokenTest extends IntegrationTestCase {

	public function test_a_new_cart_token_is_issued_by_the_store_api(): void {
		$payload = JsonWebToken::get_parts( BlocksUtility::create_cart_token( 't_shopper' ) )->payload;

		$this->assertSame( 'store-api', $payload->iss );
		$this->assertSame( 't_shopper', $payload->user_id );
	}

	/**
	 * @dataProvider provide_stored_tokens
	 */
	public function test_a_stored_token_only_gets_reissued_when_we_signed_it_for_another_issuer( string $iss, string $secret, bool $reissued ): void {
		$token = JsonWebToken::create(
			[
				'user_id' => 't_shopper',
				'exp'     => time() + HOUR_IN_SECONDS,
				'iss'     => $iss,
			],
			'' === $secret ? '@' . wp_salt() : $secret
		);

		$refreshed = BlocksUtility::refresh_cart_token( $token );

		if ( ! $reissued ) {
			$this->assertSame( $token, $refreshed );
			return;
		}

		$payload = JsonWebToken::get_parts( $refreshed )->payload;
		$this->assertTrue( JsonWebToken::validate( $refreshed, '@' . wp_salt() ) );
		$this->assertSame( 'store-api', $payload->iss );
		$this->assertSame( 't_shopper', $payload->user_id );
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_stored_tokens(): array {
		return [
			'issued before the fix'           => [ 'wc/store/v1', '', true ],
			'already issued by the store api' => [ 'store-api', '', false ],
			'signed with another secret'      => [ 'wc/store/v1', 'not-our-secret', false ],
		];
	}
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * When the checkout block may sync the cart to the Kustom order.
 *
 * @covers \Krokedil\KustomCheckout\Blocks\BlockExtension::get_address
 */
class BlockExtensionTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->simulateCheckoutPage();
		WC()->session->set( 'kco_wc_order_id', 'checkout-order-123' );
	}

	protected function tearDown(): void {
		unset( $_GET['kco_confirm'], $_GET['kco_order_id'], $GLOBALS['wp']->query_vars['order-received'] );

		parent::tearDown();
	}

	public function test_the_confirmation_return_leaves_the_kustom_order_alone(): void {
		$_GET['kco_confirm']  = 'yes';
		$_GET['kco_order_id'] = 'checkout-order-123';

		$this->assertSame( [], KCO_WC()->block_extension->get_address() );
		$this->assertNoGatewayRequests( 'Kustom answers 403 READ_ONLY_ORDER once the purchase is complete.' );
	}

	public function test_the_thank_you_page_leaves_the_kustom_order_alone(): void {
		$GLOBALS['wp']->query_vars['order-received'] = '920';

		$this->assertSame( [], KCO_WC()->block_extension->get_address() );
		$this->assertNoGatewayRequests( 'Kustom answers 403 READ_ONLY_ORDER once the purchase is complete.' );
	}
}

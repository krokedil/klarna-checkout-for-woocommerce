<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The "Always use Kustom Checkout" setting keeps the classic checkout on Kustom.
 *
 * @covers \KCO_Templates::maybe_force_kco
 * @covers ::kco_wc_show_another_gateway_button
 * @covers \KCO_Templates::check_that_kco_template_has_loaded
 */
class ForcedPaymentMethodTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se-no-tax';

	protected function setUp(): void {
		parent::setUp();

		// A second gateway, standing in for an external payment method such as Vipps.
		update_option( 'woocommerce_bacs_settings', [ 'enabled' => 'yes' ] );
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
	}

	protected function tearDown(): void {
		delete_option( 'woocommerce_bacs_settings' );
		remove_all_filters( 'wp_redirect' );

		parent::tearDown();
	}

	/**
	 * @dataProvider provide_checkout_visits
	 */
	public function test_which_payment_method_the_checkout_lands_on( array $settings, bool $on_checkout, ?string $chosen, string $expected ): void {
		$this->haveGatewayCredentials( $settings );
		$this->reloadPaymentGateways();
		WC()->session->set( 'chosen_payment_method', $chosen );

		if ( $on_checkout ) {
			$this->simulateCheckoutPage();
		}

		\KCO_Templates::get_instance()->maybe_force_kco();

		$this->assertSame( $expected, (string) WC()->session->get( 'chosen_payment_method' ) );
	}

	/** @return array<string, array{0: array, 1: bool, 2: ?string, 3: string}> */
	public function provide_checkout_visits(): array {
		$forced = [ 'force_payment_method' => 'yes' ];

		return [
			'forced, another gateway chosen'                   => [ $forced, true, 'bacs', 'kco' ],
			'forced, nothing chosen yet'                       => [ $forced, true, null, 'kco' ],
			'off by default'                                   => [ [], true, 'bacs', 'bacs' ],
			'forced, away from the checkout'                   => [ $forced, false, 'bacs', 'bacs' ],
			'forced, Kustom disabled, keeps a working gateway' => [ $forced + [ 'enabled' => 'no' ], true, 'bacs', 'bacs' ],
		];
	}

	/**
	 * @dataProvider provide_button_visibility
	 */
	public function test_forcing_hides_the_select_another_method_button( array $settings, bool $shown ): void {
		$this->haveGatewayCredentials( $settings );
		$this->reloadPaymentGateways();
		$this->simulateCheckoutPage();

		ob_start();
		kco_wc_show_another_gateway_button();
		$html = (string) ob_get_clean();

		$this->assertSame( $shown, str_contains( $html, 'klarna-checkout-select-other' ) );
	}

	/** @return array<string, array{0: array, 1: bool}> */
	public function provide_button_visibility(): array {
		return [
			'shown by default' => [ [], true ],
			'hidden if forced' => [ [ 'force_payment_method' => 'yes' ], false ],
		];
	}

	/**
	 * When Kustom fails to render, the plugin normally switches the customer to another
	 * gateway and reloads. Forced, that reload would land straight back on Kustom.
	 */
	public function test_a_failed_render_does_not_fall_back_to_another_gateway_when_forced(): void {
		$this->haveGatewayCredentials( [ 'force_payment_method' => 'yes' ] );
		$this->reloadPaymentGateways();
		$this->simulateCheckoutPage();
		WC()->session->set( 'chosen_payment_method', 'kco' );

		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( "Redirected to {$location}" );
			}
		);

		\KCO_Templates::get_instance()->check_that_kco_template_has_loaded();

		$this->assertSame( 'kco', WC()->session->get( 'chosen_payment_method' ) );
	}
}

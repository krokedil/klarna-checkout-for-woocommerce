<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Admin\SetupChecks;
use KrokedilKlarnaCheckoutDeps\Krokedil\SettingsPage\SettingsPage;
use KrokedilKlarnaCheckoutDeps\Krokedil\SettingsPage\SetupChecks\SetupCheck;
use Tests\Support\IntegrationTestCase;

/**
 * The store settings Kustom Checkout depends on are reported through the settings-page package.
 *
 * @covers \Krokedil\KustomCheckout\Admin\SetupChecks
 */
class SetupChecksTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->clearRegisteredChecks();
		$this->haveWorkingStore();
		( new SetupChecks() )->register();
	}

	protected function tearDown(): void {
		$this->clearRegisteredChecks();
		parent::tearDown();
	}

	/**
	 * @dataProvider provide_misconfigurations
	 */
	public function test_a_misconfigured_store_fails_its_check( callable $break_store, array $expected_failed ): void {
		$break_store();

		$failed = SettingsPage::get_instance()->setup_checks( 'kco' )->get_failed();

		$this->assertSame( $expected_failed, array_column( $failed, 'id' ) );
	}

	/** @return array<string, array{0: callable, 1: string[]}> */
	public function provide_misconfigurations(): array {
		return [
			'a working store passes every check' => [ static function () {}, [] ],
			'no terms page'                      => [ static fn() => update_option( 'woocommerce_terms_page_id', 0 ), [ 'terms_page' ] ],
			'plain permalinks'                   => [ static fn() => update_option( 'permalink_structure', '' ), [ 'pretty_permalinks' ] ],
			'served over http'                   => [ static fn() => update_option( 'home', 'http://example.org' ), [ 'https' ] ],
			'prices without decimals'            => [ static fn() => update_option( 'woocommerce_price_num_decimals', '0' ), [ 'price_decimals' ] ],
		];
	}

	public function test_decimals_are_only_recommended(): void {
		update_option( 'woocommerce_price_num_decimals', '0' );
		update_option( 'woocommerce_terms_page_id', 0 );

		$required = SettingsPage::get_instance()->setup_checks( 'kco' )->get_failed( SetupCheck::SEVERITY_REQUIRED );

		$this->assertSame( [ 'terms_page' ], array_column( $required, 'id' ) );
	}

	public function test_the_system_report_explains_each_check_in_a_help_tip(): void {
		require_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';

		ob_start();
		SettingsPage::get_instance()->output_setup_checks_report();
		$report = (string) ob_get_clean();

		$this->assertSame( 4, substr_count( $report, 'woocommerce-help-tip' ), 'A scoped function_exists() guard hides the help tips.' );
	}

	/**
	 * @dataProvider provide_enabled_settings
	 */
	public function test_the_admin_notice_follows_the_enabled_setting( string $enabled, bool $expect_notice ): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->setGatewaySettings( [ 'enabled' => $enabled ] );
		update_option( 'woocommerce_terms_page_id', 0 );

		ob_start();
		SettingsPage::get_instance()->output_setup_checks_notices();
		$notice = (string) ob_get_clean();

		$this->assertSame( $expect_notice, str_contains( $notice, 'krokedil-setup-checks-notice' ), $notice );
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_enabled_settings(): array {
		return [
			'enabled gateway shows the notice' => [ 'yes', true ],
			'disabled gateway stays quiet'     => [ 'no', false ],
		];
	}

	/** The package keeps its registries on a singleton with no public reset. */
	private function clearRegisteredChecks(): void {
		$registries = new \ReflectionProperty( SettingsPage::class, 'setup_checks' );
		$registries->setAccessible( true );
		$registries->setValue( SettingsPage::get_instance(), [] );
	}

	private function haveWorkingStore(): void {
		update_option( 'woocommerce_terms_page_id', self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] ) );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'home', 'https://example.org' );
		update_option( 'woocommerce_price_num_decimals', '2' );
	}
}

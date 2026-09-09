<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\KustomCheckout\Migrations\CredentialsMigration;
use Tests\Support\IntegrationTestCase;

/**
 * Copies a store's per-region (EU/US) credentials into the single credential set,
 * leaving the old fields in place for a rollback.
 *
 * @covers \Krokedil\KustomCheckout\Migrations\CredentialsMigration
 */
class CredentialsMigrationTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Every test starts from a clean migration state, regardless of what a
		// previous test (or this same option persisting across the suite) left behind.
		delete_option( CredentialsMigration::VERSION_OPTION );
		delete_option( CredentialsMigration::NOTICE_OPTION );
	}

	public function test_eu_only_credentials_are_copied_to_the_new_fields(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->setGatewaySettings(
			[
				'merchant_id_eu'        => 'live-mid-eu',
				'shared_secret_eu'      => 'live-secret-eu',
				'test_merchant_id_eu'   => 'test-mid-eu',
				'test_shared_secret_eu' => 'test-secret-eu',
			]
		);

		( new CredentialsMigration() )->migrate();

		$settings = get_option( 'woocommerce_kco_settings' );

		$this->assertSame( 'live-mid-eu', $settings['merchant_id'] );
		$this->assertSame( 'live-secret-eu', $settings['shared_secret'] );
		$this->assertSame( 'test-mid-eu', $settings['test_merchant_id'] );
		$this->assertSame( 'test-secret-eu', $settings['test_shared_secret'] );

		// The old fields are kept, so a rollback still has working credentials.
		$this->assertSame( 'live-mid-eu', $settings['merchant_id_eu'] );

		$this->assertFalse( get_option( CredentialsMigration::NOTICE_OPTION ) );
	}

	public function test_us_only_credentials_are_copied_to_the_new_fields(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->setGatewaySettings(
			[
				'merchant_id_us'        => 'live-mid-us',
				'shared_secret_us'      => 'live-secret-us',
				'test_merchant_id_us'   => 'test-mid-us',
				'test_shared_secret_us' => 'test-secret-us',
			]
		);

		( new CredentialsMigration() )->migrate();

		$settings = get_option( 'woocommerce_kco_settings' );

		$this->assertSame( 'live-mid-us', $settings['merchant_id'] );
		$this->assertSame( 'live-secret-us', $settings['shared_secret'] );
		$this->assertSame( 'test-mid-us', $settings['test_merchant_id'] );
		$this->assertSame( 'test-secret-us', $settings['test_shared_secret'] );

		$this->assertSame( 'live-mid-us', $settings['merchant_id_us'] );

		$this->assertFalse( get_option( CredentialsMigration::NOTICE_OPTION ) );
	}

	public function test_both_regions_filled_in_defaults_to_us_for_a_us_store(): void {
		$this->configureStore( [ 'country' => 'US', 'currency' => 'USD', 'calc_taxes' => false ] );
		$this->setGatewaySettings(
			[
				'merchant_id_eu'        => 'live-mid-eu',
				'shared_secret_eu'      => 'live-secret-eu',
				'test_merchant_id_eu'   => 'test-mid-eu',
				'test_shared_secret_eu' => 'test-secret-eu',
				'merchant_id_us'        => 'live-mid-us',
				'shared_secret_us'      => 'live-secret-us',
				'test_merchant_id_us'   => 'test-mid-us',
				'test_shared_secret_us' => 'test-secret-us',
			]
		);

		( new CredentialsMigration() )->migrate();

		$settings = get_option( 'woocommerce_kco_settings' );

		$this->assertSame( 'live-mid-us', $settings['merchant_id'] );
		$this->assertSame( 'live-secret-us', $settings['shared_secret'] );
		$this->assertSame( 'test-mid-us', $settings['test_merchant_id'] );
		$this->assertSame( 'test-secret-us', $settings['test_shared_secret'] );

		// Both old regions are still stored, untouched.
		$this->assertSame( 'live-mid-eu', $settings['merchant_id_eu'] );
		$this->assertSame( 'live-mid-us', $settings['merchant_id_us'] );

		$this->assertSame( 'us', get_option( CredentialsMigration::NOTICE_OPTION ) );
	}

	public function test_both_regions_filled_in_defaults_to_eu_for_a_non_us_store(): void {
		$this->configureStore( [ 'country' => 'DE', 'currency' => 'EUR', 'calc_taxes' => false ] );
		$this->setGatewaySettings(
			[
				'merchant_id_eu'        => 'live-mid-eu',
				'shared_secret_eu'      => 'live-secret-eu',
				'test_merchant_id_eu'   => 'test-mid-eu',
				'test_shared_secret_eu' => 'test-secret-eu',
				'merchant_id_us'        => 'live-mid-us',
				'shared_secret_us'      => 'live-secret-us',
				'test_merchant_id_us'   => 'test-mid-us',
				'test_shared_secret_us' => 'test-secret-us',
			]
		);

		( new CredentialsMigration() )->migrate();

		$settings = get_option( 'woocommerce_kco_settings' );

		$this->assertSame( 'live-mid-eu', $settings['merchant_id'] );
		$this->assertSame( 'live-secret-eu', $settings['shared_secret'] );

		$this->assertSame( 'eu', get_option( CredentialsMigration::NOTICE_OPTION ) );
	}

	public function test_neither_region_filled_in_does_nothing(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->setGatewaySettings( [ 'enabled' => 'yes' ] );

		( new CredentialsMigration() )->migrate();

		$settings = get_option( 'woocommerce_kco_settings' );

		$this->assertArrayNotHasKey( 'merchant_id', $settings );
		$this->assertFalse( get_option( CredentialsMigration::NOTICE_OPTION ) );
	}

	public function test_an_empty_settings_option_does_nothing(): void {
		// No `woocommerce_kco_settings` option at all, as on a fresh install.
		delete_option( 'woocommerce_kco_settings' );

		( new CredentialsMigration() )->migrate();

		$this->assertFalse( get_option( 'woocommerce_kco_settings' ) );
	}

	public function test_maybe_migrate_only_runs_once(): void {
		$this->haveLegacyEuStore();

		$migration = new CredentialsMigration();
		$migration->maybe_migrate();

		$this->assertSame( CredentialsMigration::TARGET_VERSION, get_option( CredentialsMigration::VERSION_OPTION ) );

		// Simulate an admin having since changed the migrated value by hand.
		$settings                = get_option( 'woocommerce_kco_settings' );
		$settings['merchant_id'] = 'manually-edited';
		update_option( 'woocommerce_kco_settings', $settings );

		$migration->maybe_migrate();

		$this->assertSame( 'manually-edited', get_option( 'woocommerce_kco_settings' )['merchant_id'] );
	}

	/**
	 * The stored version is what gates the migration, so a store that is already past
	 * this migration's target is left alone even on a first-ever call.
	 */
	public function test_a_store_already_past_the_target_version_is_skipped(): void {
		$this->haveLegacyEuStore();
		update_option( CredentialsMigration::VERSION_OPTION, '99.0.0' );

		( new CredentialsMigration() )->maybe_migrate();

		$this->assertArrayNotHasKey( 'merchant_id', get_option( 'woocommerce_kco_settings' ) );
		$this->assertSame( '99.0.0', get_option( CredentialsMigration::VERSION_OPTION ) );
	}

	/**
	 * A store below the target migrates, which is also what lets support re-run the
	 * migration by lowering the stored version rather than shipping code.
	 */
	public function test_a_store_below_the_target_version_migrates(): void {
		$this->haveLegacyEuStore();
		update_option( CredentialsMigration::VERSION_OPTION, '2.20.11' );

		( new CredentialsMigration() )->maybe_migrate();

		$this->assertSame( 'live-mid-eu', get_option( 'woocommerce_kco_settings' )['merchant_id'] );
		$this->assertSame( CredentialsMigration::TARGET_VERSION, get_option( CredentialsMigration::VERSION_OPTION ) );
	}

	/**
	 * Constructing the class only registers the notice hook. The bootstrap calls
	 * `maybe_migrate()` itself, so nothing writes to the database on construction.
	 */
	public function test_the_constructor_does_not_migrate(): void {
		$this->haveLegacyEuStore();

		new CredentialsMigration();

		$this->assertArrayNotHasKey( 'merchant_id', get_option( 'woocommerce_kco_settings' ) );
		$this->assertFalse( get_option( CredentialsMigration::VERSION_OPTION ) );
	}

	public function test_notice_is_rendered_when_due(): void {
		wp_set_current_user( 1 );
		update_option( CredentialsMigration::NOTICE_OPTION, 'us' );

		ob_start();
		( new CredentialsMigration() )->render_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'single set of credentials', $output );
		$this->assertStringContainsString( 'US', $output );
	}

	public function test_notice_is_not_rendered_when_not_due(): void {
		$this->assertFalse( get_option( CredentialsMigration::NOTICE_OPTION ) );

		ob_start();
		( new CredentialsMigration() )->render_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Dismissal is per user, via the same `dismissed_{$name}_notice` user meta
	 * WooCommerce's own generic notice-dismiss handler writes for every other
	 * notice in this plugin - not a global "seen by anyone" flag.
	 */
	public function test_notice_is_not_rendered_once_dismissed_by_the_current_user(): void {
		wp_set_current_user( 1 );
		update_option( CredentialsMigration::NOTICE_OPTION, 'eu' );
		update_user_meta( get_current_user_id(), 'dismissed_kco_credentials_migration_notice', true );

		ob_start();
		( new CredentialsMigration() )->render_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * The notice names the region the store was defaulted to, which is only useful to
	 * someone who can act on it. A shop's other users must not be shown a notice they
	 * have no capability to dismiss.
	 */
	public function test_the_notice_is_hidden_from_users_who_cannot_manage_woocommerce(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		update_option( CredentialsMigration::NOTICE_OPTION, 'us' );

		ob_start();
		( new CredentialsMigration() )->render_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Re-saving the gateway settings means the merchant has been on the settings screen
	 * and looked at the credentials, which is what the notice was asking them to do.
	 */
	public function test_a_settings_save_clears_the_notice(): void {
		$this->haveSavedGatewaySettings();
		update_option( CredentialsMigration::NOTICE_OPTION, 'us' );

		// Constructing it is what registers the hook, so this covers the wiring too.
		new CredentialsMigration();
		do_action( 'woocommerce_update_options_checkout_kco' );

		$this->assertFalse( get_option( CredentialsMigration::NOTICE_OPTION ) );
	}

	public function test_the_notice_stops_rendering_after_a_settings_save(): void {
		wp_set_current_user( 1 );
		$this->haveSavedGatewaySettings();
		update_option( CredentialsMigration::NOTICE_OPTION, 'eu' );

		$migration = new CredentialsMigration();
		do_action( 'woocommerce_update_options_checkout_kco' );

		ob_start();
		$migration->render_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * A settings option the other `woocommerce_update_options_checkout_kco` callbacks
	 * can read. `KCO_Settings_Saved` indexes it without guarding, and bails on a
	 * gateway that is switched off, which is all these tests need from it.
	 */
	private function haveSavedGatewaySettings(): void {
		$this->setGatewaySettings( [ 'enabled' => 'no' ] );
	}

	private function haveLegacyEuStore(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->setGatewaySettings(
			[
				'merchant_id_eu'   => 'live-mid-eu',
				'shared_secret_eu' => 'live-secret-eu',
			]
		);
	}
}

<?php

namespace Krokedil\KustomCheckout\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CredentialsMigration class.
 *
 * Copies a store's per-region (EU/US) API credentials into the single credential set
 * the settings page uses, keeping the old fields in place so a rollback to a previous
 * plugin version still finds working credentials.
 */
class CredentialsMigration {

	/**
	 * Option holding the plugin version whose migrations have all been run.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'kco_db_version';

	/**
	 * The plugin version this migration ships in. A stored version at or past this
	 * means the store is already migrated.
	 *
	 * @var string
	 */
	public const TARGET_VERSION = '2.21.0';

	/**
	 * Option set to 'yes' when the admin notice about defaulting to the EU
	 * credentials is due, and absent otherwise.
	 *
	 * @var string
	 */
	public const NOTICE_OPTION = 'kco_credentials_migration_notice';

	/**
	 * The credential field name stems, each combined with a region suffix
	 * ('eu'/'us') to form an actual settings key.
	 *
	 * @var string[]
	 */
	public const FIELD_STEMS = array( 'merchant_id', 'shared_secret', 'test_merchant_id', 'test_shared_secret' );

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'woocommerce_update_options_checkout_kco', array( $this, 'clear_notice' ) );
	}

	/**
	 * Runs the migration unless this store has already had it.
	 *
	 * Called explicitly from the bootstrap rather than hooked: it has to run before
	 * KCO_Credentials reads the settings, and a callback added to `plugins_loaded`
	 * from inside that same dispatch would not run until the next request.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		if ( version_compare( get_option( self::VERSION_OPTION, '0' ), self::TARGET_VERSION, '>=' ) ) {
			return;
		}

		$this->migrate();
		update_option( self::VERSION_OPTION, self::TARGET_VERSION );
	}

	/**
	 * Copies a store's existing EU/US credentials into the new single credential set.
	 *
	 * EU wins when both regions are filled in, and the store gets an admin notice
	 * saying so.
	 *
	 * @return void
	 */
	public function migrate() {
		$settings = get_option( 'woocommerce_kco_settings', array() );

		if ( empty( $settings ) ) {
			return;
		}

		$has_eu = $this->region_has_credentials( $settings, 'eu' );
		$has_us = $this->region_has_credentials( $settings, 'us' );

		if ( ! $has_eu && ! $has_us ) {
			return;
		}

		$this->copy_region( $settings, $has_eu ? 'eu' : 'us' );

		if ( $has_eu && $has_us ) {
			update_option( self::NOTICE_OPTION, 'yes' );
		}

		update_option( 'woocommerce_kco_settings', $settings );
	}

	/**
	 * Whether any of a region's credential fields (live or test) are filled in.
	 *
	 * @param array  $settings The gateway settings.
	 * @param string $region   Either 'eu' or 'us'.
	 * @return bool
	 */
	private function region_has_credentials( array $settings, $region ) {
		foreach ( self::FIELD_STEMS as $stem ) {
			if ( '' !== ( $settings[ "{$stem}_{$region}" ] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copies one region's credential fields into the new single credential fields.
	 *
	 * @param array  $settings The gateway settings, by reference.
	 * @param string $region   Either 'eu' or 'us'.
	 * @return void
	 */
	private function copy_region( array &$settings, $region ) {
		foreach ( self::FIELD_STEMS as $stem ) {
			$settings[ $stem ] = $settings[ "{$stem}_{$region}" ] ?? '';
		}
	}

	/**
	 * Renders the admin notice explaining that the EU credentials were kept.
	 *
	 * Dismissal is handled by WooCommerce's own generic `wc-hide-notice` handler,
	 * the same mechanism every other notice in this plugin relies on.
	 *
	 * @return void
	 */
	public function render_notice() {
		if ( ! get_option( self::NOTICE_OPTION ) ) {
			return;
		}

		// Only show this for users that can manage WooCommerce settings.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), 'dismissed_kco_credentials_migration_notice', true ) ) {
			return;
		}

		$message = __( 'Kustom Checkout for WooCommerce has been updated to use a single set of credentials for all regions. It looks like you had credentials configured for both Europe and the United States. We\'ve defaulted to the credentials previously entered for Europe. Your other credentials are still stored in the database, and will be available again if you roll back to a previous version.', 'klarna-checkout-for-woocommerce' );

		$dismiss_url = wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_credentials_migration' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' );
		?>
		<div class="kco-message notice woocommerce-message notice-info">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Clear the admin notice option when settings are re-saved since that should mean it's resolved.
	 *
	 * @return void
	 */
	public function clear_notice() {
		if ( get_option( self::NOTICE_OPTION ) ) {
			delete_option( self::NOTICE_OPTION );
		}
	}
}

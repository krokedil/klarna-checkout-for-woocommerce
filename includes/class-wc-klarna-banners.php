<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WC_Klarna_Banners' ) ) {
	/**
	 * Displays merchant information in the backend.
	 */
	class WC_Klarna_Banners {
		/**
		 * WC_Klarna_Banners constructor.
		 */
		public function __construct() {
			add_action( 'in_admin_header', array( $this, 'klarna_banner' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_css' ) );
		}

		/**
		 * Loads admin CSS file, has to be done here instead of gateway class, because
		 * it is required in all admin pages.
		 */
		public function load_admin_css() {
			wp_enqueue_style(
				'klarna_payments_admin',
				plugins_url( 'assets/css/klarna-checkout-admin.css?v=120320182110', KCO_WC_MAIN_FILE )
			);
		}

		/**
		 * Loads Klarna banner in admin pages.
		 */
		public function klarna_banner() {
			$kco_settings = get_option( 'woocommerce_kco_settings' );
			$show_banner  = false;

			// Always show banner in testmode.
			if ( isset( $kco_settings['testmode'] ) && 'yes' === $kco_settings['testmode'] ) {
				$show_banner = true;
			}

			// Go through countries and check if at least one has credentials configured.
			$countries   = array( 'eu', 'us' );
			$country_set = false;
			foreach ( $countries as $country ) {
				if ( '' !== $kco_settings[ 'merchant_id_' . $country ] && '' !== $kco_settings[ 'shared_secret_' . $country ] ) {
					$country_set = true;
				}
			}

			if ( ! $country_set ) {
				$show_banner = true;
			}

			if ( $show_banner ) {
				?>
				<div id="kb-spacer"></div>
				<div id="klarna-banner">
					<div id="kb-left">
						<h1>Go live</h1>
						<p>Before you can start to sell with Klarna you need your store to be approved by Klarna. When the installation is done and you are ready to go live, Klarna will need to verify the integration. Then you can go live with your store! If you wish to switch Klarna products then you’ll need the Klarna team to approve your store again.</p>
						<a class="kb-button"
						   href="https://www.klarna.com/international/business/woocommerce/?utm_source=woo-backend&utm_medium=referral&utm_campaign=woo&utm_content=banner"
						   target="_blank">Click here to go live with your store</a>
					</div>
					<div id="kb-right">
						<h1>Currently using Klarna?</h1>
						<p>Pay now, Pay later and Slice it. Klarna is entering a new world of smoooth. We would love for you to join us on the ride and to do so, you will need to upgrade your Klarna products to a new integration. You will then always get the latest features that Klarna develops and you’ll keep your current agreement along with your price settings.</p>
						<a class="kb-button"
						   href="https://hello.klarna.com/product-upgrade?utm_source=woo-backend&utm_medium=referral&utm_campaign=woo&utm_content=banner"
						   target="_blank">Click here to update your Klarna products</a>
					</div>
					<img id="kb-image"
						 src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/klarna_logo_white.png"
						 alt="Klarna logo" width="110"/>
				</div>
				<?php
			}
		}

		/**
		 * @param $parent_options
		 */
		public static function settings_sidebar( $parent_options ) {
			?>
			<img id="klarna-settings-logo"
				 src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/klarna_logo_black.png" width="200"/>

			<div id="klarna-wrapper">
				<div id="klarna-main">
					<?php echo $parent_options; ?>
				</div>
				<div id="klarna-sidebar">
					<div class="kb-sidebar-section">
						<img src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/icon_reminder.png" width="64"/>
						<h3>Go live</h3>
						<p>Before you can start to sell with Klarna you need your store to be approved by Klarna. When the installation is done and you are ready to go live, Klarna will need to verify the integration. Then you can go live with your store! If you wish to switch Klarna products then you’ll need the Klarna team to approve your store again.</p>
						<a class="kb-button"
						   href="https://www.klarna.com/international/business/woocommerce/?utm_source=woo-backend&utm_medium=referral&utm_campaign=woo&utm_content=kco"
						   target="_blank">Get Klarna to verify your store</a>
					</div>

					<div class="kb-sidebar-section">
						<div>
							<img src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/klarna_icons.png"
								 width="192"/>
						</div>
						<h3>Currently using Klarna?</h3>
						<p>Pay now, Pay later and Slice it. Klarna is entering a new world of smoooth. We would love for you to join us on the ride and to do so, you will need to upgrade your Klarna products to a new integration. You will then always get the latest features that Klarna develops and you’ll keep your current agreement along with your price settings.</p>
						<a class="kb-button"
						   href="https://hello.klarna.com/product-upgrade?utm_source=woo-backend&utm_medium=referral&utm_campaign=woo&utm_content=kco"
						   target="_blank">Update your Klarna product</a>
					</div>

					<p>*Note that if you are using Klarna checkout and want to start selling with Klarna payments then
						you
						need to sign up with Klarna.</p>
				</div>
			</div>

			<?php
		}
	}
}

new WC_Klarna_Banners();

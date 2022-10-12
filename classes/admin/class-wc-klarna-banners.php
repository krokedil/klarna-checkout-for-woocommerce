<?php
/**
 * Klarna banners file.
 *
 * @package  Klarna_Checkout/Classes
 */

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
			add_action( 'admin_notices', array( $this, 'klarna_banner' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_css' ) );
			add_action( 'wp_ajax_hide_klarna_banner', array( $this, 'hide_klarna_banner' ) );
			add_action( 'wp_ajax_nopriv_hide_klarna_banner', array( $this, 'hide_klarna_banner' ) );
		}

		/**
		 * Loads admin CSS file, has to be done here instead of gateway class, because
		 * it is required in all admin pages.
		 */
		public function load_admin_css() {
			wp_enqueue_style(
				'klarna_payments_admin',
				plugins_url( 'assets/css/klarna-checkout-admin.css?v=120320182113', KCO_WC_MAIN_FILE ),
				array(),
				KCO_WC_VERSION
			);
		}


		/**
		 * Loads Klarna banner in admin pages.
		 */
		public function klarna_banner() {
			global $pagenow;

			// Only display the banner on WP admin dashboard page or KCO settings page.
			$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( 'index.php' !== $pagenow && ( empty( $section ) && 'kco' !== $section ) ) {
				return;
			}

			$kco_settings = get_option( 'woocommerce_kco_settings' );
			$show_banner  = false;

			// Always show banner in testmode.
			if ( isset( $kco_settings['testmode'] ) && 'yes' === $kco_settings['testmode'] ) {
				$show_banner = true;
			}

			// Go through countries and check if at least one has credentials configured.
			$country_set = false;

			if ( is_array( $kco_settings ) ) { // Check for the country credentials only if the setting is present.
				$countries = array( 'eu', 'us' );
				foreach ( $countries as $country ) {
					if ( '' !== $kco_settings[ 'merchant_id_' . $country ] && '' !== $kco_settings[ 'shared_secret_' . $country ] ) {
						$country_set = true;
					}
				}
			}

			if ( ! $country_set ) {
				$show_banner = true;
			}

			if ( $show_banner && false === get_transient( 'klarna_hide_banner' ) ) {
				?>
				<div id="kb-spacer"></div>

				<div id="klarna-banner" class="kb-new-container">

					<!-- Left group -->
					<div class="kb-left-group">
						<div id="kb-left" class="kb-small-container">
							<h1 id="left-main-title" class="container-title">Go live.</h1>
							<p id="left-main-text" class="container-main-text">Before you can start to sell with Klarna you need your store to be approved by Klarna. When the installation is done and you are ready to go live, Klarna will need to verify the integration. Then you can go live with your store! If you wish to switch Klarna products then you’ll need the Klarna team to approve your store again.</p>
						</div>
					</div>

					<!-- Middle group -->
					<div class="kb-middle-group">
						<div id="kb-button-left-frame">
							<a id="kb-button-left" class="kb-dismiss kb-button"
								href="<?php echo esc_attr( self::get_go_live_url() ); ?>"
								target="_blank">Go live now
							</a>
						</div>
						<div id="kb-button-go-live-frame">
							<a id="kb-button-go-live" class="kb-button"
								href="<?php echo esc_attr( self::get_playground_credentials_url() ); ?>"
								target="_blank">Get playground credentials
							</a>
						</div>
					</div>

					<!-- Right group -->
					<div class="kb-right-group">
						<div id="klarna-logo-left-frame">
							<img id="klarna-logo-left" class="klarna-logo-img"
							src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/klarna_logo_black.png">
						</div>
					</div>

				</div>



					<span id="kb-dismiss-close-icon" class="kb-dismiss dashicons dashicons-dismiss"></span>

				<script type="text/javascript">

				jQuery(document).ready(function($){

					jQuery('.kb-dismiss').click(function(){
						jQuery('#klarna-banner').slideUp();
						jQuery.post(
							ajaxurl,
							{
								action		: 'hide_klarna_banner',
								_wpnonce	: '<?php echo wp_create_nonce( 'hide-klarna-banner' ); // phpcs:ignore?>',
							},
							function(response){
								console.log('Success hide KCO banner');
							}
						);
					});
				});
				</script>
				<?php
			}
		}

		/**
		 * Adds banners to the settings sidebar.
		 *
		 * @param array $parent_options The parent options.
		 */
		public static function settings_sidebar( $parent_options ) {
			?>
			<img id="klarna-settings-logo"
				src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/klarna_logo_black.png" width="200"/>

			<div id="klarna-wrapper">
				<div id="klarna-main">
					<?php echo $parent_options; // phpcs:ignore?>
				</div>

				<div id="krokdocs-sidebar">

					<div class="krokdocs-sidebar-section">
						<h1 id="krokdocs-sidebar-title">Get started</h1>
							<div class="krokdocs-sidebar-content">
								<p id="krokdocs-sidebar-main-text">
									<a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/" target="_blank">Documentation</a> <br/>
									<a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/" target="_blank">Plugin site</a>
								</p>
						<h1 id="krokdocs-sidebar-title">Support</h1>
								<p id="krokdocs-sidebar-main-text">
									If you have questions regarding a certain purchase you are welcome to contact <a href="http://klarna.com/merchant-support" target="_blank">Klarna</a>.
								</p>
								<p id="krokdocs-sidebar-main-text">
									If you have technical questions or questions regarding the configuration of the plugin you are welcome to contact <a href="https://www.krokedil.com/support" target="_blank">Krokedil</a>.
								</p>
							</div>

								<div id="krokdocs-sidebar-bottom-holder">
									<p id="krokdocs-sidebar-logo-follow-up-text">
										Developed by:
									</p>
									<img id="krokdocs-sidebar-krokedil-logo-right"
									src="https://krokedil.se/wp-content/uploads/2020/05/webb_logo_400px.png">
								</div>
					</div>
				</div>
			<div class="save-separator"></div>
			<?php
		}

		/**
		 * Hide Klarna banner in admin pages for.
		 */
		public function hide_klarna_banner() {
			set_transient( 'klarna_hide_banner', '1', 5 * DAY_IN_SECONDS );
			wp_send_json_success( 'Hide Klarna banner.' );
		}

		/**
		 * Return correct Go live url depending on the store country.
		 */
		public static function get_go_live_url() {
			// Set args for the URL.
			$country        = wc_get_base_location()['country'];
			$plugin         = 'klarna-checkout-for-woocommerce';
			$plugin_version = KCO_WC_VERSION;
			$wc_version     = defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
			$url_queries    = '?country=' . $country . '&products=kco&plugin=' . $plugin . '&pluginVersion=' . $plugin_version . '&platform=woocommerce&platformVersion=' . $wc_version;

			if ( 'US' !== $country ) {
				$url_base = 'https://eu.portal.klarna.com/signup/';
				$url      = $url_base . $url_queries;
			} else {
				$url_base = 'https://us.portal.klarna.com/signup/';
				$url      = $url_base . $url_queries;
			}
			return $url;
		}

		/**
		 * Returns the URL to the Klarna developers page for getting test credentials.
		 *
		 * @return string
		 */
		public static function get_playground_credentials_url() {
			return 'https://developers.klarna.com/documentation/testing-environment/';
		}
	}

	new WC_Klarna_Banners();
}

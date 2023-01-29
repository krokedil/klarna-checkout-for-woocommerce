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
			
			<style>
				#kco-support, #kco-addons {
					display: none;
				}

				.kco-tab-wrapper {
					display: flex;
					justify-content: space-between;
					align-items: center;
				}
			</style>

			<script>
				jQuery(document).ready(function($){
					$('#klarna-wrapper #tabs a').click(function(){
						$('#klarna-wrapper #tabs a').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						$(this).siblings().each(function(){
							$('#' + $(this).attr('href').replace('#', '')).hide();
						})
						$('#' + $(this).attr('href').replace('#', '')).show();
					});
				});
			</script>

			<div id="klarna-wrapper">
				<div id='tabs' class='nav-tab-wrapper'>
					<nav>
						<a class='nav-tab nav-tab-active' href="#kco-settings">Settings</a>
						<a class='nav-tab' href="#kco-support">Support</a>
						<a class='nav-tab' href="#kco-addons">Add-ons</a>
					</nav>
				</div>
				<div class='kco-tab-wrapper'>
				<div id='kco-settings'>
					<div id="klarna-main">
						<?php echo $parent_options; // phpcs:ignore?>
					</div>
				</div>
				<div id='kco-support'>
					<p>Before opening a support ticket, please make sure you have read the relevant plugin resources for a solution to your problem.</p>
					<ul>
						<li><a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/" target="_blank">General information</a></li>
						<li><a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/" target="_blank">Technical documentation</a></li>
						<li><a href="https://docs.krokedil.com/krokedil-general-support-info/" target="_blank">General support information</a></li>
					</ul>
					<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="">Klarna.</a></p>
					<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href="">Krokedil</a>, the plugin's developer.</p>
				</div>
				<style>
					.kco-addons-cards {
						display: flex;
						flex-wrap: wrap;
						justify-content: space-between;
						margin-bottom: 1.2em;
						max-width: 1080px;
					}
					.kco-addon-card {
						display: flex;
						justify-content: space-between;
						flex-direction: column;
						width: 30%;
						border: 1px solid #ccc;
						border-radius: 3px;
						background-color: white;
					}

					.kco-addon-card a[target="_blank"]::after {
						content: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAQElEQVR42qXKwQkAIAxDUUdxtO6/RBQkQZvSi8I/pL4BoGw/XPkh4XigPmsUgh0626AjRsgxHTkUThsG2T/sIlzdTsp52kSS1wAAAABJRU5ErkJggg==);
						margin: 0 3px 0 5px;
					}

					.kco-addon-card-image {
						width: 100%;
						background-size: cover;
						background-position: center;
						border-radius: 3px 3px 0 0;
					}
					.kco-addon-card-title {
						font-weight: bold;
						padding: 0 0.7em;
					}
					.kco-addon-card-description {
						padding: 1.1em;
						padding-top: 0;
					}

					.kco-addon-read-more {
						display: block;
						padding: 1.1em;
						padding-top: 0;
						border-bottom: 2px solid lightgray;
					}

					.kco-addon-card-action {
						display: flex;
						justify-content: space-between;
						align-items: center;
						padding: 0 1.1em;
						font-weight: bold;
					}
				</style>
				<div id='kco-addons'>
					<p>These are other plugins from Krokedil that work well with the plugin Klarna Checkout.</p>
					<div class='kco-addons-cards'>
						<div class="kco-addon-card">
							<img class="kco-addon-card-image" src="https://krokedil.com/wp-content/uploads/sites/3/2020/11/kom-chosen-960x544.jpg" alt="Klarna Order Management logo">
							<h3 class="kco-addon-card-title">Klarna Order Management</h3>
							<p class="kco-addon-card-description">Handle post purchase order management in Klarna's system directly from WooCommerce. This way you can save time and don't have to work in both systems simultaneously.</p>
							<a class="kco-addon-read-more" href="https://krokedil.com/product/klarna-order-management/" target="_blank">Read more</a>
							<p class="kco-addon-card-action"><span class='kco-addon-card-price'>Free</span><a class="install-now button" href="https://krokedil.com/product/krokedil-general-support-info/" target="_blank">Install now</a></p>
						</div>


						<div class="kco-addon-card">
							<img class="kco-addon-card-image" src="https://krokedil.com/wp-content/uploads/sites/3/2020/11/osm-chosen-960x544.jpg" alt="Krokedil logo">
							<h3 class="kco-addon-card-title">On-Site Messaging</h3>
							<p class="kco-addon-card-description">On-Site Messaging is easy and simple to integrate, providing tailored messaging ranging from generic banners to promote your partnership with Klarna and availability of financing to personalized credit promotion on product or cart pages.</p>
							<a class="kco-addon-read-more" href="https://krokedil.com/product/on-site-messaging-for-woocommerce/" target="_blank">Read more</a>
							<p class="kco-addon-card-action"><span class='kco-addon-card-price'>Free</span><a class="install-now button"  href="https://krokedil.com/product/klarna-payments-for-woocommerce/" target="_blank">Install now</a></p>
						</div>
						
						<div class="kco-addon-card">
							<img class="kco-addon-card-image" src="https://krokedil.com/wp-content/uploads/sites/3/2021/08/featured-partial-delivery-960x544.jpg" alt="Partial Delivery for WooCommerce logo">
							<h3 class="kco-addon-card-title">Partial Delivery for WooCommerce</h3>
							<p class="kco-addon-card-description">Partial Delivery for WooCommerce enables merchants to ship parts of an order. Via a logical interface you are able to send one or several items in each delivery.</p>
							<a class="kco-addon-read-more" href="https://krokedil.com/product/partial-delivery-for-woocommerce/" target="_blank">Read more</a>
							<p class="kco-addon-card-action"><span class='kco-addon-card-price'>250 SEK/month</span><a class="install-now button" href="https://krokedil.com/product/klarna-checkout-for-woocommerce/" target="_blank">Buy Now</a></p>
						</div>
						
					</div>
				</div>

				<div id="krokdocs-sidebar">
					<div class="krokdocs-sidebar-section">
						<h1 id="krokdocs-sidebar-title">Plugin resources</h1>
							<div class="krokdocs-sidebar-content">
									<ul>
									<li><a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/" target="_blank">General information</a></li>
									<li><a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/" target="_blank">Technical documentation</a></li>
									<li><a href="">Support</a></li>
									<li><a href="">Add-ons</a></li>
									</ul>
						<h1 id="krokdocs-sidebar-title">Additional resources</h1>
									<ul>
										<li><a href="https://docs.krokedil.com/krokedil-general-support-info/" target="_blank">General support information</a></li>
										<li><a href="https://krokedil.com/products/" target="_blank">Other Krokedil plugins</a></li>
										<li><a href="https://krokedil.com/knowledge/" target="_blank">Krokedil blog</a></li>
									</ul>
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

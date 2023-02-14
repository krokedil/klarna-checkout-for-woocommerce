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

		public static function get_plugin_logs( $plugin_name ) {
			$logs = array();
			foreach ( WC_Admin_Status::scan_log_files() as $log => $path ) {
				if ( strpos( $log, $plugin_name ) !== false ) {
					$timestamp = filemtime( WC_LOG_DIR . $path );
					$date      = sprintf(
						/* translators: 1: last access date 2: last access time 3: last access timezone abbreviation */
						__( '%1$s at %2$s %3$s', 'woocommerce' ),
						wp_date( wc_date_format(), $timestamp ),
						wp_date( wc_time_format(), $timestamp ),
						wp_date( 'T', $timestamp )
					);

					$logs[ $date ] = $path;
				}
			}

			return $logs;
		}

		/**
		 * Adds banners to the settings sidebar.
		 *
		 * @param array $parent_options The parent options.
		 */
		public static function settings_sidebar( $parent_options ) {

			// $kco_log = array();
			// foreach ( WC_Admin_Status::scan_log_files() as $log => $path ) {
			// if ( strpos( $log, 'klarna-checkout-for-woocommerce' ) !== false ) {
			// $timestamp = filemtime( WC_LOG_DIR . $path );
			// $date      = sprintf(
			// * translators: 1: last access date 2: last access time 3: last access timezone abbreviation */
			// __( '%1$s at %2$s %3$s', 'woocommerce' ),
			// wp_date( wc_date_format(), $timestamp ),
			// wp_date( wc_time_format(), $timestamp ),
			// wp_date( 'T', $timestamp )
			// );

			// $kco_log[ $date ] = $path;
			// }
			// }

			$logs = array(
				'kco'   => self::get_plugin_logs( 'klarna-checkout-for-woocommerce' ),
				'kom'   => self::get_plugin_logs( 'klarna-order-management-for-woocommerce' ),
				'fatal' => self::get_plugin_logs( 'fatal-errors' ),
			);

			?>
			<div style="position: absolute; top: -9999px; left: -9999px;">
			<?php
				$system_report = new WC_Admin_Status();
				echo $system_report->status_report();
			?>
			</div>
			<img id="klarna-settings-logo"
				src="<?php echo esc_url( KCO_WC_PLUGIN_URL ); ?>/assets/img/klarna_logo_black.png" width="200"/>
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

					$(window).on('hashchange', function() {
						$('#klarna-wrapper #tabs a').removeClass('nav-tab-active');
						$('#klarna-wrapper #tabs a[href="' + window.location.hash + '"]').addClass('nav-tab-active');
						$('#klarna-wrapper #tabs a[href="' + window.location.hash + '"]').siblings().each(function(){
							$('#' + $(this).attr('href').replace('#', '')).hide();
						})
						$('#' + window.location.hash.replace('#', '')).show();
					})
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
						<li><a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">General information</a></li>
						<li><a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">Technical documentation</a></li>
						<li><a href="https://docs.krokedil.com/krokedil-general-support-info/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">General support information</a></li>
					</ul>
					<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="">Klarna.</a></p>
					<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href="">Krokedil</a>, the plugin's developer.</p>
					<style>
						.support-form input {
							display: block;
						}

						.support-form .required {
							font-size: 0.7em;
							color: black;
						}

						.support-form textarea#issue {
							width: 100%;
						}

						.support-form .system-report-wrapper, .log-wrapper {
							display: flex;
							align-items: center;
							flex-wrap: wrap;
						}

						.system-report-wrapper a, .log-wrapper a {
							margin-left: 2em;
						}

						.system-report-wrapper a::after, .log-wrapper a::after {
							display: inline-block;
							content: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23555%22%2F%3E%3C%2Fsvg%3E");
							transform: scale(0.7) translateY(30%);
							filter: sepia(100%) hue-rotate(190deg) saturate(500%);
						}
						
						.system-report-wrapper a:focus, .log-wrapper a:focus {
							outline: none;
							border: none;
							box-shadow: none;
						}

						.system-report-wrapper textarea, .log-wrapper textarea {
							display: none;
							flex-basis: 100%;
							font-family: monospace;
							width: 100%;
							margin: 1em 0;
							height: 150px;
							padding-left: 10px;
							border-radius: 0;
							resize: none;
							font-size: 12px;
							line-height: 20px;
							outline: 0;
						}

						.woocommerce-log {
							display: flex;
							flex-wrap: wrap;
							align-items: center;
						}

						.woocommerce-log h3 {
							flex-basis: 100%;
						}

						.woocommerce-log .select2-container {
							max-width: 60%;
							min-width: 60%;
						}

						.woocommerce-log a {
							margin-left: 2em;
						}

					</style>
					<div class="support-form">
						<h1>Technical support request form</h1>
						<h2>E-mail <span class="required">(required)</span></h2>
						<p>
							<label for="email">Replies will be sent to this address, please check for typos.</label>
							<input id="email" name="email" type="email" size="50" required>
						</p>
						<h2>Subject <span class="required">(required)</span></h2>
						<p>
							<label for="subject">Summarize your question in a few words.</label>
							<input id="subject" name="subject" type="text" size="50" required>
						</p>
						<h2>How can we help? <span class="required">(required)</span></h2>
						<p>
							<label for="issue">Describe the issue you are having as detailed as possible.</label>
							<textarea id="issue" name="issue" rows="4" cols="70" ></textarea>
						</p>
						<h2>WooCommerce system status report <span class="woocommerce-help-tip"></span></h2>
						<p>This report contains information about your website that is very useful for troubleshooting issues.</p>
						<script>
							jQuery(function($) {
								$( '.system-report-wrapper a' ).on( 'click', function() {
									/* Refer to "wp-content/plugins/woocommerce/assets/js/admin/system-status.js:generateReport()" */
									let report = '';
									$( '.wc_status_table thead, .wc_status_table tbody' ).each( function() {
										if ( $( this ).is( 'thead' ) ) {
											var label = $( this ).find( 'th:eq(0)' ).data( 'exportLabel' ) || $( this ).text();
											report = report + '\n### ' + label.trim() + ' ###\n\n';
										} else {
											$( 'tr', $( this ) ).each( function() {
												var label       = $( this ).find( 'td:eq(0)' ).data( 'exportLabel' ) || $( this ).find( 'td:eq(0)' ).text();
												var the_name    = label.trim().replace( /(<([^>]+)>)/ig, '' ); // Remove HTML.
												// Find value
												var $value_html = $( this ).find( 'td:eq(2)' ).clone();
												$value_html.find( '.private' ).remove();
												$value_html.find( '.dashicons-yes' ).replaceWith( '&#10004;' );
												$value_html.find( '.dashicons-no-alt, .dashicons-warning' ).replaceWith( '&#10060;' );
												// Format value
												var the_value   = $value_html.text().trim();
												var value_array = the_value.split( ', ' );
												if ( value_array.length > 1 ) {
													// If value have a list of plugins ','.
													// Split to add new line.
													var temp_line ='';
													$.each( value_array, function( key, line ) {
														temp_line = temp_line + line + '\n';
													});
													the_value = temp_line;
												}
												report = report + '' + the_name + ': ' + the_value + '\n';
											});
										}
									})

									$('.system-report-content').val(report);
								})
								$('.system-report-action').click(function(e) {
									$('.system-report-content').toggle({duration: 250});
									if ($(this).text() === 'View report') {
										$(this).text('Hide report');
									} else {
										$(this).text('View report');
									}
									
									e.preventDefault();
								})

								$('.view-log').click(function(e) {
									$(this).siblings('.log-content').toggle({duration: 250});
									if ($(this).text() === 'View log') {
										$(this).text('Hide log');
									} else {
										$(this).text('View log');
									}
									
									e.preventDefault();
								})

							})
						</script>
						<div class="system-report-wrapper">
							<input type="checkbox" id="system-report" checked>
							<!-- TODO: Handle the checkbox event. -->
							<label id="system-report">Attach this store's WooCommerce system status report.</label>
							<a href="#" class="system-report-action">View report</a>
							<textarea class="system-report-content" readonly></textarea>
						</div>
						<h2>WooCommerce logs <span class="woocommerce-help-tip"></span></h2>
						<p>
							If you have logging enabled, providing relevant logs can be very useful for troubleshooting (e.g., issue with a specific order).
						</p>
						<div class="woocommerce-log">
							<!-- TODO: Include the WC_AJAX::endpoint in an enqueued script. -->
							<div class="log-wrapper">
							<h3>Klarna Checkout</h3>
							<!-- TODO: Handle sitution where there are no log entries: show no logs available text. -->
							<select class="kco-log-option wc-enhanced-select" name="kco-log">
								<?php foreach ( $logs['kco'] as $date => $path ) : ?>
								<option name="kco-log" value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
								<?php endforeach; ?>
							</select>
							<a class="view-log">View log</a>
							<textarea class="log-content" readonly></textarea>
							</div>
							<div class="log-wrapper">
							<h3>Klarna Order Management</h3>
							<!-- TODO: Handle sitution where there are no log entries: show no logs available text. -->
							<select class="kco-log-option wc-enhanced-select" name="kco-log">
								<?php foreach ( $logs['kom'] as $date => $path ) : ?>
								<option name="kco-log" value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
								<?php endforeach; ?>
							</select>
							<a class="view-log">View log</a>
							<textarea class="log-content" readonly></textarea>
							</div>
							<div class="log-wrapper">
							<h3>Fatal error log</h3>
							<!-- TODO: Handle sitution where there are no log entries: show no logs available text. -->
							<select class="kco-log-option wc-enhanced-select" name="kco-log">
								<?php foreach ( $logs['fatal'] as $date => $path ) : ?>
								<option name="kco-log" value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
								<?php endforeach; ?>
							</select>
							<a class="view-log">View log</a>
							<textarea class="log-content" readonly></textarea>
							</div>
						</div>
					</div>
				</div>
				
				<div id="krokdocs-sidebar">
					<div class="krokdocs-sidebar-section">
						<h1 id="krokdocs-sidebar-title">Plugin resources</h1>
							<div class="krokdocs-sidebar-content">
									<ul>
									<li><a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">General information</a></li>
									<li><a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">Technical documentation</a></li>
									<li><a href="https://krokedil.montazar.eu.ngrok.io/wp-admin/admin.php?page=wc-settings&tab=checkout&section=kco#kco-support">Support</a></li>
									<li><a href="https://krokedil.montazar.eu.ngrok.io/wp-admin/admin.php?page=wc-settings&tab=checkout&section=kco#kco-addons">Add-ons</a></li>
									</ul>
						<h1 id="krokdocs-sidebar-title">Additional resources</h1>
									<ul>
										<li><a href="https://docs.krokedil.com/krokedil-general-support-info/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">General support information</a></li>
										<li><a href="https://krokedil.com/products/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">Other Krokedil plugins</a></li>
										<li><a href="https://krokedil.com/knowledge/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar" target="_blank">Krokedil blog</a></li>
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

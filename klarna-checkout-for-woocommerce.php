<?php // phpcs:ignore
/**
 * Plugin Name: Klarna Checkout for WooCommerce
 * Plugin URI: https://krokedil.com/klarna/
 * Description: Klarna Checkout payment gateway for WooCommerce.
 * Author: Krokedil
 * Author URI: https://krokedil.com/
 * Version: 2.0.13
 * Text Domain: klarna-checkout-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 3.2.0
 * WC tested up to: 4.0.1
 *
 * Copyright (c) 2017-2020 Krokedil
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'KCO_WC_VERSION', '2.0.13' );
define( 'KCO_WC_MIN_PHP_VER', '5.6.0' );
define( 'KCO_WC_MIN_WC_VER', '3.9.0' );
define( 'KCO_WC_MAIN_FILE', __FILE__ );
define( 'KCO_WC_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'KCO_WC_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'KROKEDIL_LOGGER_GATEWAY', 'kco' );
if ( ! class_exists( 'KCO' ) ) {
	/**
	 * Class KCO
	 */
	class KCO {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;

		/**
		 * Reference to API class.
		 *
		 * @var $api
		 */
		public $api;

		/**
		 * Reference to merchant URLs class.
		 *
		 * @var $merchant_urls
		 */
		public $merchant_urls;

		/**
		 * Reference to order lines class.
		 *
		 * @var $order_lines
		 */
		public $order_lines;

		/**
		 * Reference to credentials class.
		 *
		 * @var $credentials
		 */
		public $credentials;

		/**
		 * Reference to logging class.
		 *
		 * @var $log
		 */
		public $logger;

		/**
		 * Reference to order lines from order class.
		 *
		 * @var $log
		 */
		public $order_lines_from_order;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Notices (array)
		 *
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_action( 'wp_head', array( $this, 'check_if_external_payment' ) );

			// "Fallback" redirection to proper order thank you page if we have one.
			add_action( 'wp_head', array( $this, 'redirect_to_thankyou' ) );

			// Add quantity button in woocommerce_order_review() function.
			add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'add_quantity_field' ), 10, 3 );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Init the gateway itself.
			$this->init_gateways();
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'klarna-checkout-for-woocommerce' ) . '</a>',
				'<a href="http://krokedil.se/">' . __( 'Support', 'klarna-checkout-for-woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$section_slug = 'kco';

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Classes.
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-ajax.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-api-callbacks.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-api.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-credentials.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-fields.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-gateway.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-gdpr.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-logger.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-status.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-subscription.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/class-kco-templates.php';

			// Admin includes.
			if ( is_admin() ) {
				include_once KCO_WC_PLUGIN_PATH . '/classes/admin/class-kco-admin-notices.php';
				include_once KCO_WC_PLUGIN_PATH . '/classes/admin/class-klarna-for-woocommerce-addons.php';
				include_once KCO_WC_PLUGIN_PATH . '/classes/admin/class-wc-klarna-banners.php';
			}

			// Requests.
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/class-kco-request.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/checkout/post/class-kco-request-create-recurring.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/checkout/post/class-kco-request-create.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/checkout/post/class-kco-request-update.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/checkout/get/class-kco-request-retrieve.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/order-management/get/class-kco-request-get-order.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/order-management/post/class-kco-request-acknowledge-order.php';

			// Helpers.
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-merchant-urls.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-request-cart.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-request-countries.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-request-merchant-data.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-request-options.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-request-order.php';
			include_once KCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-kco-request-shipping-options.php';

			// Includes.
			include_once KCO_WC_PLUGIN_PATH . '/includes/kco-functions.php';

			// Set class variables.
			$this->credentials   = new KCO_Credentials();
			$this->merchant_urls = new KCO_Merchant_URLs();
			$this->logger        = new KCO_Logger();
			$this->api           = new KCO_API();

			load_plugin_textdomain( 'klarna-checkout-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @param  array $methods Payment methods.
		 *
		 * @return array $methods Payment methods.
		 * @since  1.0.0
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'KCO_Gateway';

			return $methods;
		}


		/**
		 * Filters cart item quantity output.
		 *
		 * @param string $output HTML output.
		 * @param array  $cart_item Cart item.
		 * @param string $cart_item_key Cart item key.
		 *
		 * @return string $output
		 */
		public function add_quantity_field( $output, $cart_item, $cart_item_key ) {
			$settings    = get_option( 'woocommerce_kco_settings' );
			$show_fields = isset( $settings['quantity_fields'] ) ? $settings['quantity_fields'] : 'yes';
			if ( 'yes' !== $show_fields ) {
				return $output;
			}

			if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
				foreach ( WC()->cart->get_cart() as $cart_key => $cart_value ) {
					if ( $cart_key === $cart_item_key ) {
						$_product = $cart_item['data'];

						if ( $_product->is_sold_individually() ) {
							$return_value = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_key );
						} else {
							$return_value = woocommerce_quantity_input(
								array(
									'input_name'  => 'cart[' . $cart_key . '][qty]',
									'input_value' => $cart_item['quantity'],
									'max_value'   => $_product->backorders_allowed() ? '' : $_product->get_stock_quantity(),
									'min_value'   => '1',
								),
								$_product,
								false
							);
						}

						$output = $return_value;
					}
				}
			}

			return $output;
		}

		/**
		 * Redirects the customer to the proper thank you page.
		 *
		 * @return void
		 */
		public function redirect_to_thankyou() {
			if ( isset( $_GET['kco_confirm'] ) && isset( $_GET['kco_order_id'] ) ) { // phpcs:ignore
				$klarna_order_id = sanitize_text_field( wp_unslash( $_GET['kco_order_id'] ) ); // phpcs:ignore
				KCO_Logger::log( $klarna_order_id . ': Confirmation endpoint hit for order.' );

				// Find relevant order in Woo.
				$query_args = array(
					'fields'      => 'ids',
					'post_type'   => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'meta_key'    => '_wc_klarna_order_id', // phpcs:ignore
					'meta_value'  => $klarna_order_id, // phpcs:ignore
				);

				$orders = get_posts( $query_args );
				if ( ! $orders ) {
					// If no order is found, bail. @TODO Add a fallback order creation here?
					wc_add_notice( __( 'Something went wrong in the checkout process. Please contact the store.', 'error' ) );
					return;
				}
				$order_id = $orders[0];
				$order    = wc_get_order( $order_id );
				// Confirm, redirect and exit.
				KCO_Logger::log( $klarna_order_id . ': Confirm the klarna order from the confirmation page.' );
				kco_confirm_klarna_order( $order_id, $klarna_order_id );
				kco_unset_sessions();
				header( 'Location:' . $order->get_checkout_order_received_url() );
				exit;
			}
		}

		/**
		 * Checks if we have an external payment method on page load.
		 *
		 * @return void
		 */
		public function check_if_external_payment() {
			if ( isset( $_GET['kco-external-payment'] ) ) { // phpcs:ignore
				$this->run_kepm( $_GET ); // phpcs:ignore
			}
		}

		/**
		 * Initiates a Klarna External Payment Method payment.
		 *
		 * @param array $get_data The get data from the server request.
		 * @return void
		 */
		public function run_kepm( $get_data ) {
			$epm             = $get_data['kco-external-payment'];
			$order_id        = ( isset( $get_data['order_id'] ) ) ? $get_data['order_id'] : '';
			$klarna_order_id = ( isset( $get_data['kco_order_id'] ) ) ? $get_data['kco_order_id'] : '';
			$order           = wc_get_order( $order_id );
			// Check if we have a KCO order id.
			if ( ! empty( $klarna_order_id ) ) {
				// Do a database lookup for the WooCommerce order.
				$query_args = array(
					'fields'      => 'ids',
					'post_type'   => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'meta_key'    => '_wc_klarna_order_id', // phpcs:ignore
					'meta_value'  => $klarna_order_id, // phpcs:ignore
				);
				$orders     = get_posts( $query_args );
				// Set the order from the first order id returned.
				if ( ! empty( $orders ) ) {
					$order_id = $orders[0];
					$order    = wc_get_order( $order_id );
				}
			}
			// Check if we have a order.
			if ( ! $order ) {
				wc_print_notice( __( 'Failed getting the order for the external payment.', 'klarna-checkout-for-woocommerce' ), 'error' );
				return;
			}
			$payment_methods = WC()->payment_gateways->get_available_payment_gateways();
			// Check if the payment method is available.
			if ( ! isset( $payment_methods[ $epm ] ) ) {
				wc_print_notice( __( 'Failed to find the payment method for the external payment.', 'klarna-checkout-for-woocommerce' ), 'error' );
				return;
			}
			$result = $payment_methods[ $epm ]->process_payment( $order_id );
			// Check if the result is good.
			if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
				wc_print_notice( __( 'Something went wrong with the external payment. Please try again', 'klarna-checkout-for-woocommerce' ), 'error' );
				return;
			}
			// Everything is fine, redirect to the URL specified by the gateway.
			WC()->session->set( 'chosen_payment_method', $epm );
			$order->set_payment_method( $payment_methods[ $epm ] );
			$order->save();
			wp_redirect( $result['redirect'] ); // phpcs:ignore
			exit;
		}

	}
	KCO::get_instance();
}

/**
 * Main instance KCO WooCommerce.
 *
 * Returns the main instance of KCO.
 *
 * @return KCO
 */
function KCO_WC() { // phpcs:ignore
	return KCO::get_instance();
}

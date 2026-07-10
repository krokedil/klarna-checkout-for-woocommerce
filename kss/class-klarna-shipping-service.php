<?php // phpcs:ignore
/**
 * Bundled Kustom Shipping Assistant loader.
 *
 * This is the previously standalone "Kustom Shipping Assistant for WooCommerce"
 * plugin, integrated into Kustom Checkout as a subfolder. The standalone plugin
 * header, the Kernl update checker and the self-instantiation have been removed.
 * Kustom Checkout instantiates this class from its bootstrap when the feature is
 * enabled in the settings and the standalone plugin is not active.
 *
 * @package Klarna_Checkout/KSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants (guarded in case the standalone plugin already defined them).
if ( ! defined( 'KLARNA_KSS_VERSION' ) ) {
	define( 'KLARNA_KSS_VERSION', '1.3.1' );
}
if ( ! defined( 'KLARNA_KSS_URL' ) ) {
	define( 'KLARNA_KSS_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
}
if ( ! defined( 'KLARNA_KSS_PATH' ) ) {
	define( 'KLARNA_KSS_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! class_exists( 'Klarna_Shipping_Service_For_WooCommerce' ) ) {
	/**
	 * Kustom Shipping Assistant main class.
	 */
	class Klarna_Shipping_Service_For_WooCommerce {

		/**
		 * Class constructor.
		 *
		 * Kustom Checkout instantiates this on the `plugins_loaded` action, so the
		 * files are included and hooks are registered immediately instead of being
		 * deferred to a second `plugins_loaded` callback as in the standalone plugin.
		 */
		public function __construct() {
			$this->include_files();

			add_action( 'kco_wc_process_payment', array( $this, 'add_shipping_details_to_order' ), 10, 2 );
			add_action( 'kco_update_shipping_data', array( $this, 'clear_shipping_and_recalculate' ) );
			add_filter( 'kco_wc_chosen_shipping_method', array( $this, 'set_shipping_method' ) );
			add_filter( 'kco_check_if_needs_payment', array( $this, 'change_check_if_needs_payment' ) );
		}

		/**
		 * Include the plugin files.
		 *
		 * @return void
		 */
		public function include_files() {
			// Include classes.
			if ( is_admin() ) {
				include_once KLARNA_KSS_PATH . '/classes/class-kss-admin-notices.php';
			}
			include_once KLARNA_KSS_PATH . '/classes/class-kss-cart-page.php';
			include_once KLARNA_KSS_PATH . '/classes/class-kss-shipping-method.php';
			include_once KLARNA_KSS_PATH . '/classes/class-kss-order-lines.php';
			include_once KLARNA_KSS_PATH . '/classes/class-kss-free-orders.php';
			include_once KLARNA_KSS_PATH . '/classes/class-kss-edit-klarna-order.php';
			include_once KLARNA_KSS_PATH . '/classes/class-kss-compare-totals.php';
		}

		/**
		 * Returns the shipping method ID.
		 *
		 * @param array $chosen_shipping_methods WooCommerce shipping method ID.
		 * @return array The shipping method ID for this shipping method.
		 */
		public function set_shipping_method( $chosen_shipping_methods ) {
			$shipping_methods = WC()->shipping->get_shipping_methods();
			// Only do this if we have Kustom KSS active on the store, and the returned shipping method is NOT a real WooCommerce shipping method.
			if ( isset( $shipping_methods['klarna_kss'] ) && ! isset( $shipping_methods[ $chosen_shipping_methods[0] ] ) ) {
				return array( 'klarna_kss' );
			}
			return $chosen_shipping_methods;
		}

		/**
		 * Adds the shipping details from KSS to the WooCommerce order.
		 *
		 * @param int   $order_id The WooCommerce order id.
		 * @param array $klarna_order The Kustom order.
		 * @return void
		 */
		public function add_shipping_details_to_order( $order_id, $klarna_order ) {
			if ( isset( $klarna_order['selected_shipping_option'] ) ) {
				$order = wc_get_order( $order_id );

				$shipping_details = $klarna_order['selected_shipping_option'];
				if ( isset( $shipping_details['tms_reference'] ) ) {
					$order->update_meta_data( '_kco_kss_reference', $shipping_details['tms_reference'] );
				}

				$order->update_meta_data( '_kco_kss_data', wp_json_encode( $shipping_details, JSON_UNESCAPED_UNICODE ) );
				$order->save();
				WC()->session->__unset( 'kco_kss_enabled' );
			}
		}

		/**
		 * Clears the shipping calculations to prevent errors.
		 *
		 * @return void
		 */
		public function clear_shipping_and_recalculate() {
			if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
				WC()->session->set( 'kco_kss_enabled', true );
				$packages = WC()->cart->get_shipping_packages();
				foreach ( $packages as $package_key => $package ) {
					$session_key = 'shipping_for_package_' . $package_key;
					WC()->session->__unset( $session_key );
				}
			} elseif ( null !== WC()->session->get( 'kco_kss_enabled' ) ) {
					WC()->session->__unset( 'kco_kss_enabled' );
					$packages = WC()->cart->get_shipping_packages();
				foreach ( $packages as $package_key => $package ) {
					$session_key = 'shipping_for_package_' . $package_key;
					WC()->session->__unset( $session_key );
				}
			}
		}

		/**
		 * Make sure that KCO iframe is displayed in checkout even if order total is 0.
		 * This is needed so we can save the tms data to the Woo order.
		 *
		 * @param bool $bool Wether or not the plugin should check if KCO checkout should be displayed. Defaults to true.
		 *
		 * @return bool
		 */
		public function change_check_if_needs_payment( $bool ) {
			// Allways return false. We want to display the KCO iframe even if order total is 0.
			return false;
		}
	}
}

<?php // phpcs:ignore
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Klarna_For_WooCommerce_Addons' ) ) {
	/**
	 * Klarna_Checkout_For_WooCommerce_Addons class.
	 *
	 * Handles Klarna Checkout addons page.
	 */
	class Klarna_For_WooCommerce_Addons {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;

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
		 * Klarna_Checkout_For_WooCommerce_Confirmation constructor.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_menu' ), 100 );
		}

		/**
		 * Add the Addons menu to WooCommerce
		 **/
		public function add_menu() {
			add_submenu_page( 'woocommerce', __( 'Klarna Add-ons', 'klarna-checkout-for-woocommerce' ), __( 'Klarna Add-ons', 'klarna-checkout-for-woocommerce' ), 'manage_woocommerce', 'checkout-addons', array( $this, 'options_page' ) );
		}

		/**
		 * Add the Addons options page to WooCommerce.
		 **/
		public function options_page() {
			$tab     = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$this->add_page_tabs( $tab );
			if ( 'settings' === $tab ) {
				do_action( 'klarna_addons_settings_tab', $section );
				echo '<p>' . esc_html_e( 'Please install an add-on to be able to see the settings.', 'klarna-checkout-for-woocommerce' ) . '</p>';
			}
		}

		/**
		 * Adds tabs to the Addons page.
		 *
		 * @param string $current Wich tab is to be selected.
		 * @return void
		 */
		public function add_page_tabs( $current = 'settings' ) {
			if ( empty( $current ) ) {
				$current = 'settings';
			}
			$tabs = array(
				'settings' => __( 'Settings', 'klarna-checkout-for-woocommerce' ),
			);
			$html = '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $tab => $name ) {
				$class = ( $tab === $current ) ? 'nav-tab-active' : '';
				$html .= '<a class="nav-tab ' . $class . '" href="?page=checkout-addons&tab=' . $tab . '">' . $name . '</a>';
			}
			$html .= '</h2>';
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
Klarna_For_WooCommerce_Addons::get_instance();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Returns error messages depending on
 *
 * @class    Collector_Checkout_Admin_Notices
 * @version  1.0
 * @package  Collector_Checkout/Classes
 * @category Class
 * @author   Krokedil
 */
class Klarna_Checkout_For_WooCommerce_Admin_Notices {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Checks if KCO gateway is enabled.
	 *
	 * @var $enabled
	 */
	protected $enabled;

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
	 * Collector_Checkout_Admin_Notices constructor.
	 */
	public function __construct() {
		$settings      = get_option( 'woocommerce_kco_settings' );
		$this->enabled = $settings['enabled'];

		add_action( 'admin_init', array( $this, 'check_settings' ) );
	}

	/**
	 * Checks the settings.
	 */
	public function check_settings() {
		if ( ! empty( $_POST ) ) {
			add_action( 'woocommerce_settings_saved', array( $this, 'check_terms' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'check_terms' ) );
			add_action( 'admin_notices', array( $this, 'check_https' ) );
		}
	}

	/**
	 * Check if terms page is set.
	 */
	public function check_terms() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		// Terms page.
		if ( ! wc_get_page_id( 'terms' ) || wc_get_page_id( 'terms' ) < 0 ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html( __( 'You need to specify a terms page in WooCommerce Settings to be able to use Klarna Checkout.', 'klarna-checkout-for-woocommerce' ) ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Check if https is configured.
	 */
	public function check_https() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! is_ssl() ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . esc_html( __( 'You need to enable and configure https to be able to use Klarna Checkout.', 'klarna-checkout-for-woocommerce' ) ) . '</p>';
			echo '</div>';
		}
	}
}

Klarna_Checkout_For_WooCommerce_Admin_Notices::get_instance();

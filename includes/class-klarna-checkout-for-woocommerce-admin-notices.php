<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Returns error messages depending on
 *
 * @class    Klarna_Checkout_Admin_Notices
 * @version  1.0
 * @package  Klarna_Checkout/Classes
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
	 * Klarna_Checkout_Admin_Notices constructor.
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
			add_action( 'woocommerce_settings_saved', array( $this, 'check_account' ) );
			add_action( 'admin_notices', array( $this, 'check_autoptimize' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'check_https' ) );
			add_action( 'admin_notices', array( $this, 'check_terms' ) );
			add_action( 'admin_notices', array( $this, 'check_account' ) );
			add_action( 'admin_notices', array( $this, 'check_autoptimize' ) );
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

	/**
	 * Check how account creation is set.
	 */
	public function check_account() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		// Account page - username.
		if ( 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) && 'no' === get_option( 'woocommerce_registration_generate_username' ) ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . sprintf( __( 'You need to tick the checkbox <i>When creating an account, automatically generate a username from the customer\'s email address</i> when having the <i>Allow customers to create an account during checkout</i> setting activated. This can be changed in the <a href="%s">Accounts & Privacy tab</a>.', 'klarna-checkout-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=account' ) ) . '</p>';
			echo '</div>';
		}
		// Account page - password.
		if ( 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) && 'no' === get_option( 'woocommerce_registration_generate_password' ) ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . sprintf( __( 'You need to tick the checkbox <i>When creating an account, automatically generate an account password</i> when having the <i>Allow customers to create an account during checkout</i> setting activated. This can be changed in the <a href="%s">Accounts & Privacy tab</a>.', 'klarna-checkout-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=account' ) ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Check Autoptimize plugin checkout settings if they exist.
	 */
	public function check_autoptimize() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( 'on' === get_option( 'autoptimize_optimize_checkout' ) ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . sprintf( __( 'It looks like you are using the Autoptimize plugin and have enabled their <i>Optimize shop cart/checkout</i> setting. This might cause conflicts with the Klarna Checkout plugin. You can deactivate this feature in the  <a href="%s">Autoptimize settings page</a> (<i>→ Show advanced settings → Misc section</i>).', 'klarna-checkout-for-woocommerce' ), admin_url( 'options-general.php?page=autoptimize' ) ) . '</p>';
			echo '</div>';
		}
	}
}

Klarna_Checkout_For_WooCommerce_Admin_Notices::get_instance();

<?php
/**
 * Admin notice class file.
 *
 * @package Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
class KCO_Admin_Notices {

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
		$settings      = get_option( 'woocommerce_kco_settings', array() );
		$this->enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : 'no';

		add_action( 'admin_init', array( $this, 'check_settings' ) );
		add_action( 'admin_init', array( $this, 'check_hide_action' ) );
		add_action( 'admin_notices', array( $this, 'check_klarna_upstream' ) );
	}
	/**
	 * Checks the settings.
	 */
	public function check_settings() {
		if ( ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_action( 'woocommerce_settings_saved', array( $this, 'check_terms' ) );
			add_action( 'admin_notices', array( $this, 'check_autoptimize' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'check_https' ) );
			add_action( 'admin_notices', array( $this, 'check_terms' ) );
			add_action( 'admin_notices', array( $this, 'check_autoptimize' ) );
			add_action( 'admin_notices', array( $this, 'check_optimize' ) );
			add_action( 'admin_notices', array( $this, 'check_permalinks' ) );
			add_action( 'admin_notices', array( $this, 'version_warning_message' ) );
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
		if ( ! wc_get_page_id( 'terms' ) || wc_get_page_id( 'terms' ) < 0 && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_terms_notice', true ) ) {
			?>
			<div class="kco-message notice woocommerce-message notice-error">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_terms' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'You need to specify a terms page in WooCommerce Settings to be able to use Klarna Checkout. ', 'klarna-checkout-for-woocommerce' ) . '<a href="https://docs.woocommerce.com/document/configuring-woocommerce-settings/#section-24">' . __( 'Read more here. ', 'klarna-checkout-for-woocommerce' ) . '</a></p>' ) ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Check if https is configured.
	 */
	public function check_https() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}
		if ( ! is_ssl() && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_https_notice', true ) ) {
			?>
			<div class="kco-message notice woocommerce-message notice-error">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_https' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'You need to enable and configure https to be able to use Klarna Checkout. ', 'klarna-checkout-for-woocommerce' ) . '<a href="https://docs.woocommerce.com/document/ssl-and-https">' . __( 'Read more here. ', 'klarna-checkout-for-woocommerce' ) . '</a></p>' ) ); ?>
			</div>
			<?php
		}
	}


	/**
	 * Check Autoptimize plugin checkout settings if they exist.
	 */
	public function check_autoptimize() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( 'on' === get_option( 'autoptimize_optimize_checkout' ) && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_autoptimize_notice', true ) ) {
			?>
			<div class="kco-message notice woocommerce-message notice-error">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_autoptimize' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php // translators: %s: URL. ?>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'It looks like you are using the Autoptimize plugin and have enabled their <i>Optimize shop cart/checkout</i> setting. This might cause conflicts with the Klarna Checkout plugin. You can deactivate this feature in the  <a href="%s">Autoptimize settings page</a> (<i>→ Show advanced settings → Misc section</i>). ', 'klarna-checkout-for-woocommerce' ) . '<a href="https://docs.krokedil.com/article/301-klarna-checkout-optimizing-your-woocommerce-cart-checkout-when-using-an-iframe-based-checkout">' . __( 'Read more here. ', 'klarna-checkout-for-woocommerce' ) . '</a></p>' ) ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Check if Optimizing plugins exist.
	 */
	public function check_optimize() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_optimize_notice', true ) ) {
			if ( class_exists( 'autoptimizeBase' ) || class_exists( 'WpFastestCache' ) || function_exists( 'w3tc_class_autoload' ) ) {
				?>
				<div class="kco-message notice woocommerce-message notice-error">
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_optimize' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php echo wp_kses_post( wpautop( '<p>' . __( 'It looks as if you have a Optimizing or Caching plugin installed. Please make sure to not enable these features on the checkout page, as this can cause issues with Klarna Checkout. The checkout page should never be minified, concatenated, or cached. ', 'klarna-checkout-for-woocommerce' ) . '<a href="https://docs.krokedil.com/article/301-klarna-checkout-optimizing-your-woocommerce-cart-checkout-when-using-an-iframe-based-checkout">' . __( 'Read more here. ', 'klarna-checkout-for-woocommerce' ) . '</a></p>' ) ); ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Show admin notice if old Klarna Upstream plugin is installed.
	 */
	public function check_klarna_upstream() {

		$plugin_slug = 'klarna-upstream-for-woocommerce';

		// If plugin file exists.
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php' ) ) {
			// If can activate plugins.
			if ( current_user_can( 'activate_plugins' ) && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_upstream_notice', true ) ) {
				?>
				<div class="kco-message notice woocommerce-message notice-error">
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_upstream' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php // translators: %s: URL. ?>
				<?php echo wp_kses_post( wpautop( '<p>' . sprintf( __( 'The <i>Klarna upstream for WooCommerce</i> plugin is now available as <i>Klarna On-site Messaging for WooCommerce</i>. Please deactivate and delete <i>Klarna upstream for WooCommerce</i> and then install and activate <i>Klarna On-site Messaging for WooCommerce</i> via the new <a href="%s">Klarna Add-ons page</a>. ', 'klarna-checkout-for-woocommerce' ), admin_url( '/admin.php?page=checkout-addons' ) ) . '<a href="https://docs.krokedil.com/article/259-klarna-on-site-messaging"> ' . __( 'Read more here. ', 'klarna-checkout-for-woocommerce' ) . '</a></p>' ) ); ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Adds a version warning message for the merchant.
	 * To use this, change the current_message_version to the current KCO version that you want to add a warning for as a string. IE: '10.1.1'.
	 * Then add a message to the message string.
	 *
	 * Next time we want to add a message, update the current_message_version number and change the message again.
	 * As long as the version number is above the old version number, this should display the message to the user again.
	 *
	 * @return void
	 */
	public function version_warning_message() {
		$current_message_version = null;
		$dismissed_version       = get_user_meta( get_current_user_id(), 'dismissed_kco_version_number', true );
		$message                 = null;
		if ( null !== $current_message_version ) {
			if ( ! $dismissed_version || ! version_compare( $dismissed_version, $current_message_version, ' >= ' ) ) {
				?>
				<div class="kco-message notice woocommerce-message notice-error">
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'kco - hide - notice', $current_message_version ), 'kco_hide_notices_nonce', '_kco_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php echo wp_kses_post( wpautop( ' < p > ' . $message . ' < / p > ' ) ); ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Checks if we need to hide the version warning message.
	 *
	 * @return void
	 */
	public function check_hide_action() {
		if ( isset( $_GET['kco-hide-notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$nonce = isset( $_GET['_kco_notice_nonce'] ) ? sanitize_key( wp_unslash( $_GET['_kco_notice_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'kco_hide_notices_nonce' ) ) {
				wp_die( esc_html__( 'Action failed . Please refresh the page and retry . ', 'woocommerce' ) );
			}
			$hide_notice = isset( $_GET['kco-hide-notice'] ) ? sanitize_key( wp_unslash( $_GET['kco-hide-notice'] ) ) : '';
			update_user_meta( get_current_user_id(), 'dismissed_kco_version_number', $hide_notice );
		}
	}

	/**
	 * Check if Pretty permalinks is enabled
	 */
	public function check_permalinks() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_permalinks_notice', true ) ) {
			$permalinks = get_option( 'permalink_structure' );
			if ( empty( $permalinks ) ) {
				?>
				<div class="kco-message notice woocommerce-message notice-error">
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_permalinks' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php echo wp_kses_post( wpautop( '<p>' . __( 'It looks as if you don\'t have pretty permalinks enabled in WordPress. For Klarna checkout for WooCommerce to function properly, this needs to be enabled. ', 'klarna-checkout-for-woocommerce' ) . '<a href="https://wordpress.org/support/article/using-permalinks">' . __( 'Read more here. ', 'klarna-checkout-for-woocommerce' ) . '</a></p>' ) ); ?>
				</div>
				<?php
			}
		}
	}
}

KCO_Admin_Notices::get_instance();

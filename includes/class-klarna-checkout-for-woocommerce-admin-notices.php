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
		add_action( 'admin_init', array( $this, 'check_hide_action' ) );
		add_action( 'admin_notices', array( $this, 'check_klarna_upstream' ) );
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
			add_action( 'admin_notices', array( $this, 'check_optimize' ) );
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
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_terms' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'You need to specify a terms page in WooCommerce Settings to be able to use Klarna Checkout.', 'klarna-checkout-for-woocommerce' ) . '</p>' ) ); ?>
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
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_https' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'You need to enable and configure https to be able to use Klarna Checkout.', 'klarna-checkout-for-woocommerce' ) . '</p>' ) ); ?>
			</div>
			<?php
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
		if ( 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) && 'no' === get_option( 'woocommerce_registration_generate_username' ) && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_username_notice', true ) ) {
			?>
			<div class="kco-message notice woocommerce-message notice-error">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_username' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . sprintf( __( 'You need to tick the checkbox <i>When creating an account, automatically generate a username from the customer\'s email address</i> when having the <i>Allow customers to create an account during checkout</i> setting activated. This can be changed in the <a href="%s">Accounts & Privacy tab', 'klarna-checkout-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=account' ) ) . '</p>' ) ); ?>
			</div>
			<?php
		}
		// Account page - password.
		if ( 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) && 'no' === get_option( 'woocommerce_registration_generate_password' ) && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_password_notice', true ) ) {
			?>
			<div class="kco-message notice woocommerce-message notice-error">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_password' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . sprintf( __( 'You need to tick the checkbox <i>When creating an account, automatically generate an account password</i> when having the <i>Allow customers to create an account during checkout</i> setting activated. This can be changed in the <a href="%s">Accounts & Privacy tab', 'klarna-checkout-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=account' ) ) . '</p>' ) ); ?>
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
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_autoptimize' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'It looks like you are using the Autoptimize plugin and have enabled their <i>Optimize shop cart/checkout</i> setting. This might cause conflicts with the Klarna Checkout plugin. You can deactivate this feature in the  <a href="%s">Autoptimize settings page</a> (<i>→ Show advanced settings → Misc section</i>).', 'klarna-checkout-for-woocommerce' ) . '</p>' ) ); ?>
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
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_optimize' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php echo wp_kses_post( wpautop( '<p>' . __( 'It looks as if you have a Optimizing or Caching plugin installed. Please make sure to not enable these features on the checkout page, as this can cause issues with Klarna Checkout. The checkout page should never be minified, concatenated, or cached', 'klarna-checkout-for-woocommerce' ) . '</p>' ) ); ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Show admin notice if old Klarna Upstream plugin is installed..
	 */
	public function check_klarna_upstream() {

		$plugin_slug = 'klarna-upstream-for-woocommerce';

		// If plugin file exists.
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php' ) ) {
			// If can activate plugins.
			if ( current_user_can( 'activate_plugins' ) && ! get_user_meta( get_current_user_id(), 'dismissed_kco_check_upstream_notice', true ) ) {
				?>
				<div class="kco-message notice woocommerce-message notice-error">
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kco_check_upstream' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php echo wp_kses_post( wpautop( '<p>' . sprintf( __( 'The <i>Klarna upstream for WooCommerce</i> plugin is now available as <i>Klarna On-site Messaging for WooCommerce</i>. Please deactivate and delete <i>Klarna upstream for WooCommerce</i> and then install and activate <i>Klarna On-site Messaging for WooCommerce</i> via the new <a href="%s">Klarna Add-ons page</a>..', 'klarna-checkout-for-woocommerce' ), admin_url( '/admin.php?page=checkout-addons' ) ) . '</p>' ) ); ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Adds a version warning message for the merchant.
	 * To use this, change the current_message_version to the currenct KCO version that you want to add a warning for as a string. IE: '10.1.1'.
	 * Then add a message to the message string.
	 *
	 * Next time we want to add a message, update the current_message_version number and change the message again.
	 * Aslong as the version number is above the old version number, this should display the message to the user again.
	 *
	 * @return void
	 */
	public function version_warning_message() {
		$current_message_version = '1.10.2';
		$dismissed_version       = get_user_meta( get_current_user_id(), 'dismissed_kco_version_number', true );
		$message                 = __( '<h3>Klarna Checkout 1.10.2 notice</h3><p>With version 1.10.2 we have added a control to make sure that validation requests from Klarna gets a valid response. This might mean that orders are not going through if your store is blocking these signals. Please test and see that everything is working correctly, and verify that no firewalls or security plugins are blocking Klarnas request.</p>', 'klarna-checkout-for-woocommerce' );
		if ( $current_message_version !== null ) {
			if ( ! $dismissed_version || ! version_compare( $dismissed_version, $current_message_version, '>=' ) ) {
				?>
				<div class="kco-message notice woocommerce-message notice-error">
				<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'kco-hide-notice', $current_message_version ), 'kco_hide_notices_nonce', '_kco_notice_nonce' ) ); ?>"><?php _e( 'Dismiss', 'woocommerce' ); ?></a>
				<?php echo wp_kses_post( wpautop( '<p>' . $message . '</p>' ) ); ?>
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

		if ( isset( $_GET['kco-hide-notice'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_kco_notice_nonce'] ) ), 'kco_hide_notices_nonce' ) ) { // WPCS: input var ok, CSRF ok.
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'woocommerce' ) );
			}
			update_user_meta( get_current_user_id(), 'dismissed_kco_version_number', $_GET['kco-hide-notice'] ); // WPCS: input var ok, CSRF ok.
		}
	}
}

Klarna_Checkout_For_WooCommerce_Admin_Notices::get_instance();

<?php
/**
 * Admin notice class file.
 *
 * @package KlarnaShippingService/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns error messages.
 */
class KSS_Admin_Notices {

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
	 * KSS_Admin_Notices constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'check_kco_version' ) );
	}

	/**
	 * Check if the current KCO version is correct.
	 */
	public function check_kco_version() {
		if ( defined( 'KCO_WC_VERSION' ) && ! version_compare( KCO_WC_VERSION, '2.5.0', '>=' ) ) {
			?>
			<div class="kco-message notice woocommerce-message notice-error">
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'It looks as if you don\'t have the required minimum version of <b>Kustom Checkout for WooCommerce</b> installed.', 'klarna-shipping-service-for-woocommerce' ) . '</p>' ) ); ?>
			<?php echo wp_kses_post( wpautop( '<p>' . __( 'Since version 1.1.0 of <b>Kustom Shipping Assistance for WooCommerce</b> you are required to have version <b>2.5.0</b> or newer of <b>Kustom Checkout for WooCommerce</b> for the plugin to function correctly.', 'klarna-shipping-service-for-woocommerce' ) . '</p>' ) ); ?>
			</div>
			<?php
		}
	}
}

KSS_Admin_Notices::get_instance();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Addons class.
 *
 * Handles Klarna Checkout addons page.
 */
class Klarna_Checkout_For_WooCommerce_Addons {

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_css' ) );
		add_action( 'wp_ajax_change_klarna_addon_status', array( $this, 'change_klarna_addon_status' ) );
	}

	/**
	 * Load Admin CSS
	 **/
	public function enqueue_css( $hook ) {
		if ( 'woocommerce_page_checkout-addons' == $hook || 'settings_page_specter-admin' == $hook ) {
			wp_register_style( 'klarna-checkout-addons', KCO_WC_PLUGIN_URL . '/assets/css/checkout-addons.css', false, KCO_WC_VERSION );
			wp_enqueue_style( 'klarna-checkout-addons' );
			wp_register_script( 'klarna-checkout-addons', KCO_WC_PLUGIN_URL . '/assets/js/klarna-checkout-for-woocommerce-addons.js', true, KCO_WC_VERSION );
			wp_enqueue_script( 'klarna-checkout-addons' );
		}
	}

	/**
	 * Add the Addons menu to WooCommerce
	 **/
	public function add_menu() {
		$submenu = add_submenu_page( 'woocommerce', __( 'Checkout addons', 'klarna-checkout-for-woocommerce' ), __( 'Checkout addons', 'klarna-checkout-for-woocommerce' ), 'manage_woocommerce', 'checkout-addons', array( $this, 'options_page' ) );
	}

	/**
	 * Add the Addons options page to WooCommerce.
	 **/
	public function options_page() {
		?>
		<div id="checkout-addon-heading" class="checkout-addon-heading">
			<h1><?php esc_html_e( 'Checkout Addons', 'klarna-checkout-for-woocommerce' ); ?></h1>
		</div>	
		<div id="checkout-addons" class="wrap">
			<div class="list">
				<div class="checkout-addon klarna-order-management-for-woocommerce">
					<h3 class="checkout-addon-title">Klarna Order Management for WooCommerce</h3>
					<p class="checkout-addon-excerpt">Handle post purchase order management in Klarnas system directly from WooCommerce.</p>
					<div class="checkout-addon-footer">
						<div class="inside-wrapper">
							<span class="checkout-addon-status" data-plugin-slug="klarna-order-management-for-woocommerce">Status: <span class="status-text"><?php echo self::get_addon_status( 'klarna-order-management-for-woocommerce' )['title']; ?></span></span>
							<span class="checkout-addon-action"><?php echo self::get_addon_action_button( 'klarna-order-management-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>

				<div class="checkout-addon klarna-onsite-messaging-for-woocommerce">
					<h3 class="checkout-addon-title">Klarna On-Site Messaging for WooCommerce</h3>
					<p class="checkout-addon-excerpt">Handle post purchase order management in Klarnas system directly from WooCommerce.</p>
					<div class="checkout-addon-footer">
						<div class="inside-wrapper">
							<span class="checkout-addon-status" data-plugin-slug="klarna-onsite-messaging-for-woocommerce">Status: <span class="status-text"><?php echo self::get_addon_status( 'klarna-onsite-messaging-for-woocommerce' )['title']; ?></span></span>
							<span class="checkout-addon-action"><?php echo self::get_addon_action_button( 'klarna-onsite-messaging-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>
				
				<div class="checkout-addon">
					<h3 class="checkout-addon-title">My addon</h3>
					<p class="checkout-addon-excerpt">A short text to describe the addon.</p>
					<div class="checkout-addon-footer">
						<div class="inside-wrapper">
							<span class="checkout-addon-status">Status: <span><?php self::get_addon_status( 'hej' ); ?></span></span>
							<span class="checkout-addon-action"><a class="button install" href="#">Install</a></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get addon status helper function.
	 **/
	public static function get_addon_status( $plugin_slug ) {
		$status = array(
			'id'    => 'unknown',
			'title' => 'Unknown',
		);
		// If plugin file exists.
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php' ) ) {
			if ( is_plugin_active( $plugin_slug . '/' . $plugin_slug . '.php' ) ) {
				$status['id']    = 'activated';
				$status['title'] = 'Activated';
			} else {
				$status['id']    = 'deactivated';
				$status['title'] = 'Deactivated';
			}
		} else {
			$status['id']    = 'not-installed';
			$status['title'] = 'Not installed';
		}
		return $status;
	}

	/**
	 * Get addon action button helper function.
	 **/
	public static function get_addon_action_button( $plugin_slug ) {

		$status = self::get_addon_status( $plugin_slug );
		$action = '';

		switch ( $status['id'] ) {
			case 'not-installed':
				$action = '<div class="button install" data-status="not-installed" data-action="install" data-plugin-slug="' . $plugin_slug . '"><label class="switch download"><span class="dashicons dashicons-download round"></span></label><span class="action-text">' . __( 'Install', 'klarna-checkout-for-woocommerce' ) . '</span></div>';
				break;
			case 'activated':
				$action = '<div class="button install" data-status="activated" data-action="deactivate" data-plugin-slug="' . $plugin_slug . '"><label class="switch"><span class="slider round"></span></label><span class="action-text">' . __( 'Deactivate', 'klarna-checkout-for-woocommerce' ) . '</span></div>';
				break;
			case 'deactivated':
				$action = '<div class="button install" data-status="deactivated" data-action="activate" data-plugin-slug="' . $plugin_slug . '"><label class="switch"><span class="slider round"></span></label><span class="action-text">' . __( 'Activate', 'klarna-checkout-for-woocommerce' ) . '</span></div>';
				break;
			default:
				$action = '<a class="button install" href="#">Install</a>';
		}

		return $action;
	}

	/**
	 * Ajax request callback function
	 */
	public function change_klarna_addon_status() {
		$status                     = $_REQUEST['plugin_status'];
		$action                     = $_REQUEST['plugin_action'];
		$plugin_slug                = $_REQUEST['plugin_slug'];
		$plugin_folder_and_filename = $plugin_slug . '/' . $plugin_slug . '.php';
		$plugin                     = WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php';

		if ( 'activate' === $action ) {
			$result = activate_plugin( $plugin, null, false, true );

			if ( is_wp_error( $result ) ) {
				// Process Error.
				$new_status       = 'deactivated';
				$new_action       = 'activate';
				$new_status_label = 'Deactivated';
				$new_action_label = 'Activate';
			} else {
				$new_status       = 'activated';
				$new_action       = 'deactivate';
				$new_status_label = 'Activated';
				$new_action_label = 'Deactivate';
			}
		}
		if ( 'deactivate' === $action ) {
			$result = deactivate_plugins( $plugin, true, null );
			if ( is_wp_error( $result ) ) {
				// Process Error.
				$new_status       = 'activated';
				$new_action       = 'deactivate';
				$new_status_label = 'Activated';
				$new_action_label = 'Deactivate';
			} else {
				$new_status       = 'deactivated';
				$new_action       = 'activate';
				$new_status_label = 'Deactivated';
				$new_action_label = 'Activate';
			}
		}

		if ( 'install' === $action ) {

			// Check if get_plugins() function exists. This is required on the front end of the
			// site, since it is in a file that is normally only loaded in the admin.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins = get_plugins();
			if ( ! array_key_exists( $plugin_folder_and_filename, $all_plugins ) ) {

				$result = self::install_plugin( self::get_download_link_from_slug( $plugin_slug ) );

				if ( is_wp_error( $result ) || 'error' === $result['status'] ) {
					$new_status       = 'not-installed';
					$new_action       = 'install';
					$new_status_label = 'Not installed';
					$new_action_label = 'Install';
				} else {
					if ( 'installed' === $result['status'] ) {
						$new_status       = 'installed';
						$new_action       = 'activate';
						$new_status_label = 'Installed';
						$new_action_label = 'Activate';
					} else {
						$new_status       = 'not-installed';
						$new_action       = 'install';
						$new_status_label = 'Not installed';
						$new_action_label = 'Install';
					}
				}
			}
		}

		if ( is_wp_error( $result ) ) {
			$return = array(
				'error_message'    => $result->get_error_message(),
				'new_status'       => $new_status,
				'new_action'       => $new_action,
				'new_status_label' => $new_status_label,
				'new_action_label' => $new_action_label,
			);
			wp_send_json_error( $return );
		} else {
			$return = array(
				'new_status'       => $new_status,
				'new_action'       => $new_action,
				'new_status_label' => $new_status_label,
				'new_action_label' => $new_action_label,
			);
			wp_send_json_success( $return );
		}

		wp_die();
	}

	/**
	 * Install and activate dependency.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return bool|array false or Message.
	 */
	public function install_plugin( $url ) {

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! class_exists( 'Klarna_Skin', false ) ) {
			include_once KCO_WC_PLUGIN_PATH . '/includes/admin/class-klarna-checkout-for-woocommerce-skin.php';
		}

		$installer = new Plugin_Upgrader( $skin = new Klarna_Skin() );
		$result    = $installer->install( $url );

		wp_cache_flush();

		if ( is_wp_error( $result ) ) {
			return array(
				'status'  => 'error',
				'message' => $result->get_error_message(),
			);
		}
		if ( null === $result ) {
			return array(
				'status'  => 'error',
				'message' => esc_html__( 'Plugin download failed' ),
			);
		}

		return array(
			'status'  => 'installed',
			/* translators: %s: Plugin name */
			'message' => sprintf( esc_html__( '%s has been installed.' ), 'Plugin' ),
		);
	}

	/**
	 * Get plugin download link from slug helper function.
	 **/
	public static function get_download_link_from_slug( $slug ) {
		switch ( $slug ) {
			case 'klarna-onsite-messaging-for-woocommerce':
				$url = 'https://s3-eu-west-1.amazonaws.com/krokedil-plugins/klarna-onsite-messaging-for-woocommerce.zip';
				break;
			case 'klarna-order-management-for-woocommerce':
				$url = 'https://downloads.wordpress.org/plugin/klarna-order-management-for-woocommerce.zip';
				break;
			default:
				$url = '';
		}
		return $url;
	}

}

Klarna_Checkout_For_WooCommerce_Addons::get_instance();

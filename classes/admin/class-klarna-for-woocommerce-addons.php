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
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_css' ) );
			add_action( 'wp_ajax_change_klarna_addon_status', array( $this, 'change_klarna_addon_status' ) );
		}

		/**
		 * Load Admin CSS
		 *
		 * @param string $hook The hook for the page.
		 **/
		public function enqueue_css( $hook ) {
			if ( 'woocommerce_page_checkout-addons' === $hook || 'settings_page_specter-admin' === $hook ) {
				wp_register_style( 'klarna-checkout-addons', KCO_WC_PLUGIN_URL . '/assets/css/checkout-addons.css', false, KCO_WC_VERSION );
				wp_enqueue_style( 'klarna-checkout-addons' );
				wp_register_script( 'klarna-checkout-addons', KCO_WC_PLUGIN_URL . '/assets/js/klarna-for-woocommerce-addons.js', true, KCO_WC_VERSION, false );
				$params = array(
					'change_addon_status_nonce' => wp_create_nonce( 'change_klarna_addon_status' ),
				);

				wp_localize_script( 'klarna-checkout-addons', 'kco_addons_params', $params );
				wp_enqueue_script( 'klarna-checkout-addons' );
			}
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
			$section       = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$addon_content = self::get_addons();
			?>
			<div id="checkout-addons-heading" class="checkout-addons-heading">
			<div class="checkout-addons-wrap">
			<h1><?php esc_html_e( 'Klarna Add-ons', 'klarna-checkout-for-woocommerce' ); ?></h1>
			</div>
			</div>
				<?php if ( $addon_content->start ) : ?>
					<?php foreach ( $addon_content->start as $start ) : ?>
						<?php if ( isset( $start->plugin_id ) && in_array( $start->plugin_id, array( 'kco', 'both' ), true ) ) : ?>
						<div class="checkout-addons-banner-block checkout-addons-wrap wrap <?php echo esc_html( $start->class ); ?>">
							<h2><?php echo esc_html( $start->title ); ?></h2>
							<?php echo self::get_dynamic_content( $start->content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>

			<div id="checkout-addons-body" class="checkout-addons-body checkout-addons-wrap wrap">
				<?php if ( $addon_content->sections ) : ?>
					<?php foreach ( $addon_content->sections as $section ) : ?>
						<?php if ( isset( $section->plugin_id ) && in_array( $section->plugin_id, array( 'kco', 'both' ), true ) ) : ?>
							<div id="<?php echo esc_html( $section->class ); ?>" class="<?php echo esc_html( $section->class ); ?>">
								<div class="list">
									<?php foreach ( $section->items as $item ) : ?>
										<div class="checkout-addon <?php echo esc_html( $item->class ); ?>">
											<?php if ( $item->image ) : ?>
												<img src="<?php echo esc_attr( $item->image ); ?>" alt="<?php echo esc_html( $item->title ); ?>" class="checkout-addon-icon"/>
											<?php endif; ?>
											<h3 class="checkout-addon-title"><?php echo esc_html( $item->title ); ?></h3>
											<p class="checkout-addon-excerpt"><?php echo esc_textarea( $item->description ); ?></p>
											<div class="checkout-addon-footer">
												<div class="inside-wrapper">
													<?php if ( $item->href ) : ?>
														<span class="checkout-addon-action"><?php echo self::get_addon_action_button( $item ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
													<?php else : ?>
														<span class="checkout-addon-status"></span>
														<span class="checkout-addon-action"><?php echo self::get_addon_action_button( $item ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
													<?php endif; ?>	
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
				<?php
		}

		/**
		 * Get addon status helper function.
		 *
		 * @param string $plugin_slug The slug of the plugin.
		 **/
		public static function get_addon_status( $plugin_slug ) {
			$status = array(
				'id'    => 'unknown',
				'title' => 'Unknown',
			);

			// If plugin file exists.
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug ) ) {
				if ( is_plugin_active( $plugin_slug ) ) {
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
		 *
		 * @param array $item The addon item.
		 **/
		public static function get_addon_action_button( $item ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				$class_name = 'button install';
			} else {
				$class_name = 'button install disabled';
			}
			if ( ! empty( $item->href ) ) {
				if ( 'kco-settings-page' === $item->href ) {
					$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kco' );
				} else {
					$url = $item->href;
				}
				$action = '<a class="' . $class_name . '" href="' . $url . '" target="_blank">' . $item->button . '</a>';
			} else {
				$status = self::get_addon_status( $item->plugin_slug );
				$action = '';

				switch ( $status['id'] ) {
					case 'not-installed':
						$action = '<div class="' . $class_name . '" data-status="not-installed" data-action="install" data-plugin-id="' . strtok( $item->plugin_slug, '/' ) . '" data-plugin-slug="' . $item->plugin_slug . '" data-plugin-url="' . $item->plugin_url . '"><label class="switch download"><span class="dashicons dashicons-download round"></span></label><span class="action-text" title="' . __( 'Install', 'klarna-checkout-for-woocommerce' ) . '">' . __( 'Not installed', 'klarna-checkout-for-woocommerce' ) . '</span></div>';
						break;
					case 'activated':
						$action = '<div class="' . $class_name . '" data-status="activated" data-action="deactivate" data-plugin-id="' . strtok( $item->plugin_slug, '/' ) . '" data-plugin-slug="' . $item->plugin_slug . '" data-plugin-url="' . $item->plugin_url . '"><label class="switch"><span class="slider round"></span></label><span class="action-text">' . __( 'Activated', 'klarna-checkout-for-woocommerce' ) . '</span></div>';
						break;
					case 'deactivated':
						$action = '<div class="' . $class_name . '" data-status="deactivated" data-action="activate" data-plugin-id="' . strtok( $item->plugin_slug, '/' ) . '" data-plugin-slug="' . $item->plugin_slug . '" data-plugin-url="' . $item->plugin_url . '"><label class="switch"><span class="slider round"></span></label><span class="action-text">' . __( 'Deactivated', 'klarna-checkout-for-woocommerce' ) . '</span></div>';
						break;
					default:
						$action = '<a class="' . $class_name . '" href="#">Install</a>';
				}
			}

			return $action;
		}

		/**
		 * Returns dynamic content. Replaces certain url's related to the specific domain.
		 *
		 * @param string $content The content.
		 * @return string formatted $content
		 */
		public static function get_dynamic_content( $content = '' ) {
			// Pattern substitution.
			$replacements = array(
				'{{klarna-settings-page-url}}' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kco' ),
			);
			return str_replace( array_keys( $replacements ), $replacements, $content );
		}

		/**
		 * Ajax request callback function
		 */
		public function change_klarna_addon_status() {
			$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['nonce'] ) ) : '';
			// Check nonce.
			if ( ! wp_verify_nonce( $nonce, 'change_klarna_addon_status' ) ) {
				wp_send_json_error( 'bad_nonce' );
				exit;
			}

			$action      = isset( $_REQUEST['plugin_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['plugin_action'] ) ) : '';
			$plugin_slug = isset( $_REQUEST['plugin_slug'] ) ? wp_unslash( $_REQUEST['plugin_slug'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- No way to sanitize without breaking the string.
			$plugin_url  = isset( $_REQUEST['plugin_url'] ) ? esc_url_raw( wp_unslash( $_REQUEST['plugin_url'] ) ) : '';

			// Check if the user can install plugins or manage plugins.
			if ( ( ! current_user_can( 'install_plugins' ) && 'install' === $action )
				|| ( ! current_user_can( 'update_plugins' ) && in_array( $action, array( 'activate', 'deactivate' ), true ) )
			) {
				wp_send_json_error( "You are not allowed to $action plugins" );
				exit;
			}
			$plugin = WP_PLUGIN_DIR . '/' . $plugin_slug;

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
				if ( ! array_key_exists( $plugin_slug, $all_plugins ) ) {
					$result = self::install_plugin( $plugin_url );

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
		}

		/**
		 * Install and activate dependency.
		 *
		 * @param string $url The URL to the plugin to install.
		 * @return bool|array false or Message.
		 */
		public function install_plugin( $url ) {
			if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			if ( ! class_exists( 'Klarna_Skin', false ) ) {
				include_once KCO_WC_PLUGIN_PATH . '/classes/admin/class-klarna-skin.php';
			}
			$skin      = new Klarna_Skin();
			$installer = new Plugin_Upgrader( $skin );
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
		 * Get featured for the addons screen
		 *
		 * @return array of objects
		 */
		public static function get_addons() {
			$addons = get_transient( 'wc_kco_addons' );
			if ( false === $addons ) {
				$kco_settings = get_option( 'woocommerce_kco_settings' );
				$raw_addons   = wp_safe_remote_get( 'https://s3-eu-west-1.amazonaws.com/krokedil-checkout-addons/klarna-checkout-for-woocommerce-addons.json', array( 'user-agent' => 'KCO Addons Page. Testmode: ' . $kco_settings['testmode'] ) );
				if ( ! is_wp_error( $raw_addons ) ) {
					$addons = json_decode( wp_remote_retrieve_body( $raw_addons ) );
					if ( $addons ) {
						set_transient( 'wc_kco_addons', $addons, DAY_IN_SECONDS );
					}
				}
			}
			if ( is_object( $addons ) ) {
				return $addons;
			}
		}
	}
}
Klarna_For_WooCommerce_Addons::get_instance();

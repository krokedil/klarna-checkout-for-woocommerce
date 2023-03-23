<?php
/**
 * Functions file for the plugin.
 *
 * @package  Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Krokedil_Support_Form {

	private static $init    = false;
	private static $version = '1.0.0';

	public static function init() {
		// if ( self::$init ) {
		// return;
		// }

		add_action( 'admin_init', array( __CLASS__, 'process_support_form' ) );
		add_action( 'wc_ajax_krokedil_set_plugin_status', array( __CLASS__, 'set_plugin_status' ) );
		add_action( 'wc_ajax_krokedil_get_plugin_log_content', array( __CLASS__, 'get_plugin_log_content' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		self::$init = true;
	}

	public static function enqueue_assets() {
		wp_enqueue_style(
			'krokedil_support_form_styles',
			plugins_url( 'assets/css/krokedil-support-form.css', KCO_WC_MAIN_FILE ),
			array(),
			self::$version
		);

		wp_register_script(
			'krokedil_support_form_script',
			plugins_url( 'assets/js/krokedil-support-form.js', KCO_WC_MAIN_FILE ),
			array(),
			self::$version,
			true
		);
		$admin_localize_params = array(
			'get_plugin_log_content_url'   => WC_AJAX::get_endpoint( 'krokedil_get_plugin_log_content' ),
			'get_plugin_log_content_nonce' => wp_create_nonce( 'krokedil_get_plugin_log_content' ),
		);
		wp_localize_script( 'krokedil_support_form_script', 'krokedil_support_form_params', $admin_localize_params );
		wp_enqueue_script( 'krokedil_support_form_script' );

	}


	/**
	 * Check if a plugin is activated.
	 *
	 * @param string $plugin_name The plugin's directory and name of main plugin file.
	 * @return bool True if the plugin is activated, false otherwise.
	 */
	public static function is_plugin_activated( $plugin_name ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}
		return in_array( $plugin_name, $active_plugins, true ) || array_key_exists( $plugin_name, $active_plugins );
	}

	/**
	 * Get all the log filenames that are related to a plugin.
	 *
	 * @param string $plugin_name The plugin's directory name, and main plugin filename. Defaults to 'all'.
	 * @return array
	 */
	public static function get_plugin_logs( $plugin_name = 'all' ) {
		$logs = array();
		foreach ( WC_Admin_Status::scan_log_files() as $log => $path ) {
			if ( 'all' == strtolower( $plugin_name ) || strpos( $log, $plugin_name ) !== false ) {
				$timestamp = filemtime( WC_LOG_DIR . $path );
				$date      = sprintf(
				/* translators: 1: last access date 2: last access time 3: last access timezone abbreviation */
					__( '%1$s at %2$s %3$s', 'woocommerce' ),
					wp_date( wc_date_format(), $timestamp ),
					wp_date( wc_time_format(), $timestamp ),
					wp_date( 'T', $timestamp )
				);

				$logs[ $date ] = $path;
			}
		}

		return $logs;
	}

	/**
	 * Get the content of a WooCommerce log file.
	 *
	 * @return void The log content is sent back in the JSON response.
	 */
	public static function get_plugin_log_content() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_key( $_GET['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'krokedil_get_plugin_log_content' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		/* Check if the user can access WC logs. */
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'bad_permissions' );
		}

		$filename = isset( $_GET['filename'] ) ? sanitize_text_field( wp_unslash( $_GET['filename'] ) ) : '';
		$content  = file_get_contents( WC_LOG_DIR . $filename );
		wp_send_json_success( $content );
	}

	/**
	 * Outputs the HTML for selecting a WooCommerce log, and displaying its content.
	 *
	 * @param string $title The heading for this HTML section.
	 * @param array  $log An array containing the log content.
	 * @return void The HTML is output directly.
	 */
	public static function output_log_selector( $title, $log ) {

		// Normalized name intended to be form and CSS friendly.
		$name = esc_attr( str_replace( ' ', '-', strtolower( $title ) ) );
		?>
	<section class="log-wrapper <?php esc_attr_e( $name ); ?>">
		<h3><?php echo $title; ?></h3>
		<?php
		if ( empty( $log ) ) {
			echo '<p class="' . $name . '">No log available.</p>';
			return;
		}
		?>
		<select class="kco-log-option wc-enhanced-select <?php echo $name; ?> " name="<?php echo $name; ?>">
			<?php foreach ( $log as $date => $path ) : ?>
			<option value="<?php esc_attr_e( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
			<?php endforeach; ?>
		</select>
		<a class="view-log">View log</a>
		<textarea class="log-content" readonly></textarea>
	</section>
		<?php
	}

	/**
	 * Generate the HTML for a plugin action button
	 *
	 * @param mixed $plugin_name The plugin's directory name, and main plugin filename.
	 * @param mixed $install The plugin's install URL, and/or slug.
	 * @return string
	 */
	public static function addon_action_button( $plugin_name, $install ) {
		$attr = 'href="#" data-plugin-name="' . $plugin_name . '"';
		if ( isset( $install['slug'] ) ) {
			$attr .= ' data-plugin-slug="' . $install['slug'] . '"';
		}

		if ( isset( $install['url'] ) ) {
			$attr .= ' data-plugin-url="' . $install['url'] . '"';
		}

		if ( self::is_plugin_activated( $plugin_name ) ) {
			$attr .= ' class="button button-disabled"';
			$attr .= ' data-action="activated"';

			$text = __( 'Active', 'plugin' );
		} elseif ( get_plugins()[ $plugin_name ] ?? false ) {
			$attr .= ' class="button activate-now button-primary"';
			$attr .= ' data-action="activate"';

			$text = __( 'Activate', 'plugin' );
		} else {
			$attr .= ' class="install-now button"';
			$attr .= ' data-action="install"';

			$text = __( 'Install Now', 'plugin' );
		}
		return "<a {$attr}>{$text}</a>";
	}

	/**
	 * Handles installing and activating an addon plugin.
	 *
	 * @return void
	 */
	public static function set_plugin_status() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'krokedil_set_plugin_status' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		// Either a slug or a URL is required.
		$plugin_name = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';
		$plugin_url  = isset( $_POST['plugin_url'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_url'] ) ) : '';

		// Allowed: install, activate: plugin. If 'activated' do nothing.
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( 'activated' === $action ) {
			wp_send_json_success( 'active' );
		}

		if ( 'install' === $action ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_send_json_error( 'no_permission' );
			}
		}

		if ( 'activate' === $action ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_send_json_error( 'no_permission' );
			}

			$plugin = WP_PLUGIN_DIR . '/' . $plugin_name;
			if ( 'activate' === $action && ! self::is_plugin_activated( $plugin ) ) {
				$result = activate_plugin( $plugin_slug );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				} else {
					wp_send_json_success( 'activated' );
				}
			}
		}

		if ( 'install' === $action ) {
			if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$skin      = new Automatic_Upgrader_Skin();
			$installer = new Plugin_Upgrader( $skin );
			$result    = $installer->install( $plugin_url );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			} else {
				wp_send_json_success( 'installed' );
			}
		}

	}

	/**
	 * Process the form for submission to support.
	 *
	 * @return void
	 */
	public static function process_support_form() {
		if ( isset( $_POST['submit'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! isset( $_POST['krokedil_support_form_nonce'] ) || ! wp_verify_nonce( $_POST['krokedil_support_form_nonce'], 'krokedil_support_form_nonce' ) ) {
				return;
			}

			$error = false;
			if ( ! is_email( sanitize_email( $_POST['email'] ) ) ) {
				$error = true;
			}

			$from    = sanitize_email( $_POST['email'] );
			$to      = 'support@krokedil.se';
			$subject = sanitize_text_field( $_POST['subject'] );
			$message = sanitize_textarea_field( $_POST['description'] );
			$headers = array(
				'From: ' . $from,
				'Reply-To: ' . $from,
				'Content-Type: text/html; charset=UTF-8',
			);

			$attachment = array();

			if ( isset( $_POST['klarna-checkout'] ) ) {
				$attachment[] = WC_LOG_DIR . sanitize_text_field( $_POST['klarna-checkout'] );
			}

			if ( isset( $_POST['klarna-order-management'] ) ) {
				$attachment[] = WC_LOG_DIR . sanitize_text_field( $_POST['klarna-order-management'] );
			}

			if ( isset( $_POST['fatal-error-log'] ) ) {
				$attachment[] = WC_LOG_DIR . sanitize_text_field( $_POST['fatal-error-log'] );
			}

			if ( isset( $_POST['additional-log'] ) ) {
				$attachment[] = WC_LOG_DIR . sanitize_text_field( $_POST['additional-log'] );
			}

			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'additional-log-' ) !== false ) {
					$attachment[] = WC_LOG_DIR . sanitize_text_field( $value );
				}
			}

			$attachment = array_filter(
				$attachment,
				function( $path ) {
					return file_exists( $path ) && is_readable( $path );
				},
			);

			if ( 'on' === $_POST['system-report-include'] ) {
				$system_report = sanitize_textarea_field( $_POST['system-report'] );
				$filename      = 'system-report-' . date( 'Y-m-d' ) . '.txt';
				$filepath      = WP_CONTENT_DIR . '/uploads/' . $filename;

				if ( file_put_contents( $filepath, $system_report ) ) {
					$attachment[] = $filepath;
				}
			}

			if ( ! $error ) {
				$error = wp_mail( $to, $subject, $message, $headers, $attachment );
			}
		}
	}

}

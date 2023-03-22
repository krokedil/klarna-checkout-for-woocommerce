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

	private static $init = false;

	public static function init() {
		if ( self::$init ) {
			return;
		}

		add_action( 'wc_ajax_krokedil_set_plugin_status', array( __CLASS__, 'set_plugin_status' ) );

		self::$init = true;
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
	 * Get the plugin's log files.
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

}

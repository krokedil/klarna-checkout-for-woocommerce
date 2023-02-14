<?php
/**
 * Klarna add-on tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KCO_OSM_PATH', 'klarna-onsite-messaging-for-woocommerce/klarna-onsite-messaging-for-woocommerce.php' );
define( 'KCO_KOM_PATH', 'klarna-order-management-for-woocommerce/klarna-order-management-for-woocommerce.php' );

/**
 * Generate the URL for activating a plugin.
 *
 * Note: the user will be redirected to the plugins page after activation.
 *
 * @param string $plugin_name The plugin's directory and name of main plugin file.
 * @return string The URL for activating the plugin.
 */
function kco_activate_plugin_url( $plugin_name ) {
	return esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $plugin_name ), 'activate-plugin_' . $plugin_name ) );
}

/**
 * Check if a plugin is activated.
 *
 * @param string $plugin_name The plugin's directory and name of main plugin file.
 * @return bool True if the plugin is activated, false otherwise.
 */
function kco_is_plugin_activated( $plugin_name ) {
	$active_plugins = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}
	return in_array( $plugin_name, $active_plugins, true ) || array_key_exists( $plugin_name, $active_plugins );
}

/**
 * Generate the HTML for a plugin action button.
 *
 * @param string $plugin_name The plugin's directory and name of main plugin file.
 * @param array  $install The plugin's install URL or slug.
 * @return string The HTML for the plugin action button.
 */
function kco_plugin_action_button( $plugin_name, $install = array() ) {
	if ( kco_is_plugin_activated( $plugin_name ) ) {
		$attr = 'class="button button-disabled"';
		$text = __( 'Active', 'plugin' );
	} elseif ( get_plugins()[ $plugin_name ] ?? false ) {
		$attr = 'class="button activate-now button-primary" href="' . kco_activate_plugin_url( $plugin_name ) . '"';
		$text = __( 'Activate', 'plugin' );
	} else {
		$attr = 'class="install-now button"';

		if ( ! empty( $install['url'] ) ) {
			$attr .= ' href="' . esc_url( $install['url'] ) . '"';
		} else {
			$attr .= ' href="' . wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $install['slug'] ), 'install-plugin_' . $install['slug'] ) . '"';
		}

		$text = __( 'Install Now', 'plugin' );
	}
	return "<a {$attr}>{$text}</a>";
}


?>
<div class="kco-addons">
	<p>These are other plugins from Krokedil that work well with the plugin Klarna Checkout.</p>
	<div class='kco-addons-cards'>
		<div class="kco-addon-card">
			<img class="kco-addon-card-image" src="https://krokedil.com/wp-content/uploads/sites/3/2020/11/kom-chosen-960x544.jpg" alt="Get Klarna Order Management">
			<h3 class="kco-addon-card-title">Klarna Order Management</h3>
			<p class="kco-addon-card-description">Handle post purchase order management in Klarna's system directly from WooCommerce. This way you can save time and don't have to work in both systems simultaneously.</p>
			<a class="kco-addon-read-more" href="https://krokedil.com/product/klarna-order-management/" target="_blank">Read more</a>
			<p class="kco-addon-card-action"><span class='kco-addon-card-price'>Free</span>
			<!-- TODO: Escape output! -->
			<?php echo kco_plugin_action_button( KCO_KOM_PATH, array( 'slug' => 'klarna-order-management-for-woocommerce' ) ); ?></p>
		</div>

		<div class="kco-addon-card">
			<img class="kco-addon-card-image" src="https://krokedil.com/wp-content/uploads/sites/3/2020/11/osm-chosen-960x544.jpg" alt="Get Klarna On-Site Messaging">
			<h3 class="kco-addon-card-title">On-Site Messaging</h3>
			<p class="kco-addon-card-description">On-Site Messaging is easy and simple to integrate, providing tailored messaging ranging from generic banners to promote your partnership with Klarna and availability of financing to personalized credit promotion on product or cart pages.</p>
			<a class="kco-addon-read-more" href="https://krokedil.com/product/on-site-messaging-for-woocommerce/" target="_blank">Read more</a>
			<p class="kco-addon-card-action"><span class='kco-addon-card-price'>Free</span>
			<?php echo kco_plugin_action_button( KCO_OSM_PATH, array( 'url' => 'https://krokedil.com/product/klarna-on-site-messaging/?utm_source=kco&utm_medium=wp-admin&utm_campaign=add-ons' ) ); ?></p>
		</div>
	</div>
</div>

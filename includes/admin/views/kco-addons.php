<?php
/**
 * Klarna add-on tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hides the WooCommerce save button for the settings page.
$GLOBALS['hide_save_button'] = true;

define( 'KCO_OSM_PATH', 'klarna-onsite-messaging-for-woocommerce/klarna-onsite-messaging-for-woocommerce.php' );
define( 'KCO_KOM_PATH', 'klarna-order-management-for-woocommerce/klarna-order-management-for-woocommerce.php' );
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

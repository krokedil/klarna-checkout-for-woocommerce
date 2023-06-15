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
	<p><?php esc_html_e( 'These are other plugins from Krokedil that work well with the plugin Klarna Checkout.', 'klarna-checkout-for-woocommerce' ); ?></p>
	<div class='kco-addons-cards'>
		<?php foreach ( $addons as $addon ) : ?>
		<div class="kco-addon-card">
			<img class="kco-addon-card-image" src="<?php echo esc_attr( $addon['image'] ); ?>" alt="<?php echo esc_attr( $addon['description'] ); ?>">
			<h3 class="kco-addon-card-title"><?php echo esc_attr( $addon['title'] ); ?></h3>
			<p class="kco-addon-card-description"><?php echo esc_attr( $addon['description'] ); ?></p>
			<a class="kco-addon-read-more" href="<?php echo esc_attr( $addon['product_url'] ); ?>" target="_blank"><?php echo esc_attr( $addon['button'] ); ?></a>
			<p class="kco-addon-card-action"><span class='kco-addon-card-price'><?php echo esc_attr( ( $addon['free'] ? __( 'Free', 'klarna-checkout-for-woocommerce' ) : $addon['price'] ) ); ?></span>
			<?php
			echo kco_plugin_action_button(
				$addon['plugin_slug'],
				array(
					'plugin_url'  => $addon['plugin_url'],
					'plugin_slug' => $addon['plugin_slug'],
					'free'        => $addon['free'] ?? '',
					'product_url' => $addon['product_url'],
				)
			)
			?>
			</p>
		</div>
		<?php endforeach; ?>
		<div class="kco-addon-card placeholder">
			<img class="kco-addon-card-image" src="https://s3-eu-west-1.amazonaws.com/krokedil-checkout-addons/images/kco/klarna-icon-thumbnail.jpg" alt="">
			<h3 class="kco-addon-card-title"><?php esc_html_e( 'Coming soon', 'klarna-checkout-for-woocommerce' ); ?></h3>
			<p class="kco-addon-card-description"><?php esc_html_e( 'We are working on more add-ons. Make sure to keep an eye on this page for updates.', 'klarna-checkout-for-woocommerce' ); ?></p>
		</div>
	</div>
</div>

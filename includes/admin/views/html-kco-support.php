<?php
/**
 * Klarna support tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hides the WooCommerce save button for the settings page.
$GLOBALS['hide_save_button'] = true;

?>
<div id='kco-support'>
	<p><?php esc_html_e( 'Before opening a support ticket, please make sure you have read the relevant plugin resources for a solution to your problem:', 'klarna-checkout-for-woocommerce' ); ?></p>
	<ul>
		<?php
		foreach ( $links as $item ) {
			echo '<li><a href="' . esc_url( $item['href'] ) . '" target="' . esc_attr( $item['target'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
		}
		?>
	</ul>
	<p><?php echo sprintf( __( "If you have questions regarding a certain purchase, you're welcome to contact <a href='https://klarna.com/merchant-support' target='_blank'>Klarna</a>.", 'klarna-checkout-for-woocommerce' ) ); ?></p>
	<p><?php echo sprintf( __( "If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href='https://krokedil.com/support/' target='_blank'>Krokedil</a>, the plugin's developer.", 'klarna-checkout-for-woocommerce' ) ); ?></p>
</div>

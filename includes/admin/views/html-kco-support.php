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
		<li><a href="<?php echo esc_attr( $links['General information']['href'] ); ?>" target="_blank"><?php esc_html_e( 'General information', 'klarna-checkout-for-woocommerce' ); ?></a></li>
		<li><a href="<?php echo esc_attr( $links['Technical documentation']['href'] ); ?>" target="_blank"><?php esc_html__( 'Technical documentation', 'klarna-checkout-for-woocommerce' ); ?></a></li>
		<li><a href="<?php echo esc_attr( $links['General support information']['href'] ); ?>" target="_blank"><?php esc_html_e( 'General support information', 'klarna-checkout-for-woocommerce' ); ?></a></li>
	</ul>
	<p><?php echo sprintf( esc_html__( "If you have questions regarding a certain purchase, you're welcome to contact <a href='%s' target='_blank'>Klarna</a>.", 'klarna-checkout-for-woocommerce' ), esc_attr( $links['Klarna']['href'] ) ); ?></p>
	<p><?php echo sprintf( esc_html__( "If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href='%s' target='_blank'>Krokedil</a>, the plugin's developer.", 'klarna-checkout-for-woocommerce' ), esc_attr( $links['Krokedil']['href'] ) ); ?></p>
</div>

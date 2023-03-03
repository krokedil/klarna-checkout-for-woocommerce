<?php
/**
 * Klarna support tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id='kco-support'>
	<p>Before opening a support ticket, please make sure you have read the relevant plugin resources for a solution to your problem.</p>
	<ul>
		<li><a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/" target="_blank">General information</a></li>
		<li><a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/" target="_blank">Technical documentation</a></li>
		<li><a href="https://docs.krokedil.com/krokedil-general-support-info/" target="_blank">General support information</a></li>
	</ul>
	<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="https://klarna.com/merchant-support">Klarna.</a></p>
	<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href="<?php echo false !== strpos( get_locale(), 'sv' ) ? 'https://krokedil.se/support/' : 'https://krokedil.com/support/'; ?>">Krokedil</a>, the plugin's developer.</p>
</div>

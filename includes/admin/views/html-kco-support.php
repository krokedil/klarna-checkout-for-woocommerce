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
	<p>Before opening a support ticket, please make sure you have read the relevant plugin resources for a solution to
		your problem:</p>
	<ul>
		<li><a href="<?php echo $links['General information']['href']; ?>" target="_blank">General information</a></li>
		<li><a href="<?php echo $links['Technical documentation']['href']; ?>" target="_blank">Technical documentation</a></li>
		<li><a href="<?php echo $links['General support information']['href']; ?>" target="_blank">General support information</a></li>
	</ul>
	<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="<?php echo $links['Klarna']['href']; ?>" target="_blank">Klarna.</a></p>
	<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href="<?php echo $links['Krokedil']['href']; ?>" target="_blank">Krokedil</a>, the plugin's developer.</p>
</div>

<?php
/**
 * Klarna support tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$message = get_transient( 'krokedil_support_form_message' );
if ( $message ) {
	echo $message;
	delete_transient( 'krokedil_support_form_message' );
}

$system_report = new WC_Admin_Status();

$logs = array(
	'kco'   => Krokedil_Support_Form::get_plugin_logs( 'klarna-checkout-for-woocommerce' ),
	'kom'   => Krokedil_Support_Form::get_plugin_logs( 'klarna-order-management-for-woocommerce' ),
	'fatal' => Krokedil_Support_Form::get_plugin_logs( 'fatal-errors' ),
	'all'   => Krokedil_Support_Form::get_plugin_logs(),
);

// Hides the WooCommerce save button for the settings page.
$GLOBALS['hide_save_button'] = true;

?>
<div style="position: absolute; top: -9999px; left: -9999px;">
	<?php echo $system_report->status_report(); ?>
	<input type="hidden" name="system-report" id="system-report">
</div>
<div id='kco-support'>
	<p>Before opening a support ticket, please make sure you have read the relevant plugin resources for a solution to your problem:</p>
	<ul>
		<li><a href="<?php echo $links['General information']['href']; ?>" target="_blank">General information</a></li>
		<li><a href="<?php echo $links['Technical documentation']['href']; ?>" target="_blank">Technical documentation</a></li>
		<li><a href="<?php echo $links['General support information']['href']; ?>" target="_blank">General support information</a></li>
	</ul>
	<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="<?php echo $links['Klarna']['href']; ?>" target="_blank">Klarna.</a></p>
	<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to contact <a href="<?php echo $links['Krokedil']['href']; ?>" target="_blank">Krokedil</a>, the plugin's developer.</p>
		<div id='krokedil-support-form'>
		<h1 class="krokedil-support-form title">Technical support request form</h1>
		<div class="krokedil-support-form">
			<h3>E-mail <span class="required">(required)</span></h3>
			<p>
				<label for="email">Replies will be sent to this address, please check for typos.</label>
				<input id="email" name="email" type="email" size="50"
				<?php
				if ( ! empty( $_POST['email'] ) ) :
					echo 'value="' . esc_html( $_POST['email'] ) . '"'; endif
				?>
				required>
			</p>
			<h3>Subject <span class="required">(required)</span></h3>
			<p>
				<label for="subject">Summarize your question in a few words.</label>
				<input id="subject" name="subject" type="text" size="50"
				<?php
				if ( ! empty( $_POST['subject'] ) ) :
					echo 'value="' . esc_html( $_POST['subject'] ) . '"'; endif
				?>
				required>
			</p>
			<h3>How can we help? <span class="required">(required)</span></h3>
			<p>
				<label for="description">Describe the issue you are having as detailed as possible.</label>
				<?php
				$textarea = '';
				if ( ! empty( $_POST['description'] ) ) {
					$textarea = esc_html( $_POST['description'] );
				}
				?>
				<textarea id="description" name="description" rows="4" cols="70" required><?php echo $textarea; ?></textarea>
			</p>
			<h3>WooCommerce system status report <span class="woocommerce-help-tip"></span></h3>
			<p>This report contains information about your website that is very useful for troubleshooting issues.</p>
			<div class="system-report-wrapper">
				<input name="system-report-include" type="checkbox">
				<label id="system-report-include">Attach this store's WooCommerce system status report.</label>
				<a href="#" class="system-report-action">View report</a>
				<textarea class="system-report-content" readonly></textarea>
			</div>
			<h3>WooCommerce logs <span class="woocommerce-help-tip"></span></h3>
			<p>
				If you have logging enabled, providing relevant logs can be very useful for troubleshooting (e.g., issue with a specific order).
			</p>
			<div class="woocommerce-log">
				<?php
				Krokedil_Support_Form::output_log_selector( 'Klarna Checkout', $logs['kco'] );
				Krokedil_Support_Form::output_log_selector( 'Klarna Order Management', $logs['kom'] );
				Krokedil_Support_Form::output_log_selector( 'Fatal error log', $logs['fatal'] );
				Krokedil_Support_Form::output_log_selector( 'Additional log', $logs['all'] );

				echo '<a class="additional-log">+Add an additional log</a>';
				for ( $i = 1; $i <= count( $logs ); $i++ ) {
					Krokedil_Support_Form::output_log_selector( "Additional log {$i}", $logs['all'] );
					if ( $i < count( $logs ) ) {
						echo "<a class='additional-log-{$i}'>+Add an additional log</a>";
					}
				}
				?>
			</div>
			<div class="uploads">
				<h1>Upload screenshots</h1>
				<p>Add any relevant screenshots, e.g., of an order related to the problem.</p>
				<p>Please submit screenshots from both the WooCommerce admin, and the payment provider's portal with theorder notes clearly visible in the screenshot.</p>
				<input type="file" name="screenshots[]" id="screenshot-picker" accept=".jpeg,.jpg,.png,.gif" multiple>
				<input type="submit" value="Submit support ticket" name="submit" class="button button-primary button-large">
			</div>
			<input type="hidden" name="krokedil_support_form_nonce" value="<?php echo wp_create_nonce( 'krokedil_support_form_nonce' ); ?>">
		</div>
	</div>
</div>

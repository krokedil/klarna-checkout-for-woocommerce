<?php
/**
 * Klarna support tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function get_plugin_logs( $plugin_name ) {
	$logs = array();
	foreach ( WC_Admin_Status::scan_log_files() as $log => $path ) {
		if ( strpos( $log, $plugin_name ) !== false ) {
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
$logs = array(
	'kco'   => get_plugin_logs( 'klarna-checkout-for-woocommerce' ),
	'kom'   => get_plugin_logs( 'klarna-order-management-for-woocommerce' ),
	'fatal' => get_plugin_logs( 'fatal-errors' ),
);

?>
<div style="position: absolute; top: -9999px; left: -9999px;">
<?php
	$system_report = new WC_Admin_Status();
	echo $system_report->status_report();
?>
</div>

<div id='kco-support'>
	<p>Before opening a support ticket, please make sure you have read the relevant plugin resources for a solution to
		your problem.</p>
	<ul>
		<li><a href="https://krokedil.com/product/klarna-checkout-for-woocommerce/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar"
				target="_blank">General information</a></li>
		<li><a href="https://docs.krokedil.com/klarna-checkout-for-woocommerce/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar"
				target="_blank">Technical documentation</a></li>
		<li><a href="https://docs.krokedil.com/krokedil-general-support-info/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar"
				target="_blank">General support information</a></li>
	</ul>
	<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="">Klarna.</a></p>
	<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to
		contact <a href="">Krokedil</a>, the plugin's developer.</p>
	<style>
		.support-form input {
			display: block;
		}

		.support-form .required {
			font-size: 0.7em;
			color: black;
		}

		.support-form textarea#issue {
			width: 100%;
		}

		.support-form .system-report-wrapper,
		.log-wrapper {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
		}

		.system-report-wrapper a,
		.log-wrapper a {
			margin-left: 2em;
		}

		.system-report-wrapper a::after,
		.log-wrapper a::after {
			display: inline-block;
			content: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23555%22%2F%3E%3C%2Fsvg%3E");
			transform: scale(0.7) translateY(30%);
			filter: sepia(100%) hue-rotate(190deg) saturate(500%);
		}

		.system-report-wrapper a:focus,
		.log-wrapper a:focus {
			outline: none;
			border: none;
			box-shadow: none;
		}

		.system-report-wrapper textarea,
		.log-wrapper textarea {
			display: none;
			flex-basis: 100%;
			font-family: monospace;
			width: 100%;
			margin: 1em 0;
			height: 150px;
			padding-left: 10px;
			border-radius: 0;
			resize: none;
			font-size: 12px;
			line-height: 20px;
			outline: 0;
		}

		.woocommerce-log {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
		}

		.woocommerce-log h3 {
			flex-basis: 100%;
		}

		.woocommerce-log .select2-container {
			max-width: 60%;
			min-width: 60%;
		}

		.woocommerce-log a {
			margin-left: 2em;
		}
	</style>
	<div class="support-form">
		<h1>Technical support request form</h1>
		<h2>E-mail <span class="required">(required)</span></h2>
		<p>
			<label for="email">Replies will be sent to this address, please check for typos.</label>
			<input id="email" name="email" type="email" size="50" required>
		</p>
		<h2>Subject <span class="required">(required)</span></h2>
		<p>
			<label for="subject">Summarize your question in a few words.</label>
			<input id="subject" name="subject" type="text" size="50" required>
		</p>
		<h2>How can we help? <span class="required">(required)</span></h2>
		<p>
			<label for="issue">Describe the issue you are having as detailed as possible.</label>
			<textarea id="issue" name="issue" rows="4" cols="70"></textarea>
		</p>
		<h2>WooCommerce system status report <span class="woocommerce-help-tip"></span></h2>
		<p>This report contains information about your website that is very useful for troubleshooting issues.</p>
		<div class="system-report-wrapper">
			<input type="checkbox" id="system-report" checked>
			<!-- TODO: Handle the checkbox event. -->
			<label id="system-report">Attach this store's WooCommerce system status report.</label>
			<a href="#" class="system-report-action">View report</a>
			<textarea class="system-report-content" readonly></textarea>
		</div>
		<h2>WooCommerce logs <span class="woocommerce-help-tip"></span></h2>
		<p>
			If you have logging enabled, providing relevant logs can be very useful for troubleshooting (e.g., issue
			with a specific order).
		</p>
		<div class="woocommerce-log">
			<!-- TODO: Include the WC_AJAX::endpoint in an enqueued script. -->
			<div class="log-wrapper">
				<h3>Klarna Checkout</h3>
				<!-- TODO: Handle sitution where there are no log entries: show no logs available text. -->
				<select class="kco-log-option wc-enhanced-select" name="kco-log">
					<?php foreach ( $logs['kco'] as $date => $path ) : ?>
						<option name="kco-log" value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
					<?php endforeach; ?>
				</select>
				<a class="view-log">View log</a>
				<textarea class="log-content" readonly></textarea>
			</div>
			<div class="log-wrapper">
				<h3>Klarna Order Management</h3>
				<!-- TODO: Handle sitution where there are no log entries: show no logs available text. -->
				<select class="kco-log-option wc-enhanced-select" name="kco-log">
					<?php foreach ( $logs['kom'] as $date => $path ) : ?>
						<option name="kco-log" value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
					<?php endforeach; ?>
				</select>
				<a class="view-log">View log</a>
				<textarea class="log-content" readonly></textarea>
			</div>
			<div class="log-wrapper">
				<h3>Fatal error log</h3>
				<!-- TODO: Handle sitution where there are no log entries: show no logs available text. -->
				<select class="kco-log-option wc-enhanced-select" name="kco-log">
					<?php foreach ( $logs['fatal'] as $date => $path ) : ?>
						<option name="kco-log" value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
					<?php endforeach; ?>
				</select>
				<a class="view-log">View log</a>
				<textarea class="log-content" readonly></textarea>
			</div>
		</div>
		<div class="uploads">
			<h1>Upload screenshots</h1>
			<p>Add any relevant screenshots, e.g., of an order related to the problem.</p>
			<p>Please submit screenshots from both the WooCommerce admin, and the payment provider's portal with the
				order notes clearly visible in the screenshot.</p>
			<form action method="post" enctype="multipart/form-data">
				<input type="file" name="screenshots[]" id="screenshot-picker" accept=".jpeg,.jpg,.png,.gif" multiple>
				<input type="submit" value="Upload images" name="submit">
		</div>
	</div>
</div>

<?php
/**
 * Klarna support tab in the settings page.
 *
 * @package  Klarna_Checkout/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $_POST['submit'] ) ) {

	$send = true;
	if ( ! is_email( $_POST['email'] ) ) {
		$send = false;
	}

	$from    = sanitize_email( $_POST['email'] );
	$to      = 'support@krokedil.se';
	$subject = sanitize_text_field( $_POST['subject'] );
	$message = sanitize_textarea_field( $_POST['description'] );
	$headers = array(
		'From: ' . $from,
		'Reply-To: ' . $from,
		'Content-Type: text/html; charset=UTF-8',
	);


	$attachment = array(
		WC_LOG_DIR . sanitize_text_field( $_POST['klarna-checkout'] ),
		WC_LOG_DIR . sanitize_text_field( $_POST['klarna-order-management'] ),
		WC_LOG_DIR . sanitize_text_field( $_POST['fatal-error-log'] ),
		WC_LOG_DIR . sanitize_text_field( $_POST['additional-log'] ),
	);

	foreach ( $_POST as $key => $value ) {
		if ( strpos( $key, 'additional-log-' ) !== false ) {
			$attachment[] = WC_LOG_DIR . sanitize_text_field( $value );
		}
	}

	$attachment = array_filter(
		$attachment,
		function( $path ) {
			return file_exists( $path ) && is_readable( $path );
		},
	);

	if ( 'on' === $_POST['system-report-include'] ) {
		$system_report = sanitize_textarea_field( $_POST['system-report'] );
		$filename      = 'system-report-' . date( 'Y-m-d' ) . '.txt';
		$filepath      = WP_CONTENT_DIR . '/uploads/' . $filename;

		if ( file_put_contents( $filepath, $system_report ) ) {
			$attachment[] = $filepath;
		}
	}

	if ( $send ) {
		// wp_mail( $to, $subject, $message, $headers, $attachment );
	}
}

function kco_log_wrapper( $title, $log ) {
	// Normalized name intended to be form and CSS friendly.
	$name = esc_attr( str_replace( ' ', '-', strtolower( $title ) ) );
	?>
	<div class="log-wrapper <?php esc_attr_e( $name ); ?>">
		<h3><?php echo $title; ?></h3>
		<?php
		if ( empty( $log ) ) {
			echo '<p class="' . $name . '">No log available.</p>';
		}
		?>
		<select class="kco-log-option wc-enhanced-select <?php echo $name; ?> " name="<?php echo $name; ?>">
			<?php foreach ( $log as $date => $path ) : ?>
				<option name="<?php echo $name; ?>" value="<?php esc_attr_e( $path ); ?>"><?php echo esc_html( "{$path} ({$date})" ); ?></option>
			<?php endforeach; ?>
		</select>
		<a class="view-log">View log</a>
		<textarea class="log-content" readonly></textarea>
	</div>
	<?php
}

// Hides the WooCommerce save button for the settings page.
$GLOBALS['hide_save_button'] = true;

$logs = array(
	'kco'   => kco_get_plugin_logs( 'klarna-checkout-for-woocommerce' ),
	'kom'   => kco_get_plugin_logs( 'klarna-order-management-for-woocommerce' ),
	'fatal' => kco_get_plugin_logs( 'fatal-errors' ),
	'all'   => kco_get_plugin_logs(),
);

$system_report = new WC_Admin_Status();
?>
<div style="position: absolute; top: -9999px; left: -9999px;">
	<?php echo $system_report->status_report(); ?>
	<input type="hidden" name="system-report" id="system-report">
</div>

<div id='kco-support'>
	<p>If you have questions regarding a certain purchase, you're welcome to contact <a href="">Klarna.</a></p>
	<p>If you have <b>technical questions or questions regarding configuration</b> of the plugin, you're welcome to
		contact <a href="">Krokedil</a>, the plugin's developer.</p>
	<h1 class="support-form title">Technical support request form</h1>
	<div class="support-form">
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
			<label for="description">Describe the issue you are having as detailed as possible.</label>
			<textarea id="description" name="description" rows="4" cols="70" required></textarea>
		</p>
		<h2>WooCommerce system status report <span class="woocommerce-help-tip"></span></h2>
		<p>This report contains information about your website that is very useful for troubleshooting issues.</p>
		<div class="system-report-wrapper">
			<input name="system-report-include" type="checkbox" id="system-report-include" checked>
			<label id="system-report-include">Attach this store's WooCommerce system status report.</label>
			<a href="#" class="system-report-action">View report</a>
			<textarea class="system-report-content" readonly></textarea>
		</div>
		<h2>WooCommerce logs <span class="woocommerce-help-tip"></span></h2>
		<p>
			If you have logging enabled, providing relevant logs can be very useful for troubleshooting (e.g., issue
			with a specific order).
		</p>
		<div class="woocommerce-log">
			<?php
			kco_log_wrapper( 'Klarna Checkout', $logs['kco'] );
			kco_log_wrapper( 'Klarna Order Management', $logs['kom'] );
			kco_log_wrapper( 'Fatal error log', $logs['fatal'] );
			kco_log_wrapper( 'Additional log', $logs['all'] );

			echo '<a class="additional-log">+Add an additional log</a>';
			for ( $i = 1; $i <= 5; $i++ ) {
				kco_log_wrapper( "Additional log {$i}", $logs['all'] );
				if ( $i < 5 ) {
					echo "<a class='additional-log-{$i}'>+Add an additional log</a>";
				}
			}
			?>
		</div>
		<div class="uploads">
			<h1>Upload screenshots</h1>
			<p>Add any relevant screenshots, e.g., of an order related to the problem.</p>
			<p>Please submit screenshots from both the WooCommerce admin, and the payment provider's portal with the
				order notes clearly visible in the screenshot.</p>
				<input type="file" name="screenshots[]" id="screenshot-picker" accept=".jpeg,.jpg,.png,.gif" multiple>
				<input type="submit" value="Submit support ticket" name="submit" class="button button-primary button-large">
		</div>
	</div>
</div>

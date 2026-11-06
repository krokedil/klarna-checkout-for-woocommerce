<?php
namespace Krokedil\KustomCheckout\InPersonPayments\Settings;

use Krokedil\KustomCheckout\InPersonPayments\Devices;
use Krokedil\KustomCheckout\InPersonPayments\InPersonPayments;
use Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetLocations;
use Krokedil\KustomCheckout\InPersonPayments\Request\Post\RequestPostEnrollment;
use Krokedil\KustomCheckout\InPersonPayments\Request\Put\RequestPutDevice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and serves the In-Person Payments part of the gateway settings page.
 */
class Admin {

	/**
	 * The capability required to read devices and create enrollment codes.
	 *
	 * @var string
	 */
	public const ADMIN_CAPABILITY = 'manage_woocommerce';

	/**
	 * The nonce action shared by both admin-ajax endpoints.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'kco_ipp_admin';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_generate_kco_ipp_location_html', array( $this, 'location_field_html' ), 10, 4 );
		add_filter( 'woocommerce_generate_kco_ipp_devices_html', array( $this, 'devices_field_html' ), 10, 3 );
		add_filter( 'woocommerce_generate_kco_ipp_enrollment_html', array( $this, 'enrollment_field_html' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_kco', array( Devices::class, 'flush' ), 20 );

		add_action( 'wp_ajax_kco_ipp_refresh_devices', array( $this, 'ajax_refresh_devices' ) );
		add_action( 'wp_ajax_kco_ipp_create_enrollment', array( $this, 'ajax_create_enrollment' ) );
		add_action( 'wp_ajax_kco_ipp_rename_device', array( $this, 'ajax_rename_device' ) );
	}

	/**
	 * Enqueue the settings page script.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook || ! $this->is_gateway_settings_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'kco_ipp_admin',
			plugins_url( 'src/InPersonPayments/assets/css/kustom-ipp-admin' . $suffix . '.css', KCO_WC_MAIN_FILE ),
			array(),
			KCO_WC_VERSION
		);

		wp_enqueue_script(
			'kco_ipp_admin',
			plugins_url( 'src/InPersonPayments/assets/js/kustom-ipp-admin' . $suffix . '.js', KCO_WC_MAIN_FILE ),
			array( 'jquery', 'wc-backbone-modal' ),
			KCO_WC_VERSION,
			true
		);

		wp_localize_script(
			'kco_ipp_admin',
			'kco_ipp_admin_params',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
				'error_message' => __( 'Could not reach Kustom. Please try again.', 'klarna-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Render the location picker.
	 *
	 * The ids are not shown anywhere in the Merchant Portal, so they have to be read
	 * off the API. A lookup that fails falls back to a plain text input rather than
	 * leaving the merchant with no way to set the location at all.
	 *
	 * @param string $html The field HTML built so far.
	 * @param string $key The field key.
	 * @param array  $data The field definition.
	 * @param object $gateway The settings API object rendering the field.
	 * @return string
	 */
	public function location_field_html( $html, $key, $data, $gateway ) {
		if ( ! InPersonPayments::is_enabled() ) {
			return $html . $this->disabled_field_html( $data );
		}

		$locations = $this->get_locations();
		$error     = is_wp_error( $locations ) ? $locations->get_error_message() : '';
		$selected  = (string) $gateway->get_option( $key );
		$field_key = $gateway->get_field_key( $key );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $data['title'] ?? '' ); ?></label>
			</th>
			<td class="forminp">
				<?php if ( is_wp_error( $locations ) ) : ?>
					<input type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>"
						value="<?php echo esc_attr( $selected ); ?>" class="input-text regular-input" autocomplete="off" />
					<p class="kco-ipp-error"><?php echo esc_html( $error ); ?></p>
				<?php else : ?>
					<select name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" class="wc-enhanced-select">
						<option value=""<?php selected( '', $selected ); ?>>
							<?php esc_html_e( 'No location (single-location accounts only)', 'klarna-checkout-for-woocommerce' ); ?>
						</option>
						<?php foreach ( $this->location_options( $locations, $selected ) as $id => $label ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>"<?php selected( $id, $selected ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<p class="description"><?php echo esc_html( $data['description'] ?? '' ); ?></p>
			</td>
		</tr>
		<?php
		return $html . ob_get_clean();
	}

	/**
	 * Build the option list, keeping a saved location that the API no longer returns
	 * so that opening the page cannot silently drop it.
	 *
	 * @param array  $locations The locations from the API.
	 * @param string $selected The currently saved location id.
	 * @return array
	 */
	private function location_options( $locations, $selected ) {
		$options = array();

		foreach ( $locations as $location ) {
			$id = $location['id'] ?? '';
			if ( '' === $id ) {
				continue;
			}

			$name  = trim( $location['name'] ?? '' );
			$city  = trim( $location['city'] ?? '' );
			$label = trim( '' === $city || '' === $name ? $name . $city : "{$name}, {$city}" );

			$options[ $id ] = '' === $label ? $id : $label;
		}

		if ( '' !== $selected && ! isset( $options[ $selected ] ) ) {
			/* translators: %s: the location id saved in the settings. */
			$options[ $selected ] = sprintf( __( 'Unknown location (%s)', 'klarna-checkout-for-woocommerce' ), $selected );
		}

		return $options;
	}

	/**
	 * Render the devices table field.
	 *
	 * @param string $html The field HTML built so far.
	 * @param string $key The field key.
	 * @param array  $data The field definition.
	 * @return string
	 */
	public function devices_field_html( $html, $key, $data ) {
		if ( ! InPersonPayments::is_enabled() ) {
			return $html . $this->disabled_field_html( $data );
		}

		$devices = $this->get_devices();
		$error   = is_wp_error( $devices ) ? $devices->get_error_message() : '';
		$table   = is_wp_error( $devices ) ? $this->devices_table( array() ) : $this->devices_table( $devices );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $data['title'] ?? '' ); ?></label>
			</th>
			<td class="forminp">
				<div id="kco-ipp-devices" data-key="<?php echo esc_attr( $key ); ?>">
					<p class="kco-ipp-error"<?php echo '' === $error ? ' style="display:none"' : ''; ?>>
						<?php echo esc_html( $error ); ?>
					</p>
					<div class="kco-ipp-devices-table"><?php echo $table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in devices_table(). ?></div>
					<p>
						<button type="button" class="button kco-ipp-refresh-devices">
							<?php esc_html_e( 'Refresh device list', 'klarna-checkout-for-woocommerce' ); ?>
						</button>
					</p>
				</div>
				<?php $this->device_modal_template(); ?>
			</td>
		</tr>
		<?php
		return $html . ob_get_clean();
	}

	/**
	 * Render the enrollment code field.
	 *
	 * @param string $html The field HTML built so far.
	 * @param string $key The field key.
	 * @param array  $data The field definition.
	 * @return string
	 */
	public function enrollment_field_html( $html, $key, $data ) {
		if ( ! InPersonPayments::is_enabled() ) {
			return $html;
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $data['title'] ?? '' ); ?></label>
			</th>
			<td class="forminp">
				<div id="kco-ipp-enrollment" data-key="<?php echo esc_attr( $key ); ?>">
					<p class="description">
						<?php esc_html_e( 'Generate a code, then type it into the Kustom POS app on the device you want to pair.', 'klarna-checkout-for-woocommerce' ); ?>
					</p>
					<p>
						<button type="button" class="button kco-ipp-create-enrollment">
							<?php esc_html_e( 'Generate enrollment code', 'klarna-checkout-for-woocommerce' ); ?>
						</button>
					</p>
					<p class="kco-ipp-error" style="display:none"></p>
					<p class="kco-ipp-code" style="display:none"><code></code> <span class="description"></span></p>
				</div>
				<?php $this->enrollment_modal_template(); ?>
			</td>
		</tr>
		<?php
		return $html . ob_get_clean();
	}

	/**
	 * Render a placeholder in place of a field that needs the feature switched on.
	 *
	 * @param array $data The field definition.
	 * @return string
	 */
	private function disabled_field_html( $data ) {
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $data['title'] ?? '' ); ?></label>
			</th>
			<td class="forminp">
				<p class="description">
					<?php esc_html_e( 'Enable in-person payments and save the settings to pair a device.', 'klarna-checkout-for-woocommerce' ); ?>
				</p>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * The Backbone template for the device dialog.
	 *
	 * @return void
	 */
	private function device_modal_template() {
		?>
		<script type="text/template" id="tmpl-kco-ipp-device-modal">
			<div class="wc-backbone-modal">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1><?php esc_html_e( 'Edit device', 'klarna-checkout-for-woocommerce' ); ?></h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text"><?php esc_html_e( 'Close', 'klarna-checkout-for-woocommerce' ); ?></span>
							</button>
						</header>
						<article>
							<form action="" method="post">
								<input type="hidden" name="device_id" value="{{ data.id }}" />
								<p class="form-field">
									<label for="kco-ipp-device-name"><?php esc_html_e( 'Device name', 'klarna-checkout-for-woocommerce' ); ?></label>
									<input type="text" id="kco-ipp-device-name" name="name" class="input-text regular-input"
										value="{{ data.name }}" autocomplete="off" />
									<span class="description">
										<?php esc_html_e( 'The name shown here and in the Kustom Merchant Portal.', 'klarna-checkout-for-woocommerce' ); ?>
									</span>
								</p>
							</form>
						</article>
						<footer>
							<div class="inner">
								<button id="btn-ok" class="button button-primary button-large">
									<?php esc_html_e( 'Save device', 'klarna-checkout-for-woocommerce' ); ?>
								</button>
							</div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
		<?php
	}

	/**
	 * The Backbone template for the enrollment dialog.
	 *
	 * @return void
	 */
	private function enrollment_modal_template() {
		?>
		<script type="text/template" id="tmpl-kco-ipp-enrollment-modal">
			<div class="wc-backbone-modal">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1><?php esc_html_e( 'Generate enrollment code', 'klarna-checkout-for-woocommerce' ); ?></h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text"><?php esc_html_e( 'Close', 'klarna-checkout-for-woocommerce' ); ?></span>
							</button>
						</header>
						<article>
							<form action="" method="post">
								<p class="form-field">
									<label for="kco-ipp-ttl"><?php esc_html_e( 'Code valid for', 'klarna-checkout-for-woocommerce' ); ?></label>
									<select id="kco-ipp-ttl" name="ttl">
										<?php foreach ( $this->ttl_options() as $value => $label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<span class="description">
										<?php esc_html_e( 'Anything longer than two hours returns a long code that has to be copied rather than typed.', 'klarna-checkout-for-woocommerce' ); ?>
									</span>
								</p>
							</form>
						</article>
						<footer>
							<div class="inner">
								<button id="btn-ok" class="button button-primary button-large">
									<?php esc_html_e( 'Generate code', 'klarna-checkout-for-woocommerce' ); ?>
								</button>
							</div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
		<?php
	}

	/**
	 * The TTLs offered in the dialog.
	 *
	 * @return array
	 */
	private function ttl_options() {
		return array(
			'TWO_HOURS'         => __( '2 hours (short, typeable code)', 'klarna-checkout-for-woocommerce' ),
			'TWENTY_FOUR_HOURS' => __( '24 hours', 'klarna-checkout-for-woocommerce' ),
			'FORTY_EIGHT_HOURS' => __( '48 hours', 'klarna-checkout-for-woocommerce' ),
			'SEVENTY_TWO_HOURS' => __( '72 hours', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Answer the device list refresh.
	 *
	 * @return void
	 */
	public function ajax_refresh_devices() {
		$this->verify_request();

		$devices = $this->get_devices();
		if ( is_wp_error( $devices ) ) {
			wp_send_json_error( array( 'message' => $devices->get_error_message() ) );
		}

		wp_send_json_success( array( 'html' => $this->devices_table( $devices ) ) );
	}

	/**
	 * Create an enrollment code.
	 *
	 * @return void
	 */
	public function ajax_create_enrollment() {
		$this->verify_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_request() checks the nonce.
		$ttl = isset( $_POST['ttl'] ) ? sanitize_text_field( wp_unslash( $_POST['ttl'] ) ) : '';

		$response = ( new RequestPostEnrollment( array( 'ttl' => $ttl ) ) )->request();
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = $response['enrollment_code'] ?? '';
		if ( '' === $code ) {
			wp_send_json_error( array( 'message' => __( 'Kustom did not return an enrollment code.', 'klarna-checkout-for-woocommerce' ) ) );
		}

		$expires = $this->format_timestamp( $response['expires_at'] ?? '' );

		wp_send_json_success(
			array(
				'code'    => $code,
				/* translators: %s: date and time the enrollment code expires. */
				'expires' => '' === $expires ? '' : sprintf( __( 'Expires %s.', 'klarna-checkout-for-woocommerce' ), $expires ),
			)
		);
	}

	/**
	 * Rename a paired device.
	 *
	 * @return void
	 */
	public function ajax_rename_device() {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verify_request() checks the nonce.
		$device_id = isset( $_POST['device_id'] ) ? sanitize_text_field( wp_unslash( $_POST['device_id'] ) ) : '';
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$name = trim( $name );
		if ( '' === $device_id || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'A device and a name are both required.', 'klarna-checkout-for-woocommerce' ) ) );
		}

		$devices = $this->get_devices();
		if ( is_wp_error( $devices ) ) {
			wp_send_json_error( array( 'message' => $devices->get_error_message() ) );
		}

		$device = Devices::find( $devices, $device_id );
		if ( null === $device ) {
			wp_send_json_error( array( 'message' => __( 'That device is no longer paired with this account.', 'klarna-checkout-for-woocommerce' ) ) );
		}

		$response = ( new RequestPutDevice(
			array(
				'device_id'   => $device_id,
				'name'        => mb_substr( $name, 0, 100 ),
				'metadata'    => $device['metadata'] ?? array(),
				'location_id' => $device['location_id'] ?? '',
			)
		) )->request();

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$devices = $this->get_devices();

		wp_send_json_success( array( 'html' => $this->devices_table( is_wp_error( $devices ) ? array() : $devices ) ) );
	}

	/**
	 * Fetch the merchant's devices, bypassing the checkout's cache so that the settings
	 * page always shows what Kustom currently has.
	 *
	 * @return array|\WP_Error
	 */
	private function get_devices() {
		return Devices::refresh();
	}

	/**
	 * Fetch the merchant's locations.
	 *
	 * @return array|\WP_Error
	 */
	private function get_locations() {
		$response = ( new RequestGetLocations() )->request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['content'] ?? array();
	}

	/**
	 * Build the devices table markup.
	 *
	 * @param array $devices The devices from the API.
	 * @return string
	 */
	private function devices_table( $devices ) {
		ob_start();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'klarna-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Platform', 'klarna-checkout-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Paired', 'klarna-checkout-for-woocommerce' ); ?></th>
					<th class="kco-ipp-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'klarna-checkout-for-woocommerce' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $devices ) ) : ?>
				<tr>
					<td colspan="4"><?php esc_html_e( 'No paired devices.', 'klarna-checkout-for-woocommerce' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $devices as $device ) : ?>
					<tr>
						<td><?php echo esc_html( $device['name'] ?? '' ); ?></td>
						<td><?php echo esc_html( $device['platform'] ?? '' ); ?></td>
						<td><?php echo esc_html( $this->format_timestamp( $device['created_at'] ?? '' ) ); ?></td>
						<td class="kco-ipp-actions">
							<button type="button" class="button button-small kco-ipp-edit-device"
								data-device-id="<?php echo esc_attr( $device['id'] ?? '' ); ?>"
								data-device-name="<?php echo esc_attr( $device['name'] ?? '' ); ?>">
								<?php esc_html_e( 'Edit', 'klarna-checkout-for-woocommerce' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format an API timestamp in the site's timezone.
	 *
	 * @param string $timestamp An ISO 8601 UTC timestamp.
	 * @return string
	 */
	private function format_timestamp( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		$time = strtotime( $timestamp );
		if ( false === $time ) {
			return '';
		}

		return wp_date( wc_date_format() . ' ' . wc_time_format(), $time );
	}

	/**
	 * Stop an admin-ajax request that is not an authorised settings page request.
	 *
	 * @return void
	 */
	private function verify_request() {
		if ( ! current_user_can( self::ADMIN_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'klarna-checkout-for-woocommerce' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	/**
	 * Whether the current request is the Kustom Checkout gateway settings page.
	 *
	 * @return bool
	 */
	private function is_gateway_settings_page() {
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which settings page is being viewed.

		return 'kco' === $section;
	}
}

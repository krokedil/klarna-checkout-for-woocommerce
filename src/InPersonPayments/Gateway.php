<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Request\Post\RequestPostSession;
use Krokedil\KustomCheckout\OrderManagement\Settings as OrderManagementSettings;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The in-person payment method: a salesperson picks a paired device at checkout and
 * the customer pays on it.
 */
class Gateway extends \WC_Payment_Gateway {

	/**
	 * The gateway id.
	 *
	 * @var string
	 */
	public const ID = 'kco_ipp';

	/**
	 * The field the chosen device is posted in.
	 *
	 * @var string
	 */
	public const DEVICE_FIELD = 'kco_ipp_device_id';

	/**
	 * The optional field the payment channel to preselect on the device is posted in.
	 *
	 * @var string
	 */
	public const CHANNEL_FIELD = 'kco_ipp_payment_channel';

	/**
	 * The payment channels a session can be preselected for.
	 *
	 * @var string[]
	 */
	public const PAYMENT_CHANNELS = array( 'CARD_PAYMENT', 'SCAN_TO_PAY' );

	/**
	 * Order meta keys.
	 *
	 * @var string
	 */
	public const SESSION_META = '_kco_ipp_session_id';
	public const DEVICE_META  = '_kco_ipp_device_id';
	public const STATUS_META  = '_kco_ipp_status';

	/**
	 * Where and by whom the sale was taken, as it was when the session was sent.
	 *
	 * @var string
	 */
	public const DEVICE_NAME_META = '_kco_ipp_device_name';
	public const LOCATION_META    = '_kco_ipp_location_id';
	public const REGISTER_META    = '_kco_ipp_cash_register_id';
	public const CHANNEL_META     = '_kco_ipp_payment_channel';
	public const STAFF_META       = '_kco_ipp_staff_id';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id = self::ID;

		// No admin title or description makes this a "shell" gateway, which WooCommerce leaves off its
		// payments settings list. It has no settings of its own: they live in the Kustom Checkout settings.
		$this->method_title       = '';
		$this->method_description = '';
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		// The tap leaves an ordinary Kustom order behind, which order management refunds.
		if ( OrderManagementSettings::is_enabled() ) {
			$this->supports[] = 'refunds';
		}

		$this->init_settings();

		$this->enabled = $this->get_option( 'enabled', 'no' );
		$this->title   = SettingsUtility::get_setting( 'ipp_title', __( 'In-Person Payment', 'klarna-checkout-for-woocommerce' ) );
	}

	/**
	 * The settings live in the Kustom Checkout gateway's own option, under `ipp_` keys,
	 * so that all Kustom configuration stays on one page.
	 *
	 * @return string
	 */
	public function get_option_key() {
		return 'woocommerce_kco_settings';
	}

	/**
	 * Read a setting, mapping the keys WooCommerce addresses a gateway by.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $empty_value The value to use when the setting is empty.
	 * @return mixed
	 */
	public function get_option( $key, $empty_value = null ) {
		return parent::get_option( self::setting_key( $key ), $empty_value );
	}

	/**
	 * Write a setting, so that the toggle on the payment methods list reaches the
	 * In-person payments setting rather than an option nothing reads.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $value The value to store.
	 * @return bool
	 */
	public function update_option( $key, $value = '' ) {
		$updated = parent::update_option( self::setting_key( $key ), $value );

		SettingsUtility::flush();

		return $updated;
	}

	/**
	 * Translate a gateway setting key to the key it is stored under.
	 *
	 * @param string $key The setting key.
	 * @return string
	 */
	private static function setting_key( $key ) {
		return 'enabled' === $key ? 'ipp_enabled' : $key;
	}

	/**
	 * Refund through the same order management the checkout gateway uses.
	 *
	 * @param int        $order_id The WooCommerce order id.
	 * @param float|null $amount The amount to refund.
	 * @param string     $reason The reason given for the refund.
	 * @return bool|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'wc_klarna_checkout_process_refund', false, $order_id, $amount, $reason );
	}

	/**
	 * Send the merchant to the section that configures this gateway.
	 *
	 * @return void
	 */
	public function admin_options() {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kco#in_person_payments' );
		?>
		<p>
			<?php
			printf(
				/* translators: %1$s: opening link tag, %2$s: closing link tag. */
				esc_html__( 'In-person payments are configured in the %1$sKustom Checkout settings%2$s.', 'klarna-checkout-for-woocommerce' ),
				'<a href="' . esc_url( $url ) . '">',
				'</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Whether the method should be offered at checkout.
	 *
	 * Deliberately makes no API call: availability is evaluated on every checkout
	 * render, and a missing device list is reported in the payment fields instead.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! wc_string_to_bool( $this->enabled ) ) {
			return false;
		}

		return current_user_can( self::required_capability() );
	}

	/**
	 * The capability a user needs to take an in-person payment.
	 *
	 * @return string
	 */
	public static function required_capability() {
		$capability = SettingsUtility::get_setting( 'ipp_capability', 'manage_woocommerce' );

		/**
		 * The capability a user needs to be offered in-person payment and to drive a
		 * session.
		 *
		 * Widening it, e.g. to `read`, hands the method and the device list to every
		 * user with that capability. Meant for custom staff roles, not shoppers.
		 *
		 * @since x.x.x
		 *
		 * @param string $capability The capability. Default the `ipp_capability` setting.
		 */
		return apply_filters( 'kco_ipp_required_capability', $capability );
	}

	/**
	 * Render the device picker.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$devices = Devices::all();

		if ( is_wp_error( $devices ) ) {
			echo '<p class="woocommerce-error">' . esc_html( $devices->get_error_message() ) . '</p>';
			return;
		}

		if ( empty( $devices ) ) {
			echo '<p class="woocommerce-error">' . esc_html__( 'No Kustom POS device is paired with this store yet.', 'klarna-checkout-for-woocommerce' ) . '</p>';
			return;
		}

		?>
		<div class="woocommerce-info"><?php echo esc_html( self::no_address_notice() ); ?></div>
		<p class="form-row form-row-wide">
			<label for="<?php echo esc_attr( self::DEVICE_FIELD ); ?>">
				<?php esc_html_e( 'Device', 'klarna-checkout-for-woocommerce' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'klarna-checkout-for-woocommerce' ); ?>">*</abbr>
			</label>
			<select name="<?php echo esc_attr( self::DEVICE_FIELD ); ?>" id="<?php echo esc_attr( self::DEVICE_FIELD ); ?>" class="select">
				<?php foreach ( $devices as $device ) : ?>
					<option value="<?php echo esc_attr( $device['id'] ?? '' ); ?>">
						<?php echo esc_html( $device['name'] ?? ( $device['id'] ?? '' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="form-row form-row-wide">
			<label for="<?php echo esc_attr( self::CHANNEL_FIELD ); ?>"><?php esc_html_e( 'Payment type', 'klarna-checkout-for-woocommerce' ); ?></label>
			<select name="<?php echo esc_attr( self::CHANNEL_FIELD ); ?>" id="<?php echo esc_attr( self::CHANNEL_FIELD ); ?>" class="select">
				<?php foreach ( self::payment_channels() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * The notice shown while in-person payment is chosen.
	 *
	 * @return string
	 */
	public static function no_address_notice() {
		return __( 'Shipping and address details will not be added to the order.', 'klarna-checkout-for-woocommerce' );
	}

	/**
	 * Check that the posted device is one of this merchant's.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		if ( null === $this->get_posted_device() ) {
			wc_add_notice( __( 'Please choose a Kustom POS device to take the payment on.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * The posted device, or null when it is missing or not paired with this merchant.
	 *
	 * @return array|null
	 */
	private function get_posted_device() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this runs.
		$device_id = isset( $_POST[ self::DEVICE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::DEVICE_FIELD ] ) ) : '';

		if ( '' === $device_id ) {
			return null;
		}

		$devices = Devices::all();

		return is_wp_error( $devices ) ? null : Devices::find( $devices, $device_id );
	}

	/**
	 * The payment channels the salesperson can pick from, by value. An empty value lets
	 * the customer choose on the device.
	 *
	 * @return array<string, string>
	 */
	public static function payment_channels() {
		return array(
			''             => __( 'Chosen on the device', 'klarna-checkout-for-woocommerce' ),
			'CARD_PAYMENT' => __( 'Card', 'klarna-checkout-for-woocommerce' ),
			'SCAN_TO_PAY'  => __( 'Scan to pay', 'klarna-checkout-for-woocommerce' ),
		);
	}

	/**
	 * The posted payment channel, or an empty string to let the device ask.
	 *
	 * @return string
	 */
	private function get_posted_channel() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this runs.
		$channel = isset( $_POST[ self::CHANNEL_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CHANNEL_FIELD ] ) ) : '';

		return in_array( $channel, self::PAYMENT_CHANNELS, true ) ? $channel : '';
	}

	/**
	 * Dispatch a payment session to the chosen device.
	 *
	 * The order stays pending: it is the device that decides whether the sale happens.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! $this->is_available() ) {
			wc_add_notice( __( 'In-person payments can only be taken by store staff.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order  = wc_get_order( $order_id );
		$device = $this->get_posted_device();

		if ( ! $order || null === $device ) {
			wc_add_notice( __( 'Please choose a Kustom POS device to take the payment on.', 'klarna-checkout-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$device_id = $device['id'];

		if ( '' === $order->get_billing_email() ) {
			$order->set_billing_email( Checkout::fallback_email() );
		}

		$channel  = $this->get_posted_channel();
		$payload  = ( new SessionPayload( $order, $device_id, $channel ) )->get_payload();
		$response = ( new RequestPostSession( array( 'payload' => $payload ) ) )->request();

		if ( is_wp_error( $response ) ) {
			return $this->session_failed( $order, $response );
		}

		$order->update_meta_data( self::SESSION_META, $response['session_id'] ?? '' );
		$order->update_meta_data( self::DEVICE_META, $device_id );
		$order->update_meta_data( self::STATUS_META, $response['status'] ?? '' );
		$order->update_meta_data( self::DEVICE_NAME_META, $device['name'] ?? '' );
		$order->update_meta_data( self::LOCATION_META, $device['location_id'] ?? '' );
		$order->update_meta_data( self::REGISTER_META, (string) ( $device['open_cash_register_id'] ?? '' ) );
		$order->update_meta_data( self::CHANNEL_META, $channel );
		$order->update_meta_data( self::STAFF_META, (string) get_current_user_id() );
		$order->add_order_note(
			sprintf(
				/* translators: %s: the name of the POS device. */
				__( 'Kustom in-person payment session sent to %s. Waiting for the customer to pay on the device.', 'klarna-checkout-for-woocommerce' ),
				$device['name'] ?? $device_id
			)
		);
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}

	/**
	 * Report a session that never started.
	 *
	 * The provider's own wording is kept on the order rather than shown at the
	 * counter, where support can still read it.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param \WP_Error $error The error the request returned.
	 * @return array
	 */
	private function session_failed( $order, $error ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: the error message from the Kustom API. */
				__( 'Kustom could not start the in-person payment session: %s', 'klarna-checkout-for-woocommerce' ),
				wp_strip_all_tags( $error->get_error_message() )
			)
		);
		$order->save();

		wc_add_notice( ErrorMessage::for_merchant( $error ) . ' ' . ErrorMessage::where_details_are(), 'error' );

		return array( 'result' => 'failure' );
	}
}

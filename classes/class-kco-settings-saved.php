<?php
/**
 * File for checking settings on save.
 *
 * @package Klarna_Checkout/Classes
 */

/**
 * Class for checking settings on save.
 */
class KCO_Settings_Saved {
	const EU_PROD = 'European Production';
	const EU_TEST = 'European Test';
	const US_PROD = 'United States Production';
	const US_TEST = 'United States Test';

	/**
	 * If there was an error detected or not.
	 *
	 * @var boolean
	 */
	private $error = false;

	/**
	 * Error message array.
	 *
	 * @var array
	 */
	private $message = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_update_options_checkout_kco', array( $this, 'check_if_test_credentials_exists' ), 10 );
		add_action( 'woocommerce_update_options_checkout_kco', array( $this, 'check_api_credentials' ), 10 );
	}

	/**
	 * Clears whitespace from API settings.
	 *
	 * @return void
	 */
	public function check_api_credentials() {
		// Get settings from KCO.
		$options = get_option( 'woocommerce_kco_settings' );

		// If not enabled bail.
		if ( $options && 'yes' !== $options['enabled'] ) {
			return;
		}

		if ( 'yes' !== $options['testmode'] ) {
			// Check EU Production.
			if ( '' !== $options['merchant_id_eu'] ) {
				$username = $options['merchant_id_eu'];
				$password = $options['shared_secret_eu'];

				$test_response = ( new KCO_Request_Test_Credentials() )->request( $username, $password, false, 'EU' );
				$this->process_test_response( $test_response, self::EU_PROD );
			}

			// Check US Production.
			if ( '' !== $options['merchant_id_us'] ) {
				$username = $options['merchant_id_us'];
				$password = $options['shared_secret_us'];

				$test_response = ( new KCO_Request_Test_Credentials() )->request( $username, $password, false, 'US' );
				$this->process_test_response( $test_response, self::US_PROD );
			}
		} else {
			// Check EU Test.
			if ( '' !== $options['test_merchant_id_eu'] ) {
				$username = $options['test_merchant_id_eu'];
				$password = $options['test_shared_secret_eu'];

				$test_response = ( new KCO_Request_Test_Credentials() )->request( $username, $password, true, 'EU' );
				$this->process_test_response( $test_response, self::EU_TEST );
			}

			// Check US Test.
			if ( '' !== $options['test_merchant_id_us'] ) {
				$username = $options['test_merchant_id_us'];
				$password = $options['test_shared_secret_us'];

				$test_response = ( new KCO_Request_Test_Credentials() )->request( $username, $password, true, 'US' );
				$this->process_test_response( $test_response, self::US_TEST );
			}
		}

		$this->maybe_handle_error();
	}

	/**
	 * Processes the test response.
	 *
	 * @param array|WP_Error $test_response The response from the test.
	 * @param string         $test The test that was run.
	 * @return void
	 */
	public function process_test_response( $test_response, $test ) {
		// If this is not a WP Error then its ok.
		if ( ! is_wp_error( $test_response ) ) {
			return;
		}

		$code          = $test_response->get_error_code();
		$error         = json_decode( $test_response->get_error_message(), true );
		$error_message = $error['message'];
		if ( 401 === $code || 403 === $code ) {
			switch ( $code ) {
				case 401:
					$message = "Your Klarna $test credentials not authorized. Please verify the credentials and environment (production or test mode) or remove these credentials and save again. API credentials only work in either production or test, not both environments. ";
					break;
				case 403:
					$message = "It seems like your Klarna $test API credentials are not working for the Klarna Checkout plugin, please verify your Klarna contract is for the Klarna Checkout solution.  If your Klarna contract is for standalone payment methods, please instead use the <a href='https://docs.woocommerce.com/document/klarna-payments/'>Klarna Payments for WooCommerce</a> plugin. ";
					break;
			}
			$message        .= "API error code: $code, Klarna API error message: $error_message";
			$this->message[] = $message;
			$this->error     = true;
		}
	}

	/**
	 * Checks if the test mode is active and any of the test credentials are filled in.
	 *
	 * @return void
	 */
	public function check_if_test_credentials_exists() {
		$options = get_option( 'woocommerce_kco_settings' );
		// If not enabled bail.
		if ( 'yes' !== $options['enabled'] ) {
			return;
		}
		// If testmode is not enabled, bail.
		if ( ! isset( $options['testmode'] ) || 'yes' !== $options['testmode'] ) {
			return;
		}

		// Check if EU credentials are set. If they are, bail.
		if ( isset( $options['test_merchant_id_eu'], $options['test_shared_secret_eu'] )
		&& ( ! empty( $options['test_merchant_id_eu'] ) || ! empty( $options['test_shared_secret_eu'] ) ) ) {
			return;
		}

		// Check if US credentials are set. If they are, bail.
		if ( isset( $options['test_merchant_id_us'], $options['test_shared_secret_us'] )
		&& ( ! empty( $options['test_merchant_id_us'] ) || ! empty( $options['test_shared_secret_us'] ) ) ) {
			return;
		}
		$this->message[] = 'It looks like you have test mode active but no test credentials added. Please either turn off test mode or add test credentials.';
		$this->error     = true;

	}

	/**
	 * Adds a error message if an error was detected.
	 *
	 * @return void
	 */
	public function maybe_handle_error() {
		// Remove any potential error displays if there are no errors detected.
		if ( ! $this->error ) {
			delete_option( 'kco_credentials_error' );
			return;
		}

		update_option( 'kco_credentials_error', $this->message );
	}

	/**
	 * Displays errors if they exists for the credentials check.
	 *
	 * @return void
	 */
	public static function maybe_show_errors() {
		$error_messages = get_option( 'kco_credentials_error' );

		// If plugin file exists.
		if ( $error_messages ) {
			?>
				<div class="kco-message notice woocommerce-message notice-error">
				<?php
				foreach ( $error_messages as $error_message ) {
					?>
					<p><?php echo wp_kses_post( $error_message ); ?></p>
				<?php } ?>
				</div>
			<?php
		}
	}
}
new KCO_Settings_Saved();

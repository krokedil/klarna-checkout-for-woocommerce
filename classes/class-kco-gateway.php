<?php
/**
 * Class file for KCO_Gateway class.
 *
 * @package Klarna_Checkout/Classes
 */

use Krokedil\KustomCheckout\CheckoutFlow\CheckoutFlow;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Payment_Gateway' ) ) {
	/**
	 * KCO_Gateway class.
	 *
	 * @extends WC_Payment_Gateway
	 */
	class KCO_Gateway extends WC_Payment_Gateway {

		/**
		 * Whether the gateway is enabled or not.
		 *
		 * @var bool $enabled
		 */
		public $testmode = false;
		/**
		 * Whether logging is enabled or not.
		 *
		 * @var bool $logging
		 */
		public $logging = false;
		/**
		 * Whether to show shipping methods in the iframe or not.
		 *
		 * @var bool $shipping_methods_in_iframe
		 */
		public $shipping_methods_in_iframe = false;

		/**
		 * KCO_Gateway constructor.
		 */
		public function __construct() {
			$this->id                 = 'kco';
			$this->method_title       = __( 'Kustom Checkout', 'klarna-checkout-for-woocommerce' );
			$this->method_description = __( 'The current Kustom Checkout replaces standard WooCommerce checkout page.', 'klarna-checkout-for-woocommerce' );
			$this->has_fields         = false;
			$this->supports           = apply_filters(
				'kco_wc_supports',
				array(
					'products',
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'multiple_subscriptions',
					'subscription_payment_method_change_customer',
					'subscription_payment_method_change_admin',
					'upsell',
				)
			);

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			$this->enabled                    = $this->get_option( 'enabled' );
			$this->testmode                   = 'yes' === $this->get_option( 'testmode' );
			$this->logging                    = 'yes' === $this->get_option( 'logging' );
			$this->shipping_methods_in_iframe = $this->get_option( 'shipping_methods_in_iframe' );

			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array(
					$this,
					'process_admin_options',
				)
			);
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_billing_org_nr' ) );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_billing_reference' ) );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'add_shipping_reference' ) );

			add_action( 'woocommerce_checkout_init', array( $this, 'prefill_consent' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
			add_action( 'woocommerce_thankyou', 'kco_unset_sessions', 100, 1 );

			// Remove WooCommerce footer text from our settings page.
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 999 );

			// Body class for KSS.
			add_filter( 'body_class', array( $this, 'add_body_class' ) );

			add_filter( 'kco_wc_api_request_args', array( $this, 'maybe_remove_kco_epm' ), 9999 );

			// Prevent the Woo validation from proceeding if there is a discrepancy between Woo and Kustom.
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		}

		/**
		 * Validate the data of the checkout fields matches the Kustom order.
		 *
		 * @param array    $data An array of posted data.
		 * @param WP_Error $errors Validation errors.
		 * @return void
		 */
		public function validate_checkout( $data, $errors ) {
			if ( 'kco' !== WC()->session->get( 'chosen_payment_method' ) || SettingsUtility::get_setting( 'checkout_flow', 'embedded' ) === 'redirect' ) {
				return;
			}

			$checkout_flow = $this->settings['checkout_flow'] ?? 'embedded';
			// Validation for the redirect flow is made at a later stage.
			if ( 'redirect' === $checkout_flow ) {
				return;
			}

			$klarna_order_id = WC()->session->get( 'kco_wc_order_id' );
			if ( empty( $klarna_order_id ) ) {
				KCO_Logger::log( '[CHECKOUT VALIDATION]: Kustom order ID is not set in the session. Will not proceed with order.' );
				$errors->add( 'klarna_order_id', __( 'The Kustom order id could not be retrieved from the session. Please try again.', 'klarna-checkout-for-woocommerce' ) );
				return;
			}

			$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
			if ( is_wp_error( $klarna_order ) ) {
				KCO_Logger::log( "[CHECKOUT VALIDATION]: Error getting Kustom order: {$klarna_order->get_error_message()}. For Kustom order ID: '$klarna_order_id'. Will not proceed with order." );
				$errors->add( 'klarna_order', __( 'The Kustom order could not be retrieved from the session. Please try again.', 'klarna-checkout-for-woocommerce' ) );
				return;
			}

			// Mapping of the Woo/Kustom address fields.
			$address_fields_key = array(
				'first_name' => 'given_name',
				'last_name'  => 'family_name',
				'company'    => 'organization_name',
				'address_1'  => 'street_address',
				'address_2'  => 'street_address2',
				'city'       => 'city',
				'postcode'   => 'postal_code',
				'country'    => 'country',
			);

			$billing_address = array_filter(
				$data,
				function ( $field ) use ( $address_fields_key ) {
					return strpos( $field, 'billing_' ) === 0 && in_array( substr( $field, strlen( 'billing_' ) ), array_keys( $address_fields_key ), true );
				},
				ARRAY_FILTER_USE_KEY
			);

			$shipping_address = array_filter(
				$data,
				function ( $field ) use ( $address_fields_key ) {
					return strpos( $field, 'shipping_' ) === 0 && in_array( substr( $field, strlen( 'shipping_' ) ), array_keys( $address_fields_key ), true );
				},
				ARRAY_FILTER_USE_KEY
			);

			$ship_to_different_address = $data['ship_to_different_address'];
			foreach ( $address_fields_key as $wc_name => $klarna_name ) {
				$billing_field  = 'billing_' . $wc_name;
				$shipping_field = 'shipping_' . $wc_name;

				if ( 'country' === $wc_name ) {
					$base_location = wc_get_base_location();
					$country       = $base_location['country'];

					if ( ! isset( $billing_address[ $billing_field ] ) ) {
						$billing_address[ $billing_field ] = $country;
					}

					if ( ! isset( $shipping_address[ $shipping_field ] ) ) {
						$shipping_address[ $shipping_field ] = $country;
					}
				}

				if ( isset( $klarna_order['billing_address'][ $klarna_name ] ) ) {
					// Remove all whitespace and convert to lowercase.
					$billing_address[ $billing_field ]               = strtolower( preg_replace( '/\s+/', '', $billing_address[ $billing_field ] ) );
					$klarna_order['billing_address'][ $klarna_name ] = strtolower( preg_replace( '/\s+/', '', $klarna_order['billing_address'][ $klarna_name ] ) );

					if ( ( $klarna_order['billing_address'][ $klarna_name ] ?? '' ) !== $billing_address[ $billing_field ] ) {
						$field_name = str_replace( '_', ' ', $wc_name );
						// translators: %s is the field name.
						$errors->add( $billing_field, sprintf( __( 'Billing %s does not match Kustom order.', 'klarna-checkout-for-woocommerce' ), $field_name ) );
					}
				}

				if ( $ship_to_different_address ) {
					// Remove all whitespace and convert to lowercase.
					$shipping_address[ $shipping_field ]              = strtolower( preg_replace( '/\s+/', '', $shipping_address[ $shipping_field ] ) );
					$klarna_order['shipping_address'][ $klarna_name ] = strtolower( preg_replace( '/\s+/', '', $klarna_order['shipping_address'][ $klarna_name ] ?? '' ) );

					if ( ( $klarna_order['shipping_address'][ $klarna_name ] ?? '' ) !== $shipping_address[ $shipping_field ] ) {
						$field_name = str_replace( '_', ' ', $wc_name );
						// translators: %s is the field name.
						$errors->add( $shipping_field, sprintf( __( 'Shipping %s does not match Kustom order.', 'klarna-checkout-for-woocommerce' ), $field_name ) );
					}
				}
			}
		}


		/**
		 * Get gateway icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			$icon_src  = 'https://cdn.klarna.com/1.0/shared/image/generic/logo/en_us/basic/logo_black.png?width=100';
			$icon_html = '<img src="' . $icon_src . '" alt="Kustom Checkout" style="border-radius:0px" width="100"/>';
			return apply_filters( 'wc_klarna_checkout_icon_html', $icon_html );
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param  int $order_id WooCommerce order ID.
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			return CheckoutFlow::process_payment( $order_id );
		}

		/**
		 * This plugin doesn't handle order management, but it allows Klarna Order Management plugin to process refunds
		 * and then return true or false.
		 *
		 * @param int      $order_id WooCommerce order ID.
		 * @param null|int $amount Refund amount.
		 * @param string   $reason Reason for refund.
		 *
		 * @return bool
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			return apply_filters( 'wc_klarna_checkout_process_refund', false, $order_id, $amount, $reason );
		}

		/**
		 * Initialize settings fields.
		 */
		public function init_form_fields() {
			$this->form_fields = KCO_Fields::fields();
		}

		/**
		 * Checks if method should be available.
		 *
		 * @return bool
		 */
		public function is_available() {
			if ( 'yes' !== $this->enabled ) {
				return false;
			}

			// If we can't retrieve a set of credentials, disable KCO.
			if ( is_checkout() && ! KCO_WC()->credentials->get_credentials_from_session() ) {
				return false;
			}

			// If we have a subscription product in cart and the customer isn't from SE, NO, FI, DE, DK, AT or NL, disable KCO.
			if ( is_checkout() && class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
				$available_recurring_countries = array( 'SE', 'NO', 'FI', 'DK', 'DE', 'AT', 'NL' );
				$country                       = WC()->customer->get_billing_country();
				if ( empty( $country ) ) {
					// If the billing country is not available, the "No location by default" setting is set.
					// By default, if there is exactly one country the store sells to, it will be used by default.
					// However, it still won't be set as the billing country until the customer has filled their billing address.
					// In practice, the customer doesn't really have any other choice, so we can assume that it is selected country.
					$countries = WC()->countries->get_allowed_countries();
					if ( 1 === count( $countries ) ) {
						$country = array_key_first( $countries );
					} elseif ( 1 < count( $countries ) ) {
						// If there is at least more than one allowed country, WC will let the customer pick a country on the checkout page.
						// We'll wait until the customer has made a choice.
						return false;
					}
				}
				if ( ! in_array( $country, $available_recurring_countries, true ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Add sidebar to the settings page.
		 */
		public function admin_options() {
			ob_start();
			parent::admin_options();
			$parent_options = ob_get_contents();
			ob_end_clean();
			KCO_Settings_Saved::maybe_show_errors();
			WC_Klarna_Banners::settings_sidebar( $parent_options );
		}

		/**
		 * Enqueue payment scripts.
		 *
		 * @hook wp_enqueue_scripts
		 */
		public function enqueue_scripts() {
			if ( 'yes' !== $this->enabled ) {
				return;
			}

			/* On the 'order-pay' page we redirect the customer to a hosted payment page, and therefore don't need need to enqueue any of the following assets. */
			if ( ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) ) {
				return;
			}

			// If the redirect flow is selected, we do not need to load any custom scripts.
			if ( 'redirect' === ( $this->settings['checkout_flow'] ?? 'embedded' ) ) {
				return;
			}

			// If the checkout blocks are enabled in WooCommerce, we should not include these scripts either.
			if ( BlocksUtility::is_checkout_block_enabled() ) {
				return;
			}

			$pay_for_order = false;
			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$pay_for_order = true;
			}

			if ( ! kco_wc_prefill_allowed() ) {
				add_thickbox();
			}
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_register_script(
				'kco',
				plugins_url( 'assets/js/klarna-checkout-for-woocommerce' . $suffix . '.js', KCO_WC_MAIN_FILE ),
				array( 'jquery', 'jquery-blockui' ),
				KCO_WC_VERSION,
				true
			);

			wp_register_style(
				'kco',
				plugins_url( 'assets/css/klarna-checkout-for-woocommerce' . $suffix . '.css', KCO_WC_MAIN_FILE ),
				array(),
				KCO_WC_VERSION
			);

			$email_exists = 'no';
			if ( null !== WC()->customer && method_exists( WC()->customer, 'get_billing_email' ) && ! empty( WC()->customer->get_billing_email() ) ) {
				if ( email_exists( WC()->customer->get_billing_email() ) ) {
					// Email exist in a user account.
					$email_exists = 'yes';
				}
			}

			$standard_woo_checkout_fields = apply_filters( 'kco_ignored_checkout_fields', array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', '_wp_http_referer', 'ship_to_different_address', 'account_username', 'account_password' ) );
			$checkout_localize_params     = array(
				'update_cart_url'                 => WC_AJAX::get_endpoint( 'kco_wc_update_cart' ),
				'update_cart_nonce'               => wp_create_nonce( 'kco_wc_update_cart' ),
				'update_shipping_url'             => WC_AJAX::get_endpoint( 'kco_wc_update_shipping' ),
				'update_shipping_nonce'           => wp_create_nonce( 'kco_wc_update_shipping' ),
				'change_payment_method_url'       => WC_AJAX::get_endpoint( 'kco_wc_change_payment_method' ),
				'change_payment_method_nonce'     => wp_create_nonce( 'kco_wc_change_payment_method' ),
				'get_klarna_order_url'            => WC_AJAX::get_endpoint( 'kco_wc_get_klarna_order' ),
				'get_klarna_order_nonce'          => wp_create_nonce( 'kco_wc_get_klarna_order' ),
				'log_to_file_url'                 => WC_AJAX::get_endpoint( 'kco_wc_log_js' ),
				'log_to_file_nonce'               => wp_create_nonce( 'kco_wc_log_js' ),
				'submit_order'                    => WC_AJAX::get_endpoint( 'checkout' ),
				'customer_type_changed_url'       => WC_AJAX::get_endpoint( 'kco_customer_type_changed' ),
				'logging'                         => $this->logging,
				'standard_woo_checkout_fields'    => $standard_woo_checkout_fields,
				'is_confirmation_page'            => ( is_kco_confirmation() ) ? 'yes' : 'no',
				'is_order_received_page'          => is_order_received_page() ? 'yes' : 'no',
				'shipping_methods_in_iframe'      => $this->shipping_methods_in_iframe,
				'required_fields_text'            => __( 'Please fill in all required checkout fields.', 'klarna-checkout-for-woocommerce' ),
				'email_exists'                    => $email_exists,
				'must_login_message'              => apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) ),
				'timeout_message'                 => __( 'Please try again, something went wrong with processing your order.', 'klarna-checkout-for-woocommerce' ),
				'timeout_time'                    => apply_filters( 'kco_checkout_timeout_duration', 20 ),
				'countries'                       => kco_get_country_codes(),
				'pay_for_order'                   => $pay_for_order,
				'no_shipping_message'             => apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ),
				'woocommerce_ship_to_destination' => get_option( 'woocommerce_ship_to_destination' ),
			);

			if ( version_compare( WC_VERSION, '3.9', '>=' ) ) {
				$checkout_localize_params['force_update'] = true;
			}
			wp_localize_script( 'kco', 'kco_params', $checkout_localize_params );

			wp_enqueue_script( 'kco' );

			if ( ! $pay_for_order ) {
				wp_enqueue_style( 'kco' );
			}
		}


		/**
		 * Enqueue admin scripts.
		 *
		 * @param string $hook Admin page hook.
		 *
		 * @hook admin_enqueue_scripts
		 */
		public function admin_enqueue_scripts( $hook ) {
			if ( 'woocommerce_page_wc-settings' !== $hook ) {
				return;
			}
			$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( empty( $section ) || 'kco' !== $section ) {
				return;
			}

			$suffix              = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$store_base_location = wc_get_base_location();
			if ( 'US' === $store_base_location['country'] ) {
				$location = 'US';
			} else {
				$location = $this->check_if_eu( $store_base_location['country'] );
			}

			wp_register_script(
				'kco_admin',
				plugins_url( 'assets/js/klarna-checkout-for-woocommerce-admin' . $suffix . '.js', KCO_WC_MAIN_FILE ),
				array(),
				KCO_WC_VERSION,
				false
			);
			$admin_localize_params = array(
				'location' => $location,
			);
			wp_localize_script( 'kco_admin', 'kco_admin_params', $admin_localize_params );
			wp_enqueue_script( 'kco_admin' );
		}

		/**
		 * Detect if EU country.
		 *
		 * @param string $store_base_location The WooCommerce stores base country.
		 */
		private function check_if_eu( $store_base_location ) {
			$eu_countries = array(
				'AL',
				'AD',
				'AM',
				'AT',
				'BY',
				'BE',
				'BA',
				'BG',
				'CH',
				'CY',
				'CZ',
				'DE',
				'DK',
				'EE',
				'ES',
				'FO',
				'FI',
				'FR',
				'GB',
				'GE',
				'GI',
				'GR',
				'HU',
				'HR',
				'IE',
				'IS',
				'IT',
				'LT',
				'LU',
				'LV',
				'MC',
				'MK',
				'MT',
				'NO',
				'NL',
				'PL',
				'PT',
				'RO',
				'RU',
				'SE',
				'SI',
				'SK',
				'SM',
				'TR',
				'UA',
				'VA',
			);

			if ( in_array( $store_base_location, $eu_countries, true ) ) {
				return 'EU';
			} else {
				return '';
			}
		}

		/**
		 * Displays Kustom Checkout thank you iframe and clears Kustom order ID value from WC session.
		 *
		 * @param int $order_id WooCommerce order ID.
		 */
		public function show_thank_you_snippet( $order_id = null ) {
			if ( $order_id ) {
				$order           = wc_get_order( $order_id );
				$upsell_uuids    = $order->get_meta( '_ppu_upsell_ids', true );
				$has_been_upsold = ! empty( $upsell_uuids );

				if ( is_object( $order ) && $order->get_transaction_id() ) {
					$klarna_order_id = $order->get_transaction_id();

					$klarna_order = KCO_WC()->api->get_klarna_order( $klarna_order_id );
					if ( $klarna_order && ! $has_been_upsold ) { // Don't show the snippet for upsold orders, since the iFrame wont be updated with the new orders lines.
						echo kco_extract_script( $klarna_order['html_snippet'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- Cant escape since this is the iframe snippet.
					}

					// Check if we need to finalize purchase here. Should already been done in process_payment.
					if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
						KCO_Logger::log( $klarna_order_id . ': Confirm the Kustom order from the thankyou page.' );
						kco_confirm_klarna_order( $order_id, $klarna_order_id );
						WC()->cart->empty_cart();
					}
				}
			}
		}

		/**
		 * Changes footer text in KCO settings page.
		 *
		 * @param string $text Footer text.
		 *
		 * @return string
		 */
		public function admin_footer_text( $text ) {
			$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $section ) && 'kco' === $section ) {
				$text = 'If you like Kustom Checkout for WooCommerce, please consider <strong>assigning Krokedil as your integration partner.</strong>.';
			}

			return $text;
		}

		/**
		 * Adds can't edit address notice to KP EU orders.
		 *
		 * @param WC_Order $order WooCommerce order object.
		 */
		public function address_notice( $order ) {
			if ( $this->id === $order->get_payment_method() ) {
				echo '<div style="clear:both; margin: 10px 0; padding: 10px; border: 1px solid #B33A3A; font-size: 12px">';
				esc_html_e( 'Order address should not be changed and any changes you make will not be reflected in Kustom system.', 'klarna-checkout-for-woocommerce' );
				echo '</div>';
			}
		}

		/**
		 * Adds prefill consent to WC session.
		 */
		public function prefill_consent() {
			$prefill_consent = filter_input( INPUT_GET, 'prefill_consent', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $prefill_consent ) ) {
				if ( 'yes' === $prefill_consent ) {
					WC()->session->set( 'kco_wc_prefill_consent', true );
				}
			}
		}

		/**
		 * Add kco-shipping-display body class.
		 *
		 * @param array $classes Array of classes.
		 *
		 * @return array
		 */
		public function add_body_class( $classes ) {
			if ( is_checkout() && 'yes' === $this->shipping_methods_in_iframe ) {
				// Don't display KCO Shipping Display body classes if we have a cart that doesn't needs payment.
				if ( null !== WC()->cart && method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
					return $classes;
				}

				$first_gateway = '';
				if ( WC()->session->get( 'chosen_payment_method' ) ) {
					$first_gateway = WC()->session->get( 'chosen_payment_method' );
				} else {
					$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
					reset( $available_payment_gateways );
					$first_gateway = key( $available_payment_gateways );
				}
				if ( 'kco' === $first_gateway ) {
					$classes[] = 'kco-shipping-display';
				}
			}
			return $classes;
		}

		/**
		 * Maybe adds the billing org number to the address in an order.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @return void
		 */
		public function add_billing_org_nr( $order ) {
			if ( $this->id === $order->get_payment_method() ) {
				$org_nr = $order->get_meta( '_billing_org_nr', true );
				if ( $org_nr ) {
					?>
					<p>
						<strong>
							<?php esc_html_e( 'Organisation number:', 'klarna-checkout-for-woocommerce' ); ?>
						</strong>
						<?php echo esc_html( $org_nr ); ?>
					</p>
					<?php
				}
			}
		}

		/**
		 * Maybe adds the billing reference to the address in an order.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @return void
		 */
		public function add_billing_reference( $order ) {
			if ( $this->id === $order->get_payment_method() ) {
				$reference = $order->get_meta( '_billing_reference', true );
				if ( $reference ) {
					?>
					<p>
						<strong>
							<?php esc_html_e( 'Reference:', 'klarna-checkout-for-woocommerce' ); ?>
						</strong>
						<?php echo esc_html( $reference ); ?>
					</p>
					<?php
				}
			}
		}

		/**
		 * Maybe adds the shipping reference to the address in an order.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @return void
		 */
		public function add_shipping_reference( $order ) {
			if ( $this->id === $order->get_payment_method() ) {
				$reference = $order->get_meta( '_shipping_reference', true );
				if ( $reference ) {
					?>
					<p>
						<strong>
							<?php esc_html_e( 'Reference:', 'klarna-checkout-for-woocommerce' ); ?>
						</strong>
						<?php echo esc_html( $reference ); ?>
					</p>
					<?php
				}
			}
		}

		/**
		 * Remove any external payment method from pay for order.
		 *
		 * @param array $request_args The request args.
		 *
		 * @return array
		 */
		public function maybe_remove_kco_epm( $request_args ) {
			if ( isset( $request_args['external_payment_methods'] ) && is_wc_endpoint_url( 'order-pay' ) ) {
				unset( $request_args['external_payment_methods'] );
			}

			return $request_args;
		}

		/**
		 * Check if upsell should be available for the Kustom order or not.
		 *
		 * @param int $order_id The WooCommerce order id.
		 * @return bool
		 */
		public function upsell_available( $order_id ) {
			$order           = wc_get_order( $order_id );
			$klarna_order_id = $order->get_meta( '_wc_klarna_order_id', true );

			if ( empty( $klarna_order_id ) ) {
				return false;
			}

			$klarna_order = KCO_WC()->api->get_klarna_om_order( $klarna_order_id );

			if ( is_wp_error( $klarna_order ) ) {
				return false;
			}

			// If the needed keys are not set, return false.
			if ( ! isset( $klarna_order['initial_payment_method'] ) || ! isset( $klarna_order['initial_payment_method']['type'] ) ) {
				return false;
			}

			// Set allowed payment methods for upsell based on country. https://docs.kustom.co/v3/order-management/manage-orders-with-the-api/view-and-change-orders#update-order-amount-1.
			$allowed_payment_methods = array( 'INVOICE', 'B2B_INVOICE', 'BASE_ACCOUNT', 'DIRECT_DEBIT' );
			switch ( $klarna_order['billing_address']['country'] ) {
				case 'AT':
				case 'DE':
				case 'DK':
				case 'FI':
				case 'FR':
				case 'NL':
				case 'NO':
				case 'SE':
					$allowed_payment_methods[] = 'FIXED_AMOUNT';
					break;
				case 'CH':
					$allowed_payment_methods = array();
					break;
			}

			return in_array( $klarna_order['initial_payment_method']['type'], $allowed_payment_methods, true );
		}

		/**
		 * Make an upsell request to Kustom
		 *
		 * @param int    $order_id The WooCommerce order id.
		 * @param string $upsell_uuid The unique id for the upsell request.
		 * @return bool|WP_Error
		 */
		public function upsell( $order_id, $upsell_uuid ) {
			$klarna_upsell_order = KCO_WC()->api->upsell_klarna_order( $order_id, $upsell_uuid );

			if ( is_wp_error( $klarna_upsell_order ) ) {
				$error = new WP_Error( '401', __( 'Kustom did not accept the new order amount, the order has not been updated', 'klarna-checkout-for-woocommerce' ) );
				return $error;
			}

			return true;
		}
	}
}

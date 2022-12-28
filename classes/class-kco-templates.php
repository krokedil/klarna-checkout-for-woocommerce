<?php
/**
 * Templates class for Klarna checkout.
 *
 * @package  Klarna_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * KCO_Templates class.
 */
class KCO_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Override template if Klarna Checkout page.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );
		add_action( 'wp_footer', array( $this, 'check_that_kco_template_has_loaded' ) );

		// Template hooks.
		add_action( 'kco_wc_after_order_review', 'kco_wc_add_extra_checkout_fields', 10 );
		add_action( 'kco_wc_after_order_review', 'kco_wc_show_another_gateway_button', 20 );
		add_action( 'kco_wc_before_snippet', 'kco_wc_prefill_consent', 10 );
		add_action( 'kco_wc_before_snippet', array( $this, 'add_wc_form' ), 10 ); // @TODO Look into changing this to kco_wc_after_wrapper later.
		add_action( 'kco_wc_before_snippet', array( $this, 'add_review_order_before_submit' ), 15 );
		// Unrequire WooCommerce Billing State field.
		add_filter( 'woocommerce_billing_fields', array( $this, 'kco_wc_unrequire_wc_billing_state_field' ) );
		// Unrequire WooCommerce Shipping State field.
		add_filter( 'woocommerce_shipping_fields', array( $this, 'kco_wc_unrequire_wc_shipping_state_field' ) );

		// Adds the required CSS classes for the checkout layout.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Override checkout form template if Klarna Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {
			$confirm = filter_input( INPUT_GET, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			// Don't display KCO template if we have a cart that doesn't needs payment.
			if ( apply_filters( 'kco_check_if_needs_payment', true ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
			}

			// Don't use KCO template for pay for order orders.
			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				return $template;
			}

			// Klarna Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/klarna-checkout.php' ) ) {
					$klarna_checkout_template = locate_template( 'woocommerce/klarna-checkout.php' );
				} else {
					$klarna_checkout_template = apply_filters( 'kco_locate_checkout_template', KCO_WC_PLUGIN_PATH . '/templates/klarna-checkout.php', $template_name );
				}

				// Klarna checkout page.
				if ( array_key_exists( 'kco', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( empty( $confirm ) ) {
							$template = $klarna_checkout_template;
						}
					}

					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'kco' === key( $available_gateways ) ) {
							if ( empty( $confirm ) ) {
								$template = $klarna_checkout_template;
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'kco' === key( $available_gateways ) ) {
								if ( empty( $confirm ) ) {
									$template = $klarna_checkout_template;
								}
							}
						}
					}
				}
			}
		}

		return $template;
	}

	/**
	 * Redirect customer to cart page if Klarna Checkout is the selected (or first)
	 * payment method but the KCO template file hasn't been loaded.
	 */
	public function check_that_kco_template_has_loaded() {
		if ( is_checkout() && array_key_exists( 'kco', WC()->payment_gateways->get_available_payment_gateways() ) && 'kco' === kco_wc_get_selected_payment_method() && ( method_exists( WC()->cart, 'needs_payment' ) && WC()->cart->needs_payment() ) ) {

			// Get checkout object.
			$checkout = WC()->checkout();
			$settings = get_option( 'woocommerce_kco_settings' );
			$enabled  = ( 'yes' === $settings['enabled'] ) ? true : false;

			// Bail if this is KCO confirmation page, order received page, KCO page (kco_wc_show_snippet has run), user is not logged and registration is disabled or if woocommerce_cart_has_errors has run.
			if ( is_kco_confirmation()
			|| is_wc_endpoint_url( 'order-received' )
			|| did_action( 'kco_wc_show_snippet' )
			|| ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() )
			|| did_action( 'woocommerce_cart_has_errors' )
			|| isset( $_GET['change_payment_method'] ) // phpcs:ignore
			|| ! $enabled ) {
				return;
			}

			$url = add_query_arg(
				array(
					'kco-order' => 'error',
					'reason'    => base64_encode( __( 'Failed to load Klarna Checkout template file.', 'klarna-checkout-for-woocommerce' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				),
				wc_get_cart_url()
			);
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="kco-wc-form" style="position:absolute; top:-99999px; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<div id="kco-nonce-wrapper">
				<?php
				if ( version_compare( WOOCOMMERCE_VERSION, '3.4', '<' ) ) {
					wp_nonce_field( 'woocommerce-process_checkout' );
				} else {
					wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
				}
				wc_get_template( 'checkout/terms.php' );
				?>
			</div>
			<input id="payment_method_kco" type="radio" class="input-radio" name="payment_method" value="kco" checked="checked" />		</div>
		<?php
	}

	/**
	 * Unrequire WC billing state field.
	 *
	 * @param array $fields WC billing fields.
	 * @return array $fields WC billing fields.
	 */
	public function kco_wc_unrequire_wc_billing_state_field( $fields ) {
		// Unrequire if chosen payment method is Klarna Checkout.
		if ( null !== WC()->session && method_exists( WC()->session, 'get' ) &&
			WC()->session->get( 'chosen_payment_method' ) &&
			'kco' === WC()->session->get( 'chosen_payment_method' )
			) {
			$fields['billing_state']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Unrequire WC shipping state field.
	 *
	 * @param array $fields WC shipping fields.
	 * @return array $fields WC shipping fields.
	 */
	public function kco_wc_unrequire_wc_shipping_state_field( $fields ) {
		// Unrequire if chosen payment method is Klarna Checkout.
		if ( null !== WC()->session && method_exists( WC()->session, 'get' ) &&
			WC()->session->get( 'chosen_payment_method' ) &&
			'kco' === WC()->session->get( 'chosen_payment_method' )
			) {
			$fields['shipping_state']['required'] = false;
		}

		return $fields;
	}


	/**
	 * Triggers WC action.
	 */
	public function add_review_order_before_submit() {
		do_action( 'woocommerce_review_order_before_submit' );
	}

		/**
		 * Add checkout page body class, depending on checkout page layout settings.
		 *
		 * @param array $class CSS classes used in body tag.
		 * @return array The same input array with the addition of our custom classes.
		 */
	public function add_body_class( $class ) {
		if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return $class;
		}

		if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
			return $class;
		}

		$settings        = get_option( 'woocommerce_kco_settings' );
		$checkout_layout = $settings['checkout_layout'] ?? 'two_column_right';

		$first_gateway = '';
		if ( WC()->session->get( 'chosen_payment_method' ) ) {
			$first_gateway = WC()->session->get( 'chosen_payment_method' );
		} else {
			$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
			reset( $available_payment_gateways );
			$first_gateway = key( $available_payment_gateways );
		}

		if ( 'kco' === $first_gateway && 'two_column_left' === $checkout_layout ) {
			$class[] = 'kco-two-column-left';
		}

		if ( 'kco' === $first_gateway && 'two_column_left_sf' === $checkout_layout ) {
			$class[] = 'kco-two-column-left-sf';
		}

		if ( 'kco' === $first_gateway && 'one_column_checkout' === $checkout_layout ) {
			$class[] = 'kco-one-selected';
		}

		return $class;
	}
}

KCO_Templates::get_instance();

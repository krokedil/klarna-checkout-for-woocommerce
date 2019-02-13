<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_Confirmation class.
 *
 * Handles Klarna Checkout confirmation page.
 */
class Klarna_Checkout_For_WooCommerce_Confirmation {

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
	 * Klarna_Checkout_For_WooCommerce_Confirmation constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'maybe_hide_checkout_form' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_populate_wc_checkout' ) );
		add_action( 'wp_footer', array( $this, 'maybe_submit_wc_checkout' ), 999 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'maybe_unrequire_fields' ), 99 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'unrequire_posted_data' ), 99 );
		add_action( 'woocommerce_checkout_after_order_review', array( $this, 'add_kco_order_id_field' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_kco_order_id_field' ), 10, 3 );
	}


	/**
	 * Hides WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_hide_checkout_form() {
		if ( ! is_kco_confirmation() ) {
			return;
		}

		echo '<style>form.woocommerce-checkout,div.woocommerce-info{display:none!important}</style>';
	}

	/**
	 * Populates WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_populate_wc_checkout( $checkout ) {
		if ( ! is_kco_confirmation() ) {
			return;
		}
		echo '<div id="kco-confirm-loading"></div>';

		$klarna_order_id = esc_attr( sanitize_text_field( $_GET['kco_wc_order_id'] ) );
		$response        = ( ! isset( $_GET['kco-external-payment'] ) ? KCO_WC()->api->request_post_get_order( $klarna_order_id ) : KCO_WC()->api->request_pre_get_order( $klarna_order_id ) );

		if ( ! is_wp_error( $response ) ) {
			$klarna_order = apply_filters( 'kco_wc_klarna_order_pre_submit', json_decode( $response['body'] ) );
			$this->save_customer_data( $klarna_order );
		}
	}

	/**
	 * Submits WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_submit_wc_checkout() {
		if ( ! is_kco_confirmation() ) {
			return;
		}
		// Prevent duplicate orders if confirmation page is reloaded manually by customer
		$klarna_order_id = sanitize_key( $_GET['kco_wc_order_id'] );
		$query           = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'kco',
				'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
			)
		);
		$orders          = $query->get_orders();
		$order_id_match  = null;
		foreach ( $orders as $order_id ) {

			$order_klarna_order_id = get_post_meta( $order_id, '_wc_klarna_order_id', true );

			if ( $order_klarna_order_id === $klarna_order_id ) {
				$order_id_match = $order_id;
				break;
			}
		}
		// _wc_klarna_order_id already exist in an order. Let's redirect the customer to the thankyou page for that order
		if ( $order_id_match ) {
			krokedil_log_events( $order_id_match, 'Confirmation page rendered but _wc_klarna_order_id already exist in this order.', null );
			$order    = wc_get_order( $order_id_match );
			$location = $order->get_checkout_order_received_url();
			wp_safe_redirect( $location );
			exit;
		}
		?>
		<script>
			jQuery(function ($) {
				// Check if session storage is set to prevent double orders.
				// Session storage over local storage, since it dies with the tab.
				if ( sessionStorage.getItem( 'orderSubmitted' ) === null || sessionStorage.getItem( 'orderSubmitted' ) === 'false' ) {
					// Set session storage.
					sessionStorage.setItem( 'orderSubmitted',  '1');

					// Add modal with process order message.
					var klarna_process_text = '<?php echo __( 'Please wait while we process your order.', 'klarna-checkout-for-woocommerce' ); ?>';
					$( 'body' ).append( $( '<div class="kco-modal"><div class="kco-modal-content">' + klarna_process_text + '</div></div>' ) );

					$('input#terms').prop('checked', true);
					$('input#ship-to-different-address-checkbox').prop('checked', true);

					// If order value = 0, payment method fields will not be in the page, so we need to
					if (!$('input#payment_method_kco').length) {
						$('#order_review').append('<input id="payment_method_kco" type="radio" class="input-radio" name="payment_method" value="kco" checked="checked" />');
					}

					$('input#payment_method_kco').prop('checked', true);
					<?php
					$extra_field_values = WC()->session->get( 'kco_checkout_form', array() );
					?>
					var form_data = <?php echo json_encode( $extra_field_values ); ?>;
					for ( i = 0; i < form_data.length; i++ ) {
						var field = $('*[name="' + form_data[i].name + '"]');
						var saved_value = form_data[i].value;
						// Check if field is a checkbox
						if( field.is(':checkbox') ) {
							if( saved_value !== '' ) {
								field.prop('checked', true);
							}
						} else if( field.is(':radio') ) {
							for ( x = 0; x < field.length; x++ ) {
								if( field[x].value === form_data[i].value ) {
									$(field[x]).prop('checked', true);
								}
							}
						} else {
							field.val( saved_value );
						}

					}

					<?php
					do_action( 'kco_wc_before_submit' );
					KCO_WC()->logger->log( 'Confirmation page rendered and checkout form about to be submitted for Klarna order ID ' . $klarna_order_id );
					?>

					$('.validate-required').removeClass('validate-required');
					$('form[name="checkout"]').submit();
					console.log('yes submitted');
					$('form[name="checkout"]').addClass( 'processing' );
					console.log('processing class added to form');
				} else {
					console.log( 'Order already submitted' );

					// Add modal with retrying message.
					var klarna_process_text = '<?php echo __( 'Trying again. Please wait while we process your order...', 'klarna-checkout-for-woocommerce' ); ?>';
					$( 'body' ).append( $( '<div class="kco-modal"><div class="kco-modal-content">' + klarna_process_text + '</div></div>' ) );

					// If session storage is string, force it to an int.
					if( isNaN( parseInt(sessionStorage.getItem( 'orderSubmitted' ) ) ) ) {
						sessionStorage.setItem( 'orderSubmitted',  '1');
					}
					// Add to session storage.
					sessionStorage.setItem( 'orderSubmitted', parseInt( sessionStorage.getItem( 'orderSubmitted' ) ) + 1 );
					if( parseInt( sessionStorage.getItem( 'orderSubmitted' ) ) > 2 ) {
						<?php
							$redirect_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
							$redirect_url = add_query_arg( 'kco_checkout_error', 'true', $redirect_url );
						?>
						console.log('Max reloads reached.');
						window.location.href = "<?php echo $redirect_url; ?>";
					} else {
						location.reload();
					}
				}
			});
		</script>
		<?php
	}

	/**
	 * Checks if in KCO confirmation page.
	 *
	 * @return bool
	 * @todo Remove.
	 */
	private function is_kco_confirmation() {
		if ( isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] && isset( $_GET['kco_wc_order_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Saves customer data from Klarna order into WC()->customer.
	 *
	 * @param $klarna_order
	 */
	private function save_customer_data( $klarna_order ) {
		// First name.
		WC()->customer->set_billing_first_name( sanitize_text_field( $klarna_order->billing_address->given_name ) );
		WC()->customer->set_shipping_first_name( sanitize_text_field( $klarna_order->shipping_address->given_name ) );

		// Last name.
		WC()->customer->set_billing_last_name( sanitize_text_field( $klarna_order->billing_address->family_name ) );
		WC()->customer->set_shipping_last_name( sanitize_text_field( $klarna_order->shipping_address->family_name ) );

		// Country.
		WC()->customer->set_billing_country( strtoupper( sanitize_text_field( $klarna_order->billing_address->country ) ) );
		WC()->customer->set_shipping_country( strtoupper( sanitize_text_field( $klarna_order->shipping_address->country ) ) );

		// Street address 1.
		WC()->customer->set_billing_address_1( sanitize_text_field( $klarna_order->billing_address->street_address ) );
		WC()->customer->set_shipping_address_1( sanitize_text_field( $klarna_order->shipping_address->street_address ) );

		// Street address 2.
		if ( isset( $klarna_order->billing_address->street_address2 ) ) {
			WC()->customer->set_billing_address_2( sanitize_text_field( $klarna_order->billing_address->street_address2 ) );
			WC()->customer->set_shipping_address_2( sanitize_text_field( $klarna_order->shipping_address->street_address2 ) );
		}

		// Company Name.
		if ( isset( $klarna_order->billing_address->organization_name ) ) {
			WC()->customer->set_billing_company( sanitize_text_field( $klarna_order->billing_address->organization_name ) );
			WC()->customer->set_shipping_company( sanitize_text_field( $klarna_order->shipping_address->organization_name ) );
		}

		// City.
		WC()->customer->set_billing_city( sanitize_text_field( $klarna_order->billing_address->city ) );
		WC()->customer->set_shipping_city( sanitize_text_field( $klarna_order->shipping_address->city ) );

		// County/State.
		WC()->customer->set_billing_state( sanitize_text_field( $klarna_order->billing_address->region ) );
		WC()->customer->set_shipping_state( sanitize_text_field( $klarna_order->shipping_address->region ) );

		// Postcode.
		WC()->customer->set_billing_postcode( sanitize_text_field( $klarna_order->billing_address->postal_code ) );
		WC()->customer->set_shipping_postcode( sanitize_text_field( $klarna_order->shipping_address->postal_code ) );

		// Phone.
		WC()->customer->set_billing_phone( sanitize_text_field( $klarna_order->billing_address->phone ) );

		// Email.
		WC()->customer->set_billing_email( sanitize_text_field( $klarna_order->billing_address->email ) );

		WC()->customer->save();
	}

	/**
	 * When checking out using KCO, we need to make sure none of the WooCommerce are required, in case Klarna
	 * does not return info for some of them.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 *
	 * @return mixed
	 */
	public function maybe_unrequire_fields( $fields ) {
		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) && is_kco_confirmation() ) {
			foreach ( $fields as $fieldset_key => $fieldset ) {
				foreach ( $fieldset as $key => $field ) {
					$fields[ $fieldset_key ][ $key ]['required']        = '';
					$fields[ $fieldset_key ][ $key ]['wooccm_required'] = '';
				}
			}
		}

		return $fields;
	}

	/**
	 * Makes sure there's no empty data sent for validation.
	 *
	 * @param array $data Posted data.
	 *
	 * @return mixed
	 */
	public function unrequire_posted_data( $data ) {
		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $data as $key => $value ) {
				if ( '' === $value ) {
					unset( $data[ $key ] );
				}
			}
		}

		return $data;
	}


	/**
	 * Adds hidden field to WooCommerce checkout form, holding Klarna Checkout order ID.
	 */
	public function add_kco_order_id_field() {
		if ( is_kco_confirmation() ) {
			$klarna_order_id = esc_attr( sanitize_text_field( $_GET['kco_wc_order_id'] ) );
			echo '<input type="hidden" id="kco_order_id" name="kco_order_id" value="' . $klarna_order_id . '" />';
		}
	}

	/**
	 * Saves KCO order ID to WooCommerce order as meta field.
	 *
	 * @param string $order_id WooCommerce order ID.
	 * @param array  $data  Posted data.
	 * @param object $order  WooCommerce order object.
	 */
	public function save_kco_order_id_field( $order_id, $data, $order ) {
		if ( isset( $_POST['kco_order_id'] ) ) {
			$kco_order_id = sanitize_text_field( $_POST['kco_order_id'] );

			update_post_meta( $order_id, '_wc_klarna_order_id', sanitize_key( $kco_order_id ) );

			if ( 'kco' === $order->get_payment_method() ) {
				update_post_meta( $order_id, '_transaction_id', sanitize_key( $kco_order_id ) );
			}
		}
	}

}

Klarna_Checkout_For_WooCommerce_Confirmation::get_instance();

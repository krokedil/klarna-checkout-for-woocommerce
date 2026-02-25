<?php
/**
 * Meta box
 *
 * Handles the functionality for the KOM meta box.
 *
 * @package WC_Klarna_Order_Management
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Pending_Orders class.
 *
 * Handles the meta box for KOM
 */
class MetaBox {


	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'kom_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'process_kom_actions' ), 45, 2 );

		add_filter( 'kom_meta_environment', array( $this, 'filter_environment' ), 10, 1 );

		add_action( 'kom_meta_action_options', array( $this, 'output_option_capture' ), 10, 3 );
		add_action( 'kom_meta_action_options', array( $this, 'output_option_cancel' ), 20, 3 );
		add_action( 'kom_meta_action_options', array( $this, 'output_option_sync' ), 30, 3 );

		add_action( 'kom_meta_action_tips', array( $this, 'output_tip_capture' ), 10, 3 );
		add_action( 'kom_meta_action_tips', array( $this, 'output_tip_cancel' ), 20, 3 );
		add_action( 'kom_meta_action_tips', array( $this, 'output_tip_sync' ), 30, 3 );
	}

	/**
	 * Adds meta box to the side of a KCO or KP order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function kom_meta_box( $post_type ) {
		if ( in_array( $post_type, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			$order_id = kom_get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( in_array( $order->get_payment_method(), array( 'kco', 'klarna_payments' ), true ) ) {
				add_meta_box( 'kom_meta_box', __( 'Klarna Order Management', 'klarna-checkout-for-woocommerce' ), array( $this, 'kom_meta_box_content' ), $post_type, 'side', 'core' );
			}
		}
	}

	/**
	 * Adds content for the KOM meta box.
	 *
	 * @return void
	 */
	public function kom_meta_box_content() {
		$order_id = kom_get_the_ID();
		$order    = wc_get_order( $order_id );
		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) && ! in_array( $order->get_status(), array( 'on-hold' ), true ) ) {
			$this->print_error_content( __( 'The payment has not been finalized with Klarna.', 'klarna-checkout-for-woocommerce' ) );
			return;
		}
		// False if automatic settings are enabled, true if not. If true then show the option.
		if ( ! empty( $order->get_transaction_id() ) || ! empty( $order->get_meta( '_wc_klarna_order_id', true ) ) ) {

			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$this->print_error_content( __( 'Failed to retrieve the order from Klarna.', 'klarna-checkout-for-woocommerce' ) );
				return;
			}

			$this->print_standard_content( $klarna_order );
		}
	}

	/**
	 * Prints the standard content for the OM Metabox
	 *
	 * @param object $klarna_order The Klarna order object.
	 * @return void
	 */
	public function print_standard_content( $klarna_order ) {
		$order_id   = kom_get_the_ID();
		$order      = wc_get_order( $order_id );
		$settings   = WC_Klarna_Order_Management::get_instance()->settings->get_settings( $order_id );
		$session_id = $order->get_meta( '_kp_session_id' );

		$actions            = array();
		$actions['capture'] = ( ! isset( $settings['kom_auto_capture'] ) || 'yes' === $settings['kom_auto_capture'] ) ? false : true;
		$actions['cancel']  = ( ! isset( $settings['kom_auto_cancel'] ) || 'yes' === $settings['kom_auto_cancel'] ) ? false : true;
		$actions['sync']    = ( ! isset( $settings['kom_auto_order_sync'] ) || 'yes' === $settings['kom_auto_order_sync'] ) ? false : true;
		$actions['any']     = ( $actions['capture'] || $actions['cancel'] || $actions['sync'] );
		$environment        = ! empty( $order->get_meta( '_wc_klarna_environment' ) ) ? $order->get_meta( '_wc_klarna_environment' ) : '';

		// Release/Disconnect.
		$kom_disconnected_key = '_kom_disconnect';
		$kom_disconnect       = isset( $_GET[ $kom_disconnected_key ] ) ? sanitize_key( $_GET[ $kom_disconnected_key ] ) : false;

		if ( isset( $_GET['kom'] ) && wp_verify_nonce( $kom_disconnect, 'kom_disconnect' ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['kom'] ) );
			// Disabled mean it is disconnected, not that the feature is disabled.
			if ( 'disabled' === $action ) {
				$order->update_meta_data( $kom_disconnected_key, 1 );
			} elseif ( 'enabled' === $action ) {
				$order->delete_meta_data( $kom_disconnected_key );
			}
			$order->save();
		}

		$kom_disconnected_status = $order->get_meta( $kom_disconnected_key ) ? 'disabled' : 'enabled';
		$kom_disconnected_url    = add_query_arg(
			array(
				'kom' => strtolower( $kom_disconnected_status ),
			),
			admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' )
		);

		?>
		<div class="kom-meta-box-content">
			<?php do_action( 'kom_meta_begin' ); ?>
			<?php if ( $klarna_order ) : ?>

				<strong>
					<?php esc_html_e( 'Klarna Environment: ', 'klarna-checkout-for-woocommerce' ); ?>
				</strong>
				<?php echo ( esc_html( apply_filters( 'kom_meta_environment', $environment ) ) ); ?><br />

				<strong>
					<?php esc_html_e( 'Klarna order status: ', 'klarna-checkout-for-woocommerce' ); ?>
				</strong>
				<?php echo ( esc_html( apply_filters( 'kom_meta_order_status', $klarna_order->status ) ) ); ?><br />

				<strong>
					<?php esc_html_e( 'Initial Payment method: ', 'klarna-checkout-for-woocommerce' ); ?>
				</strong>
				<?php echo ( esc_html( apply_filters( 'kom_meta_payment_method', $klarna_order->initial_payment_method->description ) ) ); ?></br>

				<?php
				if ( ! empty( $session_id ) ) :
					WC_Klarna_Order_Management_Scheduled_Actions::print_scheduled_actions( $session_id );
				endif;
				?>

				<ul class="kom_order_actions_wrapper submitbox">
					<?php if ( $actions['any'] ) : ?>
						<li class="wide" id="kom-capture">
							<select class="kco_order_actions" name="kom_order_actions" id="kom_order_actions">
								<option value="">
									<?php echo esc_attr( __( 'Choose an action...', 'woocommerce' ) ); ?>
								</option>
								<?php do_action( 'kom_meta_action_options', $order_id, $klarna_order, $actions ); ?>
							</select>
							<button class="button wc-reload"><span>
									<?php esc_html_e( 'Apply', 'woocommerce' ); ?>
								</span></button>
							<span class="woocommerce-help-tip"
								data-tip="<?php do_action( 'kom_meta_action_tips', $order_id, $klarna_order, $actions ); ?>"></span>
						</li>
					<?php else : ?>
						<?php do_action( 'kom_meta_no_actions', $order_id, $klarna_order, $actions ); ?>
					<?php endif; ?>
				</ul>

			<?php else : ?>

				<ul class="kom_order_actions_wrapper submitbox">
					<?php do_action( 'kom_meta_uncaptured_begin' ); ?>
					<li class="wide" id="kom-capture">
						<input type="text" id="klarna_order_id" name="klarna_order_id" class="klarna_order_id"
							placeholder="Klarna order ID">
						<button class="button wc-reload"><span>
								<?php esc_html_e( 'Apply', 'woocommerce' ); ?>
							</span></button>
					</li>
					<?php do_action( 'kom_meta_uncaptured_end' ); ?>
				</ul>
			<?php endif; ?>

			<div class="kom_order_sync">
				<div class="kom_order_sync--box">
					<div class="kom_order_sync--toggle">
						<p><label>Order management
								<?php echo wc_help_tip( __( 'Disable this to turn off the automatic synchronization with the Klarna Merchant Portal. When disabled, any changes in either system have to be done manually.', 'klarna-checkout-for-woocommerce' ) ); //phpcs:ignore -- string literal. ?>
							</label></p>
						<span class="woocommerce-input-toggle woocommerce-input-toggle--<?php echo esc_attr( $kom_disconnected_status ); ?>"></span>
					</div>
					<div class="kom_order_sync--action">
						<a class="button submit_button"
							href="<?php echo esc_url( wp_nonce_url( $kom_disconnected_url, 'kom_disconnect', $kom_disconnected_key ) ); ?>">
								<?php esc_attr_e( 'OK' ); ?></a>
						<a class="button cancel_button">Cancel</a>
					</div>
				</div>
				<a class="kom_order_sync_edit" href="#">Edit</a>
			</div>
			<?php do_action( 'kom_meta_end' ); ?>
		</div>
		<?php
	}

	/**
	 * Print an <option> HTML element.
	 *
	 * @param  string $value The Option's value.
	 * @param  string $text The descriptive text for this option.
	 * @return void
	 */
	protected function print_option( $value, $text ) {
		printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_attr( $text ) );
	}

	/**
	 * Print an action/description pair for the help/tip section of the action submission form.
	 *
	 * @param  string $action The action to be described.
	 * @param  string $description The discription of said action.
	 * @return void
	 */
	protected function print_tip_fragment( $action, $description ) {
		printf( '%s: %s<br/>', esc_html( $action ), esc_html( $description ) );
	}

	/**
	 * Filter to return the human readable string for the environment.
	 *
	 * @param  string $environment The raw environment string.
	 * @return string The human readable environment string.
	 */
	public function filter_environment( $environment ) {
		switch ( $environment ) {
			case 'live':
				$environment = 'Production';
				break;
			case 'test':
				$environment = 'Playground';
				break;
			default:
				$environment = 'Unknown';
		}
		return $environment;
	}

	/**
	 * Determine if output associated with the Capture option is wanted.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 *
	 * @return bool Should Capture-related stuff be in the output?
	 */
	public function want_output_capture( $order_id, $klarna_order, $actions ) {
		$order = wc_get_order( $order_id );
		if ( ! $actions['capture'] ) {
			return false; // Capture is on auto, don't present action.
		}
		if ( ! empty( $order->get_meta( '_wc_klarna_capture_id' ) ) ) {
			return false; // Already captured, can't capture again.
		}
		if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
			return false; // Klarna says it's captured (in whole, or in part) at some point. Can't capture again.
		}
		if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if output associated with the Cancel option is wanted.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 *
	 * @return bool Should Capture-related stuff be in the output?
	 */
	public function want_output_cancel( $order_id, $klarna_order, $actions ) {
		$order = wc_get_order( $order_id );
		if ( ! $actions['cancel'] ) {
			return false; // Cancel is on auto, don't present action.
		}
		if ( ! empty( $order->get_meta( '_wc_klarna_pending_to_cancelled', true ) ) ) {
			return false; // A cancellation is already pending, can't cancel again.
		}
		if ( ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
			return false; // Can only cancel orders that are captured.
		}

		return true;
	}

	/**
	 * Capture option for kco_order_actions action list.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 * @return void
	 */
	public function output_option_capture( $order_id, $klarna_order, $actions ) {
		if ( $this->want_output_capture( $order_id, $klarna_order, $actions ) ) {
			$this->print_option( 'kom_capture', __( 'Capture order', 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Output the tip fragment for the Capture option, if relevant.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 * @return void
	 */
	public function output_tip_capture( $order_id, $klarna_order, $actions ) {
		if ( $this->want_output_capture( $order_id, $klarna_order, $actions ) ) {
			$this->print_tip_fragment( __( 'Capture order', 'klarna-checkout-for-woocommerce' ), __( 'Activates the order with Klarna.', 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Cancel option for kco_order_actions action list.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 * @return void
	 */
	public function output_option_cancel( $order_id, $klarna_order, $actions ) {
		if ( $this->want_output_cancel( $order_id, $klarna_order, $actions ) ) {
			$this->print_option( 'kom_cancel', __( 'Cancel order', 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Output the tip fragment for the Cancel option, if relevant.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 * @return void
	 */
	public function output_tip_cancel( $order_id, $klarna_order, $actions ) {
		if ( $this->want_output_cancel( $order_id, $klarna_order, $actions ) ) {
			$this->print_tip_fragment( __( 'Cancel order', 'klarna-checkout-for-woocommerce' ), __( 'Cancels the order with Klarna.', 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Sync option for kco_order_actions action list.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 * @return void
	 */
	public function output_option_sync( $order_id, $klarna_order, $actions ) {
		if ( $actions['sync'] ) {
			$this->print_option( 'kom_sync', __( 'Sync Klarna order', 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Output the tip fragment for the Sync option, if relevant.
	 *
	 * @param  int    $order_id The ID of the order being considered.
	 * @param  object $klarna_order The Klarna order object associated with this order.
	 * @param  array  $actions The enabled actions.
	 * @return void
	 */
	public function output_tip_sync( $order_id, $klarna_order, $actions ) {
		if ( $actions['sync'] ) {
			$this->print_tip_fragment( __( 'Sync Klarna order', 'klarna-checkout-for-woocommerce' ), __( 'Gets the order data from Klarna and saves it to the WooCommerce order.', 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Prints an error message for the OM Metabox
	 *
	 * @param string $message The error message.
	 * @return void
	 */
	public function print_error_content( $message ) {
		?>
		<div class="kom-meta-box-content">
			<p>
				<?php echo esc_html( $message ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handles KOM Actions
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post Object.
	 */
	public function process_kom_actions( $post_id, $post ) {
		$klarna_order_id = filter_input( INPUT_POST, 'klarna_order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$kom_action      = filter_input( INPUT_POST, 'kom_order_actions', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order           = wc_get_order( $post_id );
		// Bail if not a valid order.
		if ( ! $order ) {
			return;
		}
		if ( ! empty( $klarna_order_id ) ) {
			$order->update_meta_data( '_wc_klarna_order_id', $klarna_order_id );
			$order->set_transaction_id( $klarna_order_id );
			$order->save();
		}

		// If the KOM order actions is not set, or is empty bail.
		if ( empty( $kom_action ) ) {
			return;
		}

		// If we get here, process the action.
		// Capture order.
		if ( 'kom_capture' === $kom_action ) {
			WC_Klarna_Order_Management::get_instance()->capture_klarna_order( $post_id, true );
		}
		// Cancel order.
		if ( 'kom_cancel' === $kom_action ) {
			WC_Klarna_Order_Management::get_instance()->cancel_klarna_order( $post_id, true );
		}
		// Sync order.
		if ( 'kom_sync' === $kom_action ) {
			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $post_id );
			WC_Klarna_Sellers_App::populate_klarna_order( $post_id, $klarna_order );
		}
	}
}
new WC_Klarna_Meta_Box();

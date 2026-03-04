<?php
namespace Krokedil\KustomCheckout\OrderManagement;

use Krokedil\WooCommerce\OrderMetabox;
use Krokedil\Support\OrderSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MetaBox class.
 *
 * Handles the meta box for KOM
 */
class MetaBox extends OrderMetabox {
	/**
	 * Klarna Order Management instance.
	 *
	 * @var OrderManagement
	 */
	protected $order_management;

	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management Klarna Order Management instance.
	 */
	public function __construct( $order_management ) {
		$this->order_management = $order_management;
		parent::__construct( 'klarna-om', 'Klarna Order Management', $this->order_management->plugin_instance );

		add_action( 'add_meta_boxes', array( $this, 'kom_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'process_kom_actions' ), 45, 2 );

		add_filter( 'kom_meta_environment', array( $this, 'filter_environment' ), 10, 1 );

		add_action( 'kom_meta_action_options', array( $this, 'output_option_capture' ), 10, 3 );
		add_action( 'kom_meta_action_options', array( $this, 'output_option_cancel' ), 20, 3 );
		add_action( 'kom_meta_action_options', array( $this, 'output_option_sync' ), 30, 3 );

		add_action( 'kom_meta_action_tips', array( $this, 'output_tip_capture' ), 10, 3 );
		add_action( 'kom_meta_action_tips', array( $this, 'output_tip_cancel' ), 20, 3 );
		add_action( 'kom_meta_action_tips', array( $this, 'output_tip_sync' ), 30, 3 );

		$this->scripts[] = 'kom-admin-js';
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post|\WC_Order $post The post object or a WC Order for later versions of WooCommerce.
	 *
	 * @return void
	 */
	public function render_metabox( $post ) {
		?>
		<div class="krokedil_wc__metabox">
			<?php $this->metabox_content( $post ); ?>
		</div>
		<?php
	}

	/**
	 * Adds meta box to the side of a KCO or KP order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function kom_meta_box( $post_type ) {
		if ( in_array( $post_type, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			$order_id = Utility::get_the_ID();
			$order    = wc_get_order( $order_id );

			// If the order was not paid using the plugin that instanced this class, bail.
			if ( ! Utility::check_plugin_instance( $this->order_management->plugin_instance, $order->get_payment_method() ) ) {
				return;
			}

			if ( 'kco' === $order->get_payment_method() ) {
				add_meta_box(
					$this->id,
					$this->title,
					array( $this, 'render_metabox' ),
					$post_type,
					'side',
					'core'
				);
			}
		}
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post The WordPress post.
	 *
	 * @return void
	 */
	public function metabox_content( $post ) {
		$order_id = Utility::get_the_ID();
		$order    = wc_get_order( $order_id );

		// If the order was not paid using the plugin that instanced this class, bail.
		if ( ! Utility::check_plugin_instance( $this->order_management->plugin_instance, $order->get_payment_method() ) ) {
			return;
		}

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) && ! in_array( $order->get_status(), array( 'on-hold' ), true ) ) {
			$this->print_error_content( __( 'The payment has not been finalized with Klarna.', 'klarna-checkout-for-woocommerce' ) );
			return;
		}
		// False if automatic settings are enabled, true if not. If true then show the option.
		if ( ! empty( $order->get_transaction_id() ) || ! empty( $order->get_meta( '_wc_klarna_order_id', true ) ) ) {

			$klarna_order = $this->order_management->retrieve_klarna_order( $order_id );

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
		$order_id = Utility::get_the_ID();
		$order    = wc_get_order( $order_id );

		// If the order was not paid using the plugin that instanced this class, bail.
		if ( ! Utility::check_plugin_instance( $this->order_management->plugin_instance, $order->get_payment_method() ) ) {
			return;
		}

		$session_id  = $order->get_meta( '_kp_session_id' );
		$environment = ! empty( $order->get_meta( '_wc_klarna_environment' ) ) ? $order->get_meta( '_wc_klarna_environment' ) : '';

		self::output_info(
			__( 'Klarna Environment', 'klarna-checkout-for-woocommerce' ),
			apply_filters( 'kom_meta_environment', $environment )
		);

		self::output_info(
			__( 'Klarna order status', 'klarna-checkout-for-woocommerce' ),
			apply_filters( 'kom_meta_order_status', $klarna_order->status )
		);

		self::output_info(
			__( 'Initial Payment method', 'klarna-checkout-for-woocommerce' ),
			apply_filters( 'kom_meta_payment_method', $klarna_order->initial_payment_method->description )
		);

		if ( ! empty( $session_id ) ) {
			$scheduled_actions = ScheduledActions::get_scheduled_actions( $session_id );
			$link_text         = count( $scheduled_actions['complete'] ) . ' completed, ' . count( $scheduled_actions['failed'] ) . ' failed, ' . count( $scheduled_actions['pending'] ) . ' pending';
			$link_url          = admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=' . rawurlencode( $session_id ) . '&action=-1&paged=1&action2=-1' );

			self::output_info(
				__( 'Scheduled actions', 'klarna-checkout-for-woocommerce' ),
				'<a target="_blank" href="' . $link_url . '">' . $link_text . '</a>'
			);

		}
		( new OrderSupport() )->add_export_order_button( $order, true );
		self::output_actions_dropdown( $order_id, $klarna_order );
		self::output_collapsable_section( 'kom-advanced', __( 'Advanced', 'klarna-checkout-for-woocommerce' ), self::get_advanced_section_content( $order ) );
	}

	/**
	 * Output the actions dropdown for the metabox.
	 *
	 * @param int    $order_id The ID of the order being considered.
	 * @param object $klarna_order The Klarna order object associated with this order.
	 */
	protected function output_actions_dropdown( $order_id, $klarna_order ) {
		$settings = $this->order_management->settings->get_settings( $order_id );

		$actions            = array();
		$actions['capture'] = ( ! isset( $settings['kom_auto_capture'] ) || 'yes' === $settings['kom_auto_capture'] ) ? false : true;
		$actions['cancel']  = ( ! isset( $settings['kom_auto_cancel'] ) || 'yes' === $settings['kom_auto_cancel'] ) ? false : true;
		$actions['sync']    = ( ! isset( $settings['kom_auto_order_sync'] ) || 'yes' === $settings['kom_auto_order_sync'] ) ? false : true;
		$actions['any']     = ( $actions['capture'] || $actions['cancel'] || $actions['sync'] );

		ob_start();
		?>
		<ul class="kom_order_actions_wrapper submitbox">
			<?php if ( $actions['any'] ) : ?>
				<li class="wide" id="kom-capture">
					<select class="kco_order_actions" name="kom_order_actions" id="kom_order_actions">
						<option value=""><?php esc_attr_e( 'Choose an action...', 'woocommerce' ); ?></option>
						<?php do_action( 'kom_meta_action_options', $order_id, $klarna_order, $actions ); ?>
					</select>
					<button class="button wc-reload"><span><?php esc_html_e( 'Apply', 'woocommerce' ); ?></span></button>
					<span class="woocommerce-help-tip" data-tip="
					<?php
					ob_start();
					do_action( 'kom_meta_action_tips', $order_id, $klarna_order, $actions );
					echo esc_attr( ob_get_clean() );
					?>
					"></span>
				</li>
			<?php else : ?>
				<?php do_action( 'kom_meta_no_actions', $order_id, $klarna_order, $actions ); ?>
			<?php endif; ?>
		</ul>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Output the order management (sync/disconnect) section for the metabox.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 */
	protected function get_advanced_section_content( $order ) {
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

		$om_status = $order->get_meta( $kom_disconnected_key ) ? 'disabled' : 'enabled';
		$title     = __( 'Order management', 'klarna-checkout-for-woocommerce' );
		$tip       = __( 'Disable this to turn off the automatic synchronization with the Klarna Merchant Portal. When disabled, any changes in either system have to be done manually.', 'klarna-checkout-for-woocommerce' );
		$enabled   = 'enabled' === $om_status ? true : false;

		ob_start();
		self::output_toggle_switch( $title, $enabled, $tip, 'kom_order_sync--action', array( 'kom-order-sync' => $om_status ) );
		return ob_get_clean();
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
			$this->print_option( 'kom_sync', __( 'Get customer', 'klarna-checkout-for-woocommerce' ) );
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
			$this->print_tip_fragment( __( 'Get customer', 'klarna-checkout-for-woocommerce' ), __( 'Gets the customer data from Klarna and saves it to the WooCommerce order.', 'klarna-checkout-for-woocommerce' ) );
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
			$this->order_management->capture_klarna_order( $post_id, true );
		}
		// Cancel order.
		if ( 'kom_cancel' === $kom_action ) {
			$this->order_management->cancel_klarna_order( $post_id, true );
		}
		// Sync order.
		if ( 'kom_sync' === $kom_action ) {
			$klarna_order = $this->order_management->retrieve_klarna_order( $post_id );
			SellersApp::get_instance( $this->order_management )->populate_klarna_order( $post_id, $klarna_order );
		}
	}

	/**
	 * Retrieves scheduled Action Scheduler actions for a given order.
	 *
	 * @param int $order_id The ID of the order.
	 * @return array List of scheduled actions for the order.
	 */
	public function get_scheduled_actions_for_order( $order_id ) {
		if ( ! class_exists( 'ActionScheduler' ) ) {
			return array();
		}

		$actions = as_get_scheduled_actions(
			array(
				'args'     => array( $order_id ),
				'per_page' => -1,
				'status'   => '',
			)
		);

		$action_list = array();

		foreach ( $actions as $action ) {
			$action_list[] = array(
				'name'      => $action->get_hook(),
				'status'    => $action->get_status(),
				'scheduled' => $action->get_scheduled_date()->format( 'Y-m-d H:i:s' ),
			);
		}

		return $action_list;
	}
}

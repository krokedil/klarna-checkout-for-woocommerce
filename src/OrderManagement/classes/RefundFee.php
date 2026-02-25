<?php
/**
 * Refund fee class.
 * Handles the addition of return fees to refunds for Klarna orders.
 *
 * @package Klarna_Order_Management_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_Klarna_Refund_Fee class.
 */
class RefundFee {

	/**
	 * Refund order IDs to unhook.
	 *
	 * @var array
	 */
	public $refund_order_ids_to_unhook = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// Add return fee order lines to the admin order edit page.
		add_action( 'woocommerce_admin_order_items_after_shipping', array( $this, 'add_return_fee_order_lines_html' ), PHP_INT_MAX );

		// Show return fee info in the order.
		add_action( 'woocommerce_after_order_refund_item_name', array( $this, 'show_return_fee_info' ) );

		// Add the total return fee to the order totals summary.
		add_action( 'woocommerce_admin_order_totals_after_refunded', array( $this, 'add_refund_fee_to_order_totals_summary' ), 10, 1 );

		// Add the return fee to the refund order.
		add_action( 'woocommerce_create_refund', array( $this, 'add_return_fee_to_refund' ), 10, 2 );

		// Add the return fee info to the refund reason (for emails and my account page).
		add_filter( 'woocommerce_order_refund_get_reason', array( $this, 'add_return_fee_info_to_refund_reason' ), 10, 2 );

		// Declare refund as partially refunded if the order total is greater than the refund total.
		add_filter( 'woocommerce_order_is_partially_refunded', array( $this, 'woocommerce_order_is_partially_refunded' ), 10, 3 );

		// Unhook the refunded action temporarily for orders with return fees.
		add_action( 'woocommerce_order_status_refunded', array( $this, 'maybe_unhook_refund' ), 5 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'maybe_rehook_refund' ), 15 );
	}

	/**
	 * Add the return fee order line.
	 *
	 * @param int $order_id The WooCommerce order.
	 *
	 * @return void
	 */
	public function add_return_fee_order_lines_html( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
			return;
		}

		if ( ! $order->get_meta( '_wc_klarna_capture_id' ) ) {
			return;
		}

		// Check if the store country is supported for return fees.
		if ( ! $this->is_return_fee_supported_country( $order ) ) {
			return;
		}

		?>
		</tbody>
		<tbody id="klarna_return_fee" data-klarna-hide="yes" style="display: none;">
			<tr class="klarna-return-fee" data-order_item_id="klarna_return_fee">
				<td class="thumb"><div></div></td>
				<td class="name" >
					<div class="view">
				<?php esc_html_e( 'Klarna return fee', 'klarna-checkout-for-woocommerce' ); ?>
					</div>
				</td>
				<td class="item_cost" width="1%">&nbsp;</td>
				<td class="quantity" width="1%">&nbsp;</td>
				<td class="line_cost" width="1%">
					<div class="refund" style="display: none;">
						<input type="text" name="klarna_return_fee_amount" placeholder="0" class="refund_line_total wc_input_price" />
					</div>
				</td>
		<?php foreach ( $order->get_taxes() as $tax ) : ?>
					<?php if ( empty( $tax->get_rate_percent() ) ) : ?>
						<td class="line_tax" width="1%">&nbsp;</td>
					<?php else : ?>
						<td class="line_tax" width="1%">
							<div class="refund" style="display: none;">
								<input
									type="text"
									name="klarna_return_fee_tax_amount[<?php echo esc_attr( $tax->get_rate_id() ); ?>]"
									placeholder="0"
									class="refund_line_tax wc_input_price"
									data-tax_id="<?php echo esc_attr( $tax->get_rate_id() ); ?>"
								/>
							</div>
						</td>
						<?php break; ?>
					<?php endif; ?>
				<?php endforeach; ?>
				<td class="wc-order-edit-line-item">&nbsp;</td>
			</tr>
		<?php
	}

	/**
	 * Show the return fee info in the refund order.
	 *
	 * @param WC_Order $refund_order The refund order..
	 */
	public function show_return_fee_info( $refund_order ) {
		$return_fee = $refund_order->get_meta( '_klarna_return_fees' );

		// If its empty, just return.
		if ( empty( $return_fee ) ) {
			return;
		}

		$amount     = floatval( $return_fee['amount'] ) ?? 0;
		$tax_amount = floatval( $return_fee['tax_amount'] ) ?? 0;
		$total      = $amount + $tax_amount;

		// If the total is 0, just return.
		if ( $total <= 0 ) {
			return;
		}

		$original_amount = -1 * ( $total + abs( $refund_order->get_total() ) );

		?>
		<span class="klarna-return-fee-info display_meta" style="display: block; margin-top: 10px; color: #888; font-size: .92em!important;">
			<span style="font-weight: bold;"><?php esc_html_e( 'Refund amount: ' ); ?></span>
		<?php echo wp_kses_post( wc_price( $original_amount, array( 'currency' => $refund_order->get_currency() ) ) ); ?><br>
			<span style="font-weight: bold;"><?php esc_html_e( 'Return fee: ' ); ?></span>
		<?php echo wp_kses_post( wc_price( $total, array( 'currency' => $refund_order->get_currency() ) ) ); ?><br>
			<span style="font-weight: bold;"><?php esc_html_e( 'Refunded to customer: ' ); ?></span>
		<?php echo wp_kses_post( wc_price( $refund_order->get_total(), array( 'currency' => $refund_order->get_currency() ) ) ); ?>
		</span>
		<?php
	}

	/**
	 * Get the total return fee for the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return float
	 */
	public static function get_return_fee_total_for_order( $order ) {
		$return_fee_total = 0;

		foreach ( $order->get_refunds() as $refund ) {
			$return_fee = $refund->get_meta( '_klarna_return_fees' );
			if ( ! empty( $return_fee ) ) {
				$return_fee_total += floatval( $return_fee['amount'] ?? 0 );
				$return_fee_total += floatval( $return_fee['tax_amount'] ?? 0 );
			}
		}
		return $return_fee_total;
	}

	/**
	 * Add refund fee to order totals summary.
	 *
	 * @param int $order_id The order object.
	 */
	public function add_refund_fee_to_order_totals_summary( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// If the order is not a Klarna order, just return.
		if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
			return;
		}

		// Get total return fee for order.
		$total_return_fee = self::get_return_fee_total_for_order( $order );

		// If there is no return fee, just return.
		if ( empty( $total_return_fee ) ) {
			return;
		}
		?>
		<tr>
				<td class="label"><?php esc_html_e( 'Return fee', 'woocommerce' ); ?>:</td>
				<td width="1%"></td>
				<td class="total"><?php echo wc_price( $total_return_fee, array( 'currency' => $order->get_currency() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
		<?php
	}

	/**
	 * Get the return fee from the posted data.
	 *
	 * @return array
	 */
	public static function get_return_fee_from_post() {
		$return_fee = array(
			'amount'      => 0,
			'tax_amount'  => 0,
			'tax_rate_id' => 0,
		);

		$line_item_totals_json     = filter_input( INPUT_POST, 'line_item_totals', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$line_item_tax_totals_json = filter_input( INPUT_POST, 'line_item_tax_totals', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$line_item_totals     = json_decode( htmlspecialchars_decode( $line_item_totals_json ), true ) ?? array();
		$line_item_tax_totals = json_decode( htmlspecialchars_decode( $line_item_tax_totals_json ), true ) ?? array();

		foreach ( $line_item_totals as $key => $total ) {
			if ( 'klarna_return_fee' === $key ) {
				$return_fee['amount'] = str_replace( ',', '.', $total );
			}
		}
		// If the return fee amount is empty, just return.
		if ( empty( $return_fee['amount'] ) ) {
			return $return_fee;
		}

		foreach ( $line_item_tax_totals as $key => $tax_line ) {
			if ( 'klarna_return_fee' === $key ) {
				// Get the rate id from the tax by the first key in the line.
				$tax_rate_id               = array_keys( $tax_line )[0];
				$return_fee['tax_rate_id'] = $tax_rate_id;
				$return_fee['tax_amount']  = str_replace( ',', '.', $tax_line[ $tax_rate_id ] );
			}
		}

		return $return_fee;
	}

	/**
	 * Add the return fee to the refund.
	 *
	 * @param WC_Order_Refund $refund The refund order.
	 * @param array           $args   The arguments for the refund.
	 */
	public function add_return_fee_to_refund( $refund, $args ) {

		$order = wc_get_order( $refund->get_parent_id() );
		if ( ! $order ) {
			return;
		}

		// If the order is not a Klarna order, just return.
		if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
			return;
		}

		// Get the return fee from the posted data.
		$applied_return_fees = $this->get_return_fee_from_post();

		$total_return_fee_amount     = $applied_return_fees['amount'] ?? 0;
		$total_return_fee_tax_amount = $applied_return_fees['tax_amount'] ?? 0;
		$tax_rate_id                 = $applied_return_fees['tax_rate_id'] ?? 0;

		// If fee amount is empty, just return.
		if ( empty( $total_return_fee_amount ) ) {
			return;
		}

		// Manually calculate tax.
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		$location     = array();

		if ( 'billing' === $tax_based_on ) {
			$location['country']  = $order->get_billing_country();
			$location['state']    = $order->get_billing_state();
			$location['postcode'] = $order->get_billing_postcode();
			$location['city']     = $order->get_billing_city();
		} else { // shipping or default.
			$location['country']  = $order->get_shipping_country();
			$location['state']    = $order->get_shipping_state();
			$location['postcode'] = $order->get_shipping_postcode();
			$location['city']     = $order->get_shipping_city();
		}

		$tax_rates      = WC_Tax::find_rates( $location );
		$taxes          = WC_Tax::calc_tax( $total_return_fee_amount, $tax_rates, false );
		$tax_rate       = WC_Tax::_get_tax_rate( $tax_rate_id );
		$tax_class_slug = $tax_rate['tax_rate_class'] ?? '';

		// Create the fee item.
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( __( 'Return Fee', 'klarna-checkout-for-woocommerce' ) );
		$fee->set_tax_class( $tax_class_slug );
		$fee->set_tax_status( 'taxable' );

		// Set the amounts as negative values.
		$fee_line_total = -1 * abs( $total_return_fee_amount );
		$fee_tax_total  = -1 * abs( $total_return_fee_tax_amount );

		$fee->set_amount( $total_return_fee_amount );
		$fee->set_total( $total_return_fee_amount );
		$fee->calculate_taxes( $location );

		// Add the item.
		$refund->add_item( $fee );

		// Calculate totals.
		$current_total = $refund->get_total();
		$new_total     = $current_total - ( $fee_line_total + $fee_tax_total );

		$args['amount'] = $new_total;
		// Set the new total.
		$refund->set_amount( $new_total * -1 );
		$refund->update_taxes();
		$refund->calculate_totals( false );

		// Add applied fees as meta data to the refund.
		$refund->update_meta_data( '_klarna_return_fees', $applied_return_fees );

		// Save the refund. This saves both the line item and the updated totals.
		$refund->save();
	}

	/**
	 * Add the return fee info to the refund reason.
	 *
	 * @param string          $reason The refund reason.
	 * @param WC_Order_Refund $refund The refund order.
	 *
	 * @return string
	 */
	public function add_return_fee_info_to_refund_reason( $reason, $refund ) {

		$order = wc_get_order( $refund->get_parent_id() );

		// If the order is not set, just return the reason.
		if ( ! $order ) {
			return $reason;
		}

		// If the order is not a Klarna order, just return the reason.
		if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
			return $reason;
		}

		// If we're not on the my account page or in an email, return the reason as is.
		if ( ! $this->should_return_fee_info_be_shown() ) {
			return $reason;
		}

		// Get the return fee from the refund meta data.
		$klarna_return_fees = $refund->get_meta( '_klarna_return_fees' );
		if ( empty( $klarna_return_fees ) ) {
			return $reason;
		}

		$amount     = floatval( $klarna_return_fees['amount'] ?? 0 );
		$tax_amount = floatval( $klarna_return_fees['tax_amount'] ?? 0 );
		$total      = $amount + $tax_amount;
		// If the total is 0, just return the reason.
		if ( $total <= 0 ) {
			return $reason;
		}

		return sprintf(
		/* translators: %1$s: return fee amount, %2$s: refund reason */
			__( 'Return fee: %1$s<br>%2$s', 'klarna-checkout-for-woocommerce' ),
			wc_price( $total, array( 'currency' => $order->get_currency() ) ),
			$reason
		);
	}

	/**
	 * Check if the order is partially refunded.
	 *
	 * @param bool $is_partially_refunded Whether the order is partially refunded.
	 * @param int  $order_id              The order ID.
	 * @param int  $refund_id             The refund ID.
	 *
	 * @return bool
	 */
	public function woocommerce_order_is_partially_refunded( $is_partially_refunded, $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );

		// If the order is not a Klarna order, just return the original value.
		if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
			return $is_partially_refunded;
		}

		$refund_order = wc_get_order( $refund_id );

		// If the refund order is not set, just return the original value.
		if ( ! $refund_order ) {
			return $is_partially_refunded;
		}

		// If no payment was refunded, just return the original value.
		if ( ! $refund_order->get_refunded_payment() ) {
			return $is_partially_refunded;
		}

		$refund_total = abs( $refund_order->get_amount() );
		$return_fee   = $refund_order->get_meta( '_klarna_return_fees' );

		if ( empty( $return_fee ) ) {
			return $is_partially_refunded;
		}

		$refund_total += abs( floatval( $return_fee['amount'] ?? 0 ) ) + abs( floatval( $return_fee['tax_amount'] ?? 0 ) );
		// If order total is greater then refund total, then it is partially refunded.
		if ( abs( $order->get_total() ) > abs( $refund_total ) ) {
			return true;
		}

		$this->refund_order_ids_to_unhook[] = $order_id;

		return $is_partially_refunded;
	}

	/**
	 * Check if the return fee info should be shown.
	 *
	 * @return bool
	 */
	private function should_return_fee_info_be_shown() {
		if ( is_account_page() ) {
			return true;
		}
		if ( did_action( 'woocommerce_email_order_details' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the store base country supports return fees.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool True if supported, false otherwise.
	 */
	private function is_return_fee_supported_country( $order ) {
		$allowed_countries = array( 'AT', 'DE', 'DK', 'FI', 'FR', 'NL', 'NO', 'SE' );
		$order_country     = $order->get_billing_country();

		if ( in_array( $order_country, $allowed_countries, true ) ) {
			return true;
		}

		return false;
	}

	/** Maybe unhook the refund action temporarily.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 */
	public function maybe_unhook_refund( $order_id ) {
		if ( in_array( $order_id, $this->refund_order_ids_to_unhook, true ) ) {
			remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded', 10 );
		}
	}

	/** Maybe rehook the refund action.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 */
	public function maybe_rehook_refund( $order_id ) {
		if ( in_array( $order_id, $this->refund_order_ids_to_unhook, true ) ) {
			add_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded', 10 );
		}
	}
}

// Initialize the class.
new WC_Klarna_Refund_Fee();

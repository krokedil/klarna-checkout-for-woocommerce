<?php
namespace Krokedil\KustomCheckout\Upsell;

use WC_Order;

/**
 * Shows upsells that Kustom has accepted, but that wait for the push callback before they are added to the order,
 * as pending rows in the order details table. Refreshes the table when the customer adds an upsell in the confirmation iframe.
 */
class UpsellPendingDisplay {
	/**
	 * UpsellPendingDisplay constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_order_table_items', array( $this, 'output_pending_rows' ) );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'add_pending_totals' ), 10, 2 );
		add_action( 'woocommerce_thankyou_kco', array( $this, 'enqueue_script' ), 20 );
		add_action( 'wc_ajax_kco_wc_upsell_order_details', array( $this, 'ajax_order_details' ) );
	}

	/**
	 * Get the upsell lines waiting for the push callback, if the order still waits for it.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array[] The upsell lines as Kustom sent them to the upsell validation callback.
	 */
	public static function get_pending_lines( $order ) {
		if ( UpsellStatus::get_configured_status() !== $order->get_status() ) {
			return array();
		}

		$lines   = array();
		$batches = $order->get_meta( '_kco_upsell_data' );
		foreach ( is_array( $batches ) ? $batches : array() as $batch ) {
			foreach ( is_array( $batch ) ? $batch : array() as $line ) {
				if ( is_array( $line ) && ! empty( $line['reference'] ) ) {
					$lines[] = $line;
				}
			}
		}

		return $lines;
	}

	/**
	 * Output the pending upsell lines as rows in the order details table.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function output_pending_rows( $order ) {
		foreach ( self::get_pending_lines( $order ) as $line ) {
			$product = UpsellProcessor::find_product_by_reference( $line['reference'] );
			$name    = $product ? $product->get_name() : ( $line['name'] ?? $line['reference'] );
			?>
			<tr class="woocommerce-table__line-item order_item kco-upsell-pending">
				<td class="woocommerce-table__product-name product-name">
					<?php if ( $product && $product->is_visible() ) : ?>
						<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $name ); ?>
					<?php endif; ?>
					<strong class="product-quantity">&times;&nbsp;<?php echo absint( $line['quantity'] ?? 1 ); ?></strong>
					<br><small class="kco-upsell-pending__note"><?php esc_html_e( 'Awaiting order update confirmation.', 'klarna-checkout-for-woocommerce' ); ?></small>
				</td>
				<td class="woocommerce-table__product-total product-total">
					<?php echo wp_kses_post( wc_price( self::to_major_units( $line['total_amount'] ?? 0 ), array( 'currency' => $order->get_currency() ) ) ); ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Add the pending amount and the expected new total to the order totals, before the payment method.
	 *
	 * @param array    $total_rows The order totals rows.
	 * @param WC_Order $order      The WooCommerce order.
	 *
	 * @return array
	 */
	public function add_pending_totals( $total_rows, $order ) {
		$lines = self::get_pending_lines( $order );
		if ( empty( $lines ) ) {
			return $total_rows;
		}

		$pending = array_sum(
			array_map(
				function ( $line ) {
					return self::to_major_units( $line['total_amount'] ?? 0 );
				},
				$lines
			)
		);
		$args    = array( 'currency' => $order->get_currency() );
		$rows    = array(
			'kco_upsell_pending'   => array(
				'type'  => 'kco_upsell_pending',
				'label' => __( 'Awaiting confirmation:', 'klarna-checkout-for-woocommerce' ),
				'value' => '+ ' . wc_price( $pending, $args ),
			),
			'kco_upsell_new_total' => array(
				'type'  => 'kco_upsell_new_total',
				'label' => __( 'New total once confirmed:', 'klarna-checkout-for-woocommerce' ),
				'value' => wc_price( (float) $order->get_total() + $pending, $args ),
			),
		);

		$position = array_search( 'payment_method', array_keys( $total_rows ), true );
		if ( false === $position ) {
			return $total_rows + $rows;
		}

		return array_slice( $total_rows, 0, $position, true ) + $rows + array_slice( $total_rows, $position, null, true );
	}

	/**
	 * Enqueue the script that refreshes the order details when an upsell is added, while the order waits for upsells.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 */
	public function enqueue_script( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || UpsellStatus::get_configured_status() !== $order->get_status() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script(
			'kco-upsell-confirmation',
			plugins_url( 'assets/js/kco-upsell-confirmation' . $suffix . '.js', KCO_WC_MAIN_FILE ),
			array(),
			KCO_WC_VERSION,
			true
		);
		wp_localize_script(
			'kco-upsell-confirmation',
			'kcoUpsellParams',
			array(
				'url'       => \WC_AJAX::get_endpoint( 'kco_wc_upsell_order_details' ),
				'order_id'  => $order->get_id(),
				'order_key' => $order->get_order_key(),
			)
		);
	}

	/**
	 * Return the order details table HTML. The order key authorizes the request, the same way it does for the thank-you page.
	 *
	 * @return void
	 */
	public function ajax_order_details() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The order key is the authorization; a nonce would break on cached thank-you pages.
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$order = wc_get_order( $order_id );
		if ( ! $order || empty( $key ) || ! hash_equals( $order->get_order_key(), $key ) ) {
			wp_send_json_error( null, 403 );
		}

		self::add_confirmed_upsells( $order );

		ob_start();
		woocommerce_order_details_table( $order_id );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Add the pending upsells to the order once they are in the Kustom order, without waiting for the push.
	 * The request only triggers the check, the Kustom order is what decides. The order keeps waiting for the push,
	 * which still confirms and acknowledges it, so the customer can add more upsells.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public static function add_confirmed_upsells( $order ) {
		$klarna_order_id = $order->get_meta( '_wc_klarna_order_id' );
		if ( empty( $klarna_order_id ) || empty( self::get_pending_lines( $order ) ) ) {
			return;
		}

		// Same lock as the push, so the two can not add the same upsell twice.
		$lock_key = 'kco_push_lock_' . md5( $klarna_order_id );
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 2 * MINUTE_IN_SECONDS );

		try {
			$klarna_order = KCO_WC()->api->get_klarna_om_order( $klarna_order_id );
			if ( empty( $klarna_order ) || is_wp_error( $klarna_order ) ) {
				return;
			}

			( new UpsellProcessor( $order, $klarna_order ) )->process();
		} catch ( UpsellException $e ) {
			// Kustom may not have added the upsell to its order yet. The script asks again, and the push adds it otherwise.
			\KCO_Logger::log( $klarna_order_id . ': Upsell not added from the thank-you page yet: ' . $e->getMessage() );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Convert an amount from Kustom's minor units to major units.
	 *
	 * @param int $amount The amount in minor units.
	 *
	 * @return float
	 */
	private static function to_major_units( $amount ) {
		return round( (int) $amount / 100, wc_get_price_decimals() );
	}
}

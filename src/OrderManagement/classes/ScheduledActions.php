<?php
/**
 * Order scheduled actions display.
 *
 * Provides a way to display scheduled actions related to the order.
 *
 * @package OrderManagement
 * @since   1.9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OrderManagement_Scheduled_Actions class.
 *
 * Displays scheduled actions related to the order.
 */
class ScheduledActions {

	/**
	 * Gets the scheduled actions for the order.
	 *
	 * @param string $session_id The session ID.
	 * @return array
	 */
	private static function get_scheduled_actions( $session_id ) {
		$statuses          = array( 'complete', 'failed', 'pending' );
		$scheduled_actions = array();

		foreach ( $statuses as $status ) {
			$scheduled_actions[ $status ] = as_get_scheduled_actions(
				array(
					'search'   => $session_id,
					'status'   => array( $status ),
					'per_page' => -1,
				),
				'ids'
			);
		}

		return $scheduled_actions;
	}

	/**
	 * Prints the scheduled actions for the order.
	 *
	 * @param string $session_id The session ID.
	 * @return void
	 */
	public static function print_scheduled_actions( $session_id ) {
		$scheduled_actions = self::get_scheduled_actions( $session_id );
		$session_query_url = admin_url(
			'admin.php?page=wc-status&tab=action-scheduler&s=' . rawurlencode( $session_id ) . '&action=-1&paged=1&action2=-1'
		);
		?>
		<strong>
			<?php esc_html_e( 'Scheduled actions ', 'klarna-checkout-for-woocommerce' ); ?>
			<span class="woocommerce-help-tip"
					data-tip="<?php esc_html_e( 'See all actions scheduled for this order.', 'klarna-checkout-for-woocommerce' ); ?>">
			</span>
		</strong>
		<br />
		<a target="_blank" href="<?php echo esc_url( $session_query_url ); ?>">
			<?php
			printf(
			// translators: %1$d: number of completed orders, %2$d: number of failed orders, %3$d: number of pending orders.
				esc_html__( '%1$d completed, %2$d failed, %3$d pending', 'klarna-checkout-for-woocommerce' ),
				esc_html( count( $scheduled_actions['complete'] ) ),
				esc_html( count( $scheduled_actions['failed'] ) ),
				esc_html( count( $scheduled_actions['pending'] ) )
			);
			?>
		</a>
		<br />
		<?php
	}
}
new OrderManagement_Scheduled_Actions();

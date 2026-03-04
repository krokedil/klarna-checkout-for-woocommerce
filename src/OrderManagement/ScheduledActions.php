<?php
namespace Krokedil\KlarnaOrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ScheduledActions class.
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
	public static function get_scheduled_actions( $session_id ) {
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

		// Allow disabling the display of scheduled actions via a filter.
		if ( apply_filters( 'kom_skip_scheduled_actions', false ) ) {
			return;
		}

		$scheduled_actions = self::get_scheduled_actions( $session_id );
		$session_query_url = admin_url(
			'admin.php?page=wc-status&tab=action-scheduler&s=' . rawurlencode( $session_id ) . '&action=-1&paged=1&action2=-1'
		);
		?>
		<h4>
			<?php esc_html_e( 'Scheduled actions ', 'klarna-order-management-for-woocommerce' ); ?>
			<span class="woocommerce-help-tip"
					data-tip="<?php esc_html_e( 'See all actions scheduled for this order.', 'klarna-order-management-for-woocommerce' ); ?>">
			</span>
		</h4>
		<a target="_blank" href="<?php echo esc_url( $session_query_url ); ?>">
			<?php
			printf(
			// translators: %1$d: number of completed orders, %2$d: number of failed orders, %3$d: number of pending orders.
				esc_html__( '%1$d completed, %2$d failed, %3$d pending', 'klarna-order-management-for-woocommerce' ),
				esc_html( count( $scheduled_actions['complete'] ) ),
				esc_html( count( $scheduled_actions['failed'] ) ),
				esc_html( count( $scheduled_actions['pending'] ) )
			);
			?>
		</a>
		<?php
	}
}
new ScheduledActions();

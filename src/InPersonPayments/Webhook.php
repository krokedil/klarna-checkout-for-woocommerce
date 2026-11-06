<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The backstop for a tab that was closed before the customer paid.
 *
 * Kustom sends one webhook per merchant account, so a delivery is only trusted after
 * its signature checks out, and the session is read back from the API rather than
 * taken from the body.
 */
class Webhook {

	/**
	 * How long a delivery id is remembered, in seconds. Longer than the signature
	 * tolerance, so that a replay cannot outlive the record of it.
	 *
	 * @var int
	 */
	public const DEDUPE_LIFETIME = 900;

	/**
	 * Whether the delivery was signed with this merchant's secret.
	 *
	 * @param \WP_REST_Request $request The delivery.
	 * @return true|\WP_Error
	 */
	public static function verify( $request ) {
		$verified = WebhookSignature::verify(
			SettingsUtility::get_setting( 'ipp_webhook_secret', '' ),
			$request->get_header( 'webhook-id' ),
			$request->get_header( 'webhook-timestamp' ),
			$request->get_header( 'webhook-signature' ),
			$request->get_body()
		);

		if ( is_wp_error( $verified ) ) {
			// The message only ever names which check failed; the secret and the
			// signature stay out of the log.
			\KCO_Logger::log( 'WARNING Rejected a Kustom IPP webhook: ' . $verified->get_error_message() );

			return new \WP_Error( 'kco_ipp_unauthorized', __( 'Unauthorized.', 'klarna-checkout-for-woocommerce' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Apply a verified delivery.
	 *
	 * @param \WP_REST_Request $request The delivery.
	 * @return \WP_REST_Response
	 */
	public static function handle( $request ) {
		$webhook_id = $request->get_header( 'webhook-id' );

		if ( self::is_duplicate( $webhook_id ) ) {
			return new \WP_REST_Response( array( 'status' => 'duplicate' ), 200 );
		}

		$session_id = self::session_id( $request->get_json_params() );
		if ( '' === $session_id ) {
			return new \WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		$session = SessionHandler::fetch( $session_id );
		if ( is_wp_error( $session ) ) {
			\KCO_Logger::log( 'ERROR Kustom IPP webhook could not read session ' . $session_id . ': ' . $session->get_error_message() );

			// A 5xx makes Kustom redeliver, and forgetting the id lets that delivery in.
			self::forget( $webhook_id );

			return new \WP_REST_Response( array( 'status' => 'error' ), 503 );
		}

		$order = self::order_for( $session );
		if ( null === $order ) {
			\KCO_Logger::log( 'Kustom IPP webhook for session ' . $session_id . ' matched no order on this site.' );

			return new \WP_REST_Response( array( 'status' => 'unmatched' ), 200 );
		}

		$outcome = SessionHandler::sync( $order, $session );

		return new \WP_REST_Response( array( 'status' => $outcome['state'] ), 200 );
	}

	/**
	 * Whether this delivery has already been handled.
	 *
	 * @param string $webhook_id The webhook-id header.
	 * @return bool
	 */
	private static function is_duplicate( $webhook_id ) {
		$key = self::dedupe_key( $webhook_id );

		if ( false !== get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, 1, self::DEDUPE_LIFETIME );

		return false;
	}

	/**
	 * Let a delivery be handled again.
	 *
	 * @param string $webhook_id The webhook-id header.
	 * @return void
	 */
	private static function forget( $webhook_id ) {
		delete_transient( self::dedupe_key( $webhook_id ) );
	}

	/**
	 * The transient a delivery id is remembered in.
	 *
	 * @param string $webhook_id The webhook-id header.
	 * @return string
	 */
	private static function dedupe_key( $webhook_id ) {
		return 'kco_ipp_wh_' . md5( (string) $webhook_id );
	}

	/**
	 * The session a delivery is about. The event shape is not in the API description,
	 * so both a flat and a wrapped body are accepted.
	 *
	 * @param array|null $body The decoded delivery body.
	 * @return string
	 */
	private static function session_id( $body ) {
		if ( ! is_array( $body ) ) {
			return '';
		}

		$data = is_array( $body['data'] ?? null ) ? $body['data'] : array();

		foreach ( array( $body['session_id'] ?? '', $data['session_id'] ?? '', $data['id'] ?? '' ) as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				return sanitize_text_field( $candidate );
			}
		}

		return '';
	}

	/**
	 * The order a session belongs to, or null when it belongs to another site sharing
	 * the merchant account.
	 *
	 * @param array $session The session as Kustom reports it.
	 * @return \WC_Order|null
	 */
	private static function order_for( $session ) {
		$merchant_data = json_decode( (string) ( $session['merchant_data'] ?? '' ), true );
		$merchant_data = is_array( $merchant_data ) ? $merchant_data : array();

		$site_url = (string) ( $merchant_data['site_url'] ?? '' );
		if ( '' !== $site_url && untrailingslashit( $site_url ) !== untrailingslashit( home_url() ) ) {
			return null;
		}

		$order = wc_get_order( absint( $merchant_data['order_id'] ?? 0 ) );
		if ( ! $order ) {
			$order = wc_get_order( absint( $session['merchant_reference1'] ?? 0 ) );
		}

		if ( ! $order || Gateway::ID !== $order->get_payment_method() ) {
			return null;
		}

		$session_id = (string) ( $session['session_id'] ?? '' );

		return $order->get_meta( Gateway::SESSION_META ) === $session_id ? $order : null;
	}
}

<?php
namespace Krokedil\KustomCheckout\OrderManagement\Webhooks;

use Krokedil\KustomCheckout\OrderManagement\OrderManagement;
use Krokedil\KustomCheckout\OrderManagement\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhooks class.
 *
 * Receives webhooks from Kustom, verifies them and queues the supported events for processing.
 */
class Webhooks {
	/**
	 * The REST namespace.
	 */
	const REST_NAMESPACE = 'kustom/v1';

	/**
	 * The REST route.
	 */
	const REST_ROUTE = '/webhooks';

	/**
	 * The Action Scheduler hook that processes a capture.created event.
	 */
	const CAPTURE_CREATED_ACTION = 'kom_process_capture_created_webhook';

	/**
	 * The Action Scheduler group.
	 */
	const ACTION_GROUP = 'kco';

	/**
	 * The capture.created event handler.
	 *
	 * @var CaptureCreatedHandler
	 */
	protected $capture_created_handler;

	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management The order management instance.
	 */
	public function __construct( $order_management ) {
		$this->capture_created_handler = new CaptureCreatedHandler( $order_management );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( self::CAPTURE_CREATED_ACTION, array( $this->capture_created_handler, 'handle' ), 10, 2 );
	}

	/**
	 * Get the webhook URL the merchant should enter in the Kustom Portal.
	 *
	 * @return string
	 */
	public static function get_url() {
		return get_rest_url( null, self::REST_NAMESPACE . self::REST_ROUTE );
	}

	/**
	 * Register the webhook route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_request' ),
				// The request is authenticated by its signature in the callback.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle an incoming webhook.
	 *
	 * Responds quickly and leaves the processing to Action Scheduler, so Kustom doesn't time out and retry.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_request( $request ) {
		// Acknowledge the event so Kustom doesn't keep retrying while the sync is turned off.
		if ( ! Settings::is_capture_sync_enabled() ) {
			return new \WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		$verifier = new SignatureVerifier( Settings::get_webhook_secrets() );
		if ( ! $verifier->has_secrets() ) {
			\KCO_Logger::log( '[Webhook]: Received a webhook, but no signing secret is configured.' );
			return new \WP_REST_Response( array( 'error' => 'not_configured' ), 401 );
		}

		$body     = $request->get_body();
		$verified = $verifier->verify(
			$request->get_header( 'webhook-id' ),
			$request->get_header( 'webhook-timestamp' ),
			$request->get_header( 'webhook-signature' ),
			$body
		);

		if ( is_wp_error( $verified ) ) {
			\KCO_Logger::log( '[Webhook]: Rejected a webhook. ' . $verified->get_error_message() );
			return new \WP_REST_Response( array( 'error' => $verified->get_error_code() ), 401 );
		}

		$event = json_decode( $body, true );
		$type  = is_array( $event ) ? ( $event['type'] ?? '' ) : '';

		if ( 'capture.created' !== $type ) {
			return new \WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		$kustom_order_id = sanitize_text_field( $event['data']['order_id'] ?? '' );
		$capture_id      = sanitize_text_field( $event['data']['capture_id'] ?? '' );

		if ( empty( $kustom_order_id ) || empty( $capture_id ) ) {
			\KCO_Logger::log( '[Webhook]: Ignored a capture.created event without an order or capture id. Event: ' . sanitize_text_field( $event['id'] ?? '' ) );
			return new \WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		$this->schedule_capture_created( $kustom_order_id, $capture_id );

		\KCO_Logger::log( "[Webhook]: Queued capture.created for Kustom order {$kustom_order_id}, capture {$capture_id}." );

		return new \WP_REST_Response( array( 'status' => 'queued' ), 200 );
	}

	/**
	 * Queue a capture.created event for processing.
	 *
	 * The processing is delayed so a capture made from WooCommerce has time to save its capture id before the
	 * webhook it triggers is processed. Otherwise the capture could be registered twice.
	 *
	 * @param string $kustom_order_id The Kustom order id.
	 * @param string $capture_id      The Kustom capture id.
	 *
	 * @return void
	 */
	protected function schedule_capture_created( $kustom_order_id, $capture_id ) {
		$args = array(
			'kustom_order_id' => $kustom_order_id,
			'capture_id'      => $capture_id,
		);

		// Kustom retries deliveries, so the same event can arrive while it is still queued.
		if ( as_has_scheduled_action( self::CAPTURE_CREATED_ACTION, $args, self::ACTION_GROUP ) ) {
			return;
		}

		/**
		 * Filters how long to wait before processing a webhook, in seconds.
		 *
		 * @param int $delay The delay in seconds. Default 60.
		 */
		$delay = absint( apply_filters( 'kom_webhook_processing_delay', 60 ) );

		as_schedule_single_action( time() + $delay, self::CAPTURE_CREATED_ACTION, $args, self::ACTION_GROUP );
	}
}

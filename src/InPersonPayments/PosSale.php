<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\Blocks\InPersonPayments\BlockCheckout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A sale rung up on a point of sale through the Store API checkout, marked by a
 * `kco_ipp_pos` entry in its payment data: no customer, no address and no customer emails.
 */
class PosSale {

	/**
	 * The created_via of orders rung up on the point of sale.
	 *
	 * @var string
	 */
	public const CREATED_VIA = 'kustom-pos';

	/**
	 * Whether the order being checked out is a point of sale order, whose address is left empty.
	 *
	 * @var bool
	 */
	private static $relax_address = false;

	/**
	 * The customer emails a point of sale order has no customer to send to.
	 *
	 * @var string[]
	 */
	public const CUSTOMER_EMAILS = array( 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_invoice', 'customer_note' );

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'remove_customer' ), 10, 2 );
		add_filter( 'woocommerce_get_country_locale_default', array( $this, 'relax_address_fields' ), 100 );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'relax_locales' ), 100 );

		foreach ( self::CUSTOMER_EMAILS as $email_id ) {
			add_filter( "woocommerce_email_recipient_{$email_id}", array( $this, 'skip_customer_email' ), 10, 2 );
		}
	}

	/**
	 * A point of sale order has no customer: the Store API fills the order in from the
	 * salesperson's own account, so that is undone before the order is validated and paid.
	 *
	 * @param \WC_Order        $order The order.
	 * @param \WP_REST_Request $request The checkout request.
	 * @return void
	 */
	public function remove_customer( $order, $request ) {
		if ( Gateway::ID !== $order->get_payment_method() || ! self::is_pos_request( $request ) || ! current_user_can( Gateway::required_capability() ) ) {
			return;
		}

		$order->set_customer_id( 0 );

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			foreach ( array_keys( WC()->countries->get_default_address_fields() ) as $field ) {
				$setter = "set_{$type}_{$field}";
				if ( is_callable( array( $order, $setter ) ) ) {
					$order->$setter( '' );
				}
			}
		}
		$order->set_billing_phone( '' );
		$order->set_shipping_phone( '' );

		// WooCommerce refuses an order without a billing email and country, so they are the store's own.
		$order->set_billing_email( Checkout::fallback_email() );
		$order->set_billing_country( WC()->countries->get_base_country() );
		$order->set_created_via( self::CREATED_VIA );

		// The Store API validates the address against the country locale next. The relaxed in-person locale is only
		// registered for stores on the checkout block, and the locale is cached per request, so relax it here and rebuild it.
		self::$relax_address    = true;
		WC()->countries->locale = array();
	}

	/**
	 * Make the address fields optional while a point of sale order is checked out.
	 *
	 * @param array $fields The address fields.
	 * @return array
	 */
	public function relax_address_fields( $fields ) {
		if ( ! self::$relax_address ) {
			return $fields;
		}

		foreach ( BlockCheckout::OPTIONAL_FIELDS as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ]['required'] = false;
			}
		}

		return $fields;
	}

	/**
	 * Make each country's address fields optional while a point of sale order is checked out.
	 *
	 * @param array $locales The address fields by country.
	 * @return array
	 */
	public function relax_locales( $locales ) {
		if ( ! self::$relax_address ) {
			return $locales;
		}

		foreach ( $locales as $country => $fields ) {
			$locales[ $country ] = $this->relax_address_fields( (array) $fields );
		}

		return $locales;
	}

	/**
	 * Whether a Store API checkout came from the point of sale.
	 *
	 * @param \WP_REST_Request $request The checkout request.
	 * @return bool
	 */
	private static function is_pos_request( $request ) {
		foreach ( (array) $request['payment_data'] as $item ) {
			if ( 'kco_ipp_pos' === ( $item['key'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Send no customer emails for a point of sale order: its email is the store's own.
	 *
	 * @param string         $recipient The recipient.
	 * @param \WC_Order|null $order The order the email is about.
	 * @return string
	 */
	public function skip_customer_email( $recipient, $order ) {
		if ( $order instanceof \WC_Order && self::CREATED_VIA === $order->get_created_via() && Checkout::fallback_email() === $order->get_billing_email() ) {
			return '';
		}

		return $recipient;
	}
}

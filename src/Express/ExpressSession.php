<?php
namespace Krokedil\KustomCheckout\Express;

use Automattic\WooCommerce\StoreApi\SessionHandler;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use Krokedil\KustomCheckout\Utility\BlocksUtility;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Express session class.
 *
 * A separate Store API session, with its own cart token, that holds a product express purchase so the
 * validation callback can submit it without touching the shopper's own cart.
 */
class ExpressSession {
	/**
	 * Run a callback with WC()->session, WC()->cart and WC()->customer swapped for a new session
	 * holding only the given product. The shopper's own session and cart are restored afterwards.
	 *
	 * @param int      $product_id   The product ID.
	 * @param int      $variation_id The variation ID, or 0.
	 * @param int      $quantity     The quantity.
	 * @param array    $variation    The variation attributes, keyed by attribute_* name.
	 * @param callable $callback     Receives the express cart token and session key. Its return value is returned.
	 * @return mixed
	 * @throws Exception If the product could not be added to the express cart.
	 */
	public static function with_product_cart( $product_id, $variation_id, $quantity, $variation, $callback ) {
		$session_key = 't_' . substr( md5( wp_generate_uuid4() ), 0, 30 );
		$token       = BlocksUtility::create_cart_token( $session_key );
		$session     = self::open( $token );

		$original = array(
			'session'  => WC()->session,
			'cart'     => WC()->cart,
			'customer' => WC()->customer,
		);

		// The shopper's cart hooks would otherwise write their cart into the express session, or recalculate it.
		$detached = self::detach_callbacks(
			static function ( $callback_object ) use ( $original ) {
				return $callback_object === $original['cart'] || $callback_object instanceof \WC_Cart_Session;
			}
		);

		// Build the express cart without its session hooks: they set cart cookies and update the persistent cart.
		$cart_session = null;
		$capture      = static function ( $initialize, $new_session ) use ( &$cart_session ) {
			$cart_session = $new_session;
			return false;
		};

		WC()->session  = $session;
		WC()->customer = $original['customer'] ? clone $original['customer'] : new \WC_Customer( get_current_user_id(), true );

		add_filter( 'woocommerce_cart_session_initialize', $capture, PHP_INT_MAX, 2 );
		WC()->cart = new \WC_Cart();
		remove_filter( 'woocommerce_cart_session_initialize', $capture, PHP_INT_MAX );

		$express_cart = WC()->cart;

		try {
			$session->set( 'cart', array() );
			$session->set( 'kco_wc_cart_token', $token );
			$session->set( 'chosen_payment_method', 'kco' );

			wc_clear_notices();
			$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce core hook.
			$added  = $passed ? $express_cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) : false;

			if ( ! $added ) {
				$errors = wc_get_notices( 'error' );
				$notice = reset( $errors );
				throw new Exception( wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : 'Could not add the product to the express cart.' ) );
			}

			$express_cart->calculate_shipping();
			$express_cart->calculate_totals();
			$cart_session->set_session();

			$result = call_user_func( $callback, $token, $session_key );

			$cart_session->set_session();
			wc_clear_notices();
			self::save( $session );

			return $result;
		} finally {
			self::detach_callbacks(
				static function ( $callback_object ) use ( $express_cart ) {
					return $callback_object === $express_cart;
				}
			);
			self::reattach_callbacks( $detached );

			WC()->session  = $original['session'];
			WC()->cart     = $original['cart'];
			WC()->customer = $original['customer'];
		}
	}

	/**
	 * The session keys prime_for_validation() writes, which restore_after_validation() puts back.
	 *
	 * @var string[]
	 */
	const PRIMED_KEYS = array( 'kco_wc_order_id', 'chosen_payment_method', 'kco_kss_enabled', 'chosen_shipping_methods' );

	/**
	 * Prepare the session a validation callback submits, with what the express sheet chose in Kustom.
	 *
	 * In the iframe the checkout script syncs the KSA selection to the session as the shopper picks it. The express
	 * sheet has no such script, so the selection is written here before the Store API places the order.
	 *
	 * @param string $token        The cart token from the merchant data.
	 * @param array  $klarna_order The Kustom order.
	 * @return array The values the primed keys held before, for restore_after_validation().
	 */
	public static function prime_for_validation( $token, $klarna_order ) {
		$session         = self::open( $token );
		$klarna_order_id = $klarna_order['order_id'];

		$previous = array();
		foreach ( self::PRIMED_KEYS as $key ) {
			$previous[ $key ] = $session->get( $key );
		}

		// Read by process_payment() and the KSA shipping method while the Store API places the order.
		$session->set( 'kco_wc_order_id', $klarna_order_id );
		$session->set( 'chosen_payment_method', 'kco' );

		$shipping = $klarna_order['selected_shipping_option'] ?? array();
		if ( ! empty( $shipping ) ) {
			$shipping['currency'] = $klarna_order['purchase_currency'] ?? get_woocommerce_currency();
			set_transient( 'kss_data_' . $klarna_order_id, $shipping, HOUR_IN_SECONDS );

			$session->set( 'kco_kss_enabled', true );
			$session->set( 'chosen_shipping_methods', array( self::get_shipping_rate_id( $shipping, $klarna_order['shipping_address'] ?? array() ) ) );

			foreach ( array_keys( $session->get_session_data() ) as $key ) {
				if ( 0 === strpos( (string) $key, 'shipping_for_package_' ) ) {
					$session->__unset( $key );
				}
			}
		}

		self::save( $session );

		return $previous;
	}

	/**
	 * Put back what prime_for_validation() replaced, once the Store API has placed the order or refused it.
	 *
	 * For a cart express purchase this is the shopper's own session, which must not keep the express order id: the
	 * iframe checkout would update and reuse it if the purchase is then abandoned.
	 *
	 * @param string $token           The cart token from the merchant data.
	 * @param string $klarna_order_id The express Kustom order id.
	 * @param array  $previous        What prime_for_validation() returned.
	 */
	public static function restore_after_validation( $token, $klarna_order_id, $previous ) {
		$session = self::open( $token );

		// Something else changed the session since, e.g. the shopper picked a new iframe order; leave it.
		if ( $session->get( 'kco_wc_order_id' ) !== $klarna_order_id ) {
			return;
		}

		foreach ( self::PRIMED_KEYS as $key ) {
			if ( null === ( $previous[ $key ] ?? null ) ) {
				$session->__unset( $key );
			} else {
				$session->set( $key, $previous[ $key ] );
			}
		}

		self::save( $session );
	}

	/**
	 * The shipping rate id to choose for the option selected in Kustom.
	 *
	 * Mirrors ShippingAssistant\Checkout::set_shipping_method(), but matches the zone from the Kustom address,
	 * since there is no WooCommerce cart in a validation callback.
	 *
	 * @param array $shipping The selected shipping option.
	 * @param array $address  The Kustom shipping address.
	 * @return string
	 */
	private static function get_shipping_rate_id( $shipping, $address ) {
		$rate_id   = (string) ( $shipping['id'] ?? '' );
		$method_id = current( explode( ':', $rate_id ) );
		$methods   = WC()->shipping()->get_shipping_methods();

		if ( 'klarna_kss' !== $method_id && isset( $methods[ $method_id ] ) ) {
			return wc_clean( $rate_id );
		}

		$country = strtoupper( $address['country'] ?? '' );
		$region  = $address['region'] ?? '';
		$zone    = \WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => $country,
					'state'    => function_exists( 'kco_convert_region' ) && $region ? kco_convert_region( $region, $country ) : $region,
					'postcode' => $address['postal_code'] ?? '',
				),
			)
		);

		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'klarna_kss' === $method->id ) {
				return $method->get_rate_id();
			}
		}

		return 'klarna_kss';
	}

	/**
	 * Load the Store API session a cart token points at, without saving it on shutdown.
	 *
	 * @param string $token The cart token.
	 * @return SessionHandler
	 * @throws Exception If the token is not valid.
	 */
	public static function open( $token ) {
		if ( empty( $token ) || ! JsonWebToken::validate( $token, '@' . wp_salt() ) ) {
			throw new Exception( 'Invalid cart token.' );
		}

		$previous                   = $_SERVER['HTTP_CART_TOKEN'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only restored below.
		$_SERVER['HTTP_CART_TOKEN'] = $token;

		$session = new SessionHandler();
		$session->init();
		remove_action( 'shutdown', array( $session, 'save_data' ), 20 );

		if ( null === $previous ) {
			unset( $_SERVER['HTTP_CART_TOKEN'] );
		} else {
			$_SERVER['HTTP_CART_TOKEN'] = $previous;
		}

		return $session;
	}

	/**
	 * Persist a Store API session, and drop the cached copy the cookie session handler would read instead.
	 *
	 * @param SessionHandler $session The session.
	 */
	public static function save( $session ) {
		$session->save_data();

		if ( class_exists( 'WC_Cache_Helper' ) && defined( 'WC_SESSION_CACHE_GROUP' ) ) {
			wp_cache_delete( \WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP ) . $session->get_customer_id(), WC_SESSION_CACHE_GROUP );
		}
	}

	/**
	 * Remove every hooked method whose object matches, and return them so they can be put back.
	 *
	 * @param callable $matches Receives the callback object, returns whether to remove it.
	 * @return array
	 */
	private static function detach_callbacks( $matches ) {
		global $wp_filter;

		$detached = array();
		foreach ( $wp_filter as $hook_name => $hook ) {
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( ! is_array( $function ) || ! is_object( $function[0] ) || ! $matches( $function[0] ) ) {
						continue;
					}

					remove_filter( $hook_name, $function, $priority );
					$detached[] = array( $hook_name, $function, $priority, $callback['accepted_args'] );
				}
			}
		}

		return $detached;
	}

	/**
	 * Put back callbacks removed by detach_callbacks().
	 *
	 * @param array $detached The removed callbacks.
	 */
	private static function reattach_callbacks( $detached ) {
		foreach ( $detached as $callback ) {
			add_filter( $callback[0], $callback[1], $callback[2], $callback[3] );
		}
	}
}

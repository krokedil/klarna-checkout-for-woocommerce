<?php
namespace Krokedil\KustomCheckout\InPersonPayments;

use Krokedil\KustomCheckout\InPersonPayments\Request\Get\RequestGetDevices;
use Krokedil\KustomCheckout\Utility\SettingsUtility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The merchant's paired devices, cached so that rendering the checkout does not cost
 * a provider round trip on every page load.
 */
class Devices {

	/**
	 * The prefix of the transient the device list is cached in.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'kco_ipp_devices';

	/**
	 * The cached device list, fetching it when the cache is cold.
	 *
	 * @return array|\WP_Error
	 */
	public static function all() {
		$cached = get_transient( self::transient() );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		return self::refresh();
	}

	/**
	 * Fetch the device list and cache it.
	 *
	 * @return array|\WP_Error
	 */
	public static function refresh() {
		$response = ( new RequestGetDevices() )->request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$devices = $response['content'] ?? array();

		/**
		 * How long the paired device list is cached, in seconds.
		 *
		 * A longer lifetime saves round trips on every checkout render; a device paired
		 * or renamed in the meantime shows up that much later.
		 *
		 * @since 2.22.0
		 *
		 * @param int $lifetime The cache lifetime in seconds. Default one minute.
		 */
		$lifetime = apply_filters( 'kco_ipp_device_cache_lifetime', MINUTE_IN_SECONDS );

		set_transient( self::transient(), $devices, $lifetime );

		return $devices;
	}

	/**
	 * Throw the cached list away.
	 *
	 * @return void
	 */
	public static function flush() {
		SettingsUtility::flush();
		delete_transient( self::transient() );
	}

	/**
	 * The transient for the account the store is talking to right now. Test and live,
	 * and two merchant ids, each have their own devices.
	 *
	 * @return string
	 */
	private static function transient() {
		$prefix      = SettingsUtility::is_testmode() ? 'test_' : '';
		$merchant_id = (string) SettingsUtility::get_setting( "{$prefix}merchant_id", '' );

		return self::TRANSIENT . '_' . md5( $prefix . $merchant_id );
	}

	/**
	 * Find one device by id.
	 *
	 * @param array  $devices The devices from the API.
	 * @param string $device_id The device to look for.
	 * @return array|null
	 */
	public static function find( $devices, $device_id ) {
		foreach ( $devices as $device ) {
			if ( ( $device['id'] ?? '' ) === $device_id ) {
				return $device;
			}
		}

		return null;
	}
}

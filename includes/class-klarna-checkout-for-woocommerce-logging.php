<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna_Checkout_For_WooCommerce_AJAX class.
 *
 * Registers AJAX actions for Klarna Checkout for WooCommerce.
 *
 * @extends WC_AJAX
 */
class Klarna_Checkout_For_WooCommerce_Logging {

	/** @var WC_Logger Logger instance */
	private $logger = false;

	/**
	 * Logging function.
	 *
	 * @param string $message Error message.
	 * @param string $level   Error level.
	 */
	public function log( $message, $level = 'info' ) {
		if ( $this->log_enabled() ) {
			if ( empty( $this->logger ) ) {
				$this->logger = wc_get_logger();
			}

			$this->logger->log( $level, $message, array( 'source' => 'klarna-checkout-for-woocommerce' ) );
		}
	}

	/**
	 * Checks if logging is enabled in plugin settings.
	 *
	 * @return bool
	 */
	private function log_enabled() {
		$settings = get_option( 'woocommerce_kco_settings' );

		return ( null !== $settings['logging'] && 'yes' === $settings['logging'] );
	}

}

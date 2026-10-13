<?php
namespace Krokedil\KustomCheckout\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility class for the YITH WooCommerce Product Add-ons & Extra Options plugin.
 *
 * Add-ons that the plugin is configured to sell individually are not added to the cart as the products they
 * represent. A single hidden placeholder product, named after the plugin itself, is added instead, and the
 * actual selection is kept in the cart item data. YITH hides that product in the cart, and renames the order
 * item once the order is created, but the order lines Kustom receives are built from the cart, and would
 * otherwise carry the placeholder product's name and permalink.
 *
 * @see https://wordpress.org/plugins/yith-woocommerce-product-add-ons/
 */
class YithProductAddons {

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// If YITH WooCommerce Product Add-ons is not active, bail.
		if ( ! class_exists( 'YITH_WAPO' ) ) {
			return;
		}

		add_filter( 'kco_wc_cart_line_item', array( __CLASS__, 'set_addon_order_line' ), 10, 2 );
	}

	/**
	 * Describe an add-on sold individually in the order line that is sent to Kustom.
	 *
	 * @param array $klarna_item The Kustom order line.
	 * @param array $cart_item The WooCommerce cart item.
	 *
	 * @return array
	 */
	public static function set_addon_order_line( $klarna_item, $cart_item ) {
		if ( ! isset( $cart_item['yith_wapo_individual_addons'] ) ) {
			return $klarna_item;
		}

		$parent_product = self::get_parent_product( $cart_item );
		$addon_name     = self::get_addon_name( $cart_item, $parent_product );

		// Truncated the same way KCO_Request_Cart::get_item_name does it.
		if ( ! empty( $addon_name ) ) {
			$klarna_item['name'] = substr( wp_strip_all_tags( $addon_name ), 0, 254 );
		}

		// The placeholder product is private and has no image, so refer to the product the add-on belongs to
		// instead. Only if the URLs are already present, since the merchant may have turned them off.
		if ( $parent_product && isset( $klarna_item['product_url'] ) ) {
			$klarna_item['product_url'] = $parent_product->get_permalink();

			$image_url = self::get_image_url( $parent_product );
			if ( $image_url ) {
				$klarna_item['image_url'] = $image_url;
			}
		}

		return $klarna_item;
	}

	/**
	 * Get the name of an add-on sold individually.
	 *
	 * The placeholder product is named after the plugin itself, and is therefore meaningless to the customer.
	 * The add-on is described by YITH the same way it is described in the WooCommerce cart.
	 *
	 * @param array             $cart_item The WooCommerce cart item.
	 * @param \WC_Product|false $parent_product The product the add-on belongs to.
	 *
	 * @return string The name of the add-on, or an empty string if it could not be determined.
	 */
	private static function get_addon_name( $cart_item, $parent_product ) {
		$names = array();

		// The helpers are only available in some versions of YITH WooCommerce Product Add-ons.
		if ( isset( $cart_item['yith_wapo_options'] ) && class_exists( 'YITH_WAPO_Cart' ) ) {
			$wapo      = \YITH_WAPO::get_instance();
			$wapo_cart = \YITH_WAPO_Cart::get_instance();

			if ( method_exists( $wapo, 'split_addon_and_option_ids' ) && method_exists( $wapo_cart, 'get_addon_value_on_cart' ) ) {
				foreach ( $cart_item['yith_wapo_options'] as $option ) {
					foreach ( $option as $key => $value ) {
						if ( empty( $key ) || '' === $value ) {
							continue;
						}

						// Add-ons that accept several values are stored as an array by YITH.
						if ( is_array( $value ) && isset( $value[0] ) ) {
							$value = $value[0];
						}

						$ids = $wapo->split_addon_and_option_ids( $key, $value );

						// The value can contain markup, such as a link to an uploaded file.
						$name = trim( wp_strip_all_tags( $wapo_cart->get_addon_value_on_cart( $ids['addon_id'], $ids['option_id'], $key, $value, $cart_item ) ) );
						if ( ! empty( $name ) ) {
							$names[] = $name;
						}
					}
				}
			}
		}

		if ( ! empty( $names ) ) {
			$addon_name = implode( ', ', $names );
		} elseif ( $parent_product ) {
			// Fall back to the product the add-on belongs to if YITH could not describe the add-on for us.
			// translators: %s the name of the product that the add-on belongs to.
			$addon_name = sprintf( __( 'Add-on for %s', 'klarna-checkout-for-woocommerce' ), $parent_product->get_name() );
		} else {
			// Without a product to refer to, there is nothing better to say than what the order line already says.
			return '';
		}

		/**
		 * Filter the name that is sent to Kustom for a YITH WAPO add-on sold individually.
		 *
		 * @since 2.20.8
		 *
		 * @param string $addon_name The name of the add-on.
		 * @param array  $cart_item The WooCommerce cart item.
		 */
		return apply_filters( 'kco_yith_wapo_addon_name', $addon_name, $cart_item );
	}

	/**
	 * Get the product that an add-on sold individually belongs to.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 *
	 * @return \WC_Product|false The product, or false if it could not be found.
	 */
	private static function get_parent_product( $cart_item ) {
		$product_ids = array(
			$cart_item['yith_wapo_variation_id'] ?? 0,
			$cart_item['yith_wapo_product_id'] ?? 0,
		);

		foreach ( $product_ids as $product_id ) {
			if ( empty( $product_id ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( $product instanceof \WC_Product ) {
				return $product;
			}
		}

		return false;
	}

	/**
	 * Get the image URL of a product.
	 *
	 * @param \WC_Product $product The product.
	 *
	 * @return string|false The image URL, or false if the product has no image.
	 */
	private static function get_image_url( $product ) {
		if ( $product->get_image_id() > 0 ) {
			return wp_get_attachment_image_url( $product->get_image_id(), 'shop_single', false );
		}

		return false;
	}
}

<?php
/**
 * Template file for the shipping section of the cart.
 *
 * @package KlarnaShippingService/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shipping_id = WC()->session->get( 'chosen_shipping_methods' )[0];
$packages    = WC()->shipping->get_packages();
$rate        = null;
foreach ( $packages as $package ) {
	if ( isset( $package['rates'] ) && isset( $package['rates'][ $shipping_id ] ) ) {
		$rate = $package['rates'][ $shipping_id ];
		break;
	}
}

if ( ! empty( $rate ) ) {
	?>

	<tr class="woocommerce-shipping-totals shipping">
		<th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
		<td data-title="<?php esc_html_e( 'Shipping', 'woocommerce' ); ?>" class="kco-shipping"><?php echo wc_cart_totals_shipping_method_label( $rate ); // WPCS: XSS ok. ?></td>
	</tr>

	<?php
} else {
	?>
		<tr class="woocommerce-shipping-totals shipping">
		<th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
		<td data-title="<?php esc_html_e( 'Shipping', 'woocommerce' ); ?>" class="kco-shipping"><?php echo wp_kses_post( apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', __( 'Shipping costs are calculated during checkout.', 'woocommerce' ) ) ); ?></td>
	</tr>
	<?php
}

<?php
/**
 * Klarna Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package klarna-checkout-for-woocommerce
 */

WC()->cart->calculate_fees();
WC()->cart->calculate_shipping();
WC()->cart->calculate_totals();

$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
?>

<form name="checkout" class="checkout woocommerce-checkout">
<div id="kco-wrapper">
		<?php if ( count( $available_gateways ) > 1 ) { ?>
		<p><a href="#" id="klarna-checkout-select-other">Select another payment method</a></p>
		<?php } ?>

		<div id="kco-order-review">
			<?php woocommerce_order_review(); ?>
			<?php kco_wc_show_order_notes(); ?>

			<?php
			$klarna_fields = array(
				'billing' => array(
					'billing_first_name',
					'billing_last_name',
					'billing_country',
					'billing_address_1',
					'billing_address_2',
					'billing_city',
					'billing_state',
					'billing_postcode',
					'billing_phone',
					'billing_email',
				),
				'shipping' => array(
					'shipping_first_name',
					'shipping_last_name',
					'shipping_country',
					'shipping_address_1',
					'shipping_address_2',
					'shipping_city',
					'shipping_state',
					'shipping_postcode',
				),
			);

			$checkout = WC()->checkout();
			$default_billing_fields = WC()->checkout()->get_checkout_fields( 'billing' );
			$default_shipping_fields = WC()->checkout()->get_checkout_fields( 'shipping' );
			$default_account_fields = WC()->checkout()->get_checkout_fields( 'account' );
			$default_order_fields = WC()->checkout()->get_checkout_fields( 'order' );

			foreach ( $klarna_fields['billing'] as $field ) {
				if ( array_key_exists( $field, $default_billing_fields ) ) {
					unset( $default_billing_fields[ $field ] );
				}

				unset( $default_billing_fields['billing_company' ] ); // B2C only for now
			}

			foreach ( $klarna_fields['shipping'] as $field ) {
				if ( array_key_exists( $field, $default_shipping_fields ) ) {
					unset( $default_shipping_fields[ $field ] );
				}

				unset( $default_shipping_fields['shipping_company' ] ); // B2C only for now
			}

			foreach ( $default_billing_fields as $key => $field ) {
				if ( isset( $field['country_field'], $default_billing_fields[ $field['country_field'] ] ) ) {
					$field['country'] = $checkout->get_value( $field['country_field'] );
				}
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
			?>

			<?php foreach ( $default_account_fields as $key => $field ) : ?>
				<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
			<?php endforeach; ?>

			<?php
			foreach ( $default_shipping_fields as $key => $field ) {
				if ( isset( $field['country_field'], $default_shipping_fields[ $field['country_field'] ] ) ) {
					$field['country'] = $checkout->get_value( $field['country_field'] );
				}
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
			?>

			<?php foreach ( $default_order_fields as $key => $field ) : ?>
				<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
			<?php endforeach; ?>
		</div>

		<div id="kco-iframe">
			<?php do_action( 'kco_wc_before_snippet' ); ?>
			<?php kco_wc_show_snippet(); ?>
			<?php do_action( 'kco_wc_after_snippet' ); ?>
		</div>
</div>
</form>

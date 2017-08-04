<?php $nonce = wp_create_nonce( 'woocommerce-process_checkout' ); ?>

<p>Please wait while we process your order.</p>

<?php
// Fetch Klarna order.
$klarna_order_id = WC()->session->get( 'klarna_order_id' );
$response        = KCO_WC()->api->request_post_get_order( $klarna_order_id );
$klarna_order    = json_decode( $response['body'] );

// Get needed info from it.
$checkout_data = array(
	'billing_first_name'  => $klarna_order->billing_address->given_name,
	'billing_last_name'   => $klarna_order->billing_address->family_name,
	'billing_company'     => '',
	'billing_country'     => $klarna_order->billing_address->country,
	'billing_address_1'   => $klarna_order->billing_address->street_address,
	'billing_address_2'   => $klarna_order->billing_address->street_address2,
	'billing_postcode'    => $klarna_order->billing_address->postal_code,
	'billing_city'        => $klarna_order->billing_address->city,
	'billing_state'       => $klarna_order->billing_address->region,
	'billing_phone'       => $klarna_order->billing_address->phone,
	'billing_email'       => $klarna_order->billing_address->email,
	'shipping_first_name' => $klarna_order->shipping_address->given_name,
	'shipping_last_name'  => $klarna_order->shipping_address->family_name,
	'shipping_company'    => '',
	'shipping_country'    => $klarna_order->shipping_address->country,
	'shipping_address_1'  => $klarna_order->shipping_address->street_address,
	'shipping_address_2'  => $klarna_order->shipping_address->street_address2,
	'shipping_postcode'   => $klarna_order->shipping_address->postal_code,
	'shipping_city'       => $klarna_order->shipping_address->city,
	'order_comments'      => $klarna_order->shipping_address->region,
	'shipping_method'     => 'flat_rate:1',
	'payment_method'      => 'klarna_checkout_for_woocommerce',
	'terms'               => 'on',
	'terms-field'         => '1',
	'_wpnonce'            => $nonce,
);

$query = http_build_query( $checkout_data, '', '&' );
?>

<script>
	var kco_slbd_test = function kco_slbd_test() {
		jQuery.ajax({
			type: 'POST',
			url: '/checkout/?wc-ajax=checkout',
			data: '<?php echo $query; ?>',
			dataType: 'json',
			success: function (result) {
				try {
					if ('success' === result.result) {
						if (-1 === result.redirect.indexOf('https://') || -1 === result.redirect.indexOf('http://')) {
							window.location = result.redirect;
						} else {
							window.location = decodeURI(result.redirect);
						}
					} else if ('failure' === result.result) {
						throw 'Result failure';
					} else {
						throw 'Invalid response';
					}
				} catch (err) {
					// Reload page
					if (true === result.reload) {
						window.location.reload();
						return;
					}

					// Trigger update in case we need a fresh nonce
					if (true === result.refresh) {
						jQuery(document.body).trigger('update_checkout');
					}

					// Add new errors
					if (result.messages) {
						console.log(result.messages);
						wc_checkout_form.submit_error(result.messages);
					} else {
						console.log(result);
						wc_checkout_form.submit_error('<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>');
					}
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				wc_checkout_form.submit_error('<div class="woocommerce-error">' + errorThrown + '</div>');
			}
		});
	}

	kco_slbd_test();
</script>

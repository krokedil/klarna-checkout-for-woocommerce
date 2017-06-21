<?php
echo '<a href="#" id="klarna-checkout-select-other">Select another payment method</a>';

$response = Klarna_Checkout_For_WooCommerce_API::create_checkout();



$something = wp_remote_get(
	'https://api-na.playground.klarna.com/checkout/v3/orders/4ea4a787-a8df-6031-8405-046c246fe151',
	array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'N100033:riz5Aith3bii%ch9' ),
			'Content-Type'  => 'application/json',
		),
	)
);

echo '<pre>';
print_r( json_decode( $something['body'] ) );
echo '</pre>';

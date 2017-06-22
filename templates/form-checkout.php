<?php
echo '<a href="#" id="klarna-checkout-select-other">Select another payment method</a>';

echo '<div style="overflow:hidden; padding-top:20px;">';

	echo '<div style="float:left; width:40%; padding-right: 20px; font-size: 0.9em">';
	woocommerce_order_review();
	echo '</div>';

	echo '<div style="float:right; width:60%; padding-left: 20px">';
	$response = Klarna_Checkout_For_WooCommerce_API::create_checkout();
	$decoded_response = json_decode( $response['body'] );
	$klarna_order_id = $decoded_response->order_id;

	$klarna_order = wp_remote_get(
		'https://api-na.playground.klarna.com/checkout/v3/orders/' . $klarna_order_id,
		array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'N100033:riz5Aith3bii%ch9' ),
				'Content-Type'  => 'application/json',
			),
		)
	);

	$decoded_klarna_order = json_decode( $klarna_order['body'] );
	echo $decoded_klarna_order->html_snippet;
	echo '</div>';

echo '</div>';
/* global wc_checkout_params */
jQuery( function( $ ) {

	var klarna_checkout_for_woocommerce = {
		selectedGateway: $('input[name="payment_method"]:checked').val(),
		init: function() {
			$('body').on( 'change', 'input[name="payment_method"]', this.update_to_klarna_checkout );
			$('#klarna-checkout-select-other').on('click', this.update_from_klarna_checkout);
		},
		update_to_klarna_checkout: function() {
			console.log('updating checkout', $('input[name="payment_method"]:checked').val());

			// Check if switching to of from Klarna Checkout.
			if ('klarna_checkout_for_woocommerce' === $('input[name="payment_method"]:checked').val() || 'klarna_checkout_for_woocommerce' === this.selectedGateway) {
				// $('body').trigger('update_checkout');
				this.selectedGateway = $('input[name="payment_method"]:checked').val();
				window.location.reload();
			}
		},
		update_from_klarna_checkout: function() {
			console.log('updating checkout');

			this.selectedGateway = false;
			window.location.reload();
		}

	};

	klarna_checkout_for_woocommerce.init();

});


/*
// Public
var kco_slbd_test = function kco_slbd_test() {
	jQuery.ajax({
		type: 'POST',
		url: '/checkout/?wc-ajax=checkout',
		data: 'billing_first_name=Testperson-se&billing_last_name=Approved&billing_company=C&billing_country=SE&billing_address_1=St%C3%A5rgatan+1&billing_address_2=&billing_postcode=12343&billing_city=Ankeborg&billing_state=&billing_phone=0123456789&billing_email=slobodan%40krokedil.se&shipping_first_name=Testperson-se&shipping_last_name=Approved&shipping_company=C&shipping_country=SE&shipping_address_1=St%C3%A5rgatan+1&shipping_address_2=&shipping_postcode=12343&shipping_city=Ankeborg&shipping_state=&order_comments=&shipping_method%5B0%5D=flat_rate%3A1&payment_method=bacs&terms=on&terms-field=1&_wpnonce=11fc137057',
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
					wc_checkout_form.submit_error(result.messages);
				} else {
					wc_checkout_form.submit_error('<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>');
				}
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			wc_checkout_form.submit_error('<div class="woocommerce-error">' + errorThrown + '</div>');
		}
	});
}
*/
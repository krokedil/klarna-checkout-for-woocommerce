/* global wc_checkout_params */
jQuery( function( $ ) {

	var klarna_checkout_for_woocommerce = {
		init: function() {
			$('body').on( 'change', 'input[name="payment_method"]', function() {
				klarna_checkout_for_woocommerce.update_to_klarna_checkout();
			});
			$('#klarna-checkout-select-other').on('click', this.update_from_klarna_checkout);
		},
		update_to_klarna_checkout: function() {
			console.log('updating checkout', $('input[name="payment_method"]:checked').val());

			// Check if switching to of from Klarna Checkout.
			if ('klarna_checkout_for_woocommerce' === $('input[name="payment_method"]:checked').val()) {
				// $('body').trigger('update_checkout');
				window.location.href = '/checkout/kco';
			}
		},
		update_from_klarna_checkout: function(e) {
			e.preventDefault();

			/*
			$.ajax({
				type: 'POST',
				url: '/checkout/?wc-ajax=kco_ajax_event',
				success: function (data) {}
			});
			*/

			window.location.href = '/checkout';
			// $('body').trigger('update_checkout');
		}

	};

	klarna_checkout_for_woocommerce.init();

});

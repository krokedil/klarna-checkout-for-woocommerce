/* global klarna_checkout_for_woocommerce_params */
jQuery(function($) {
	// Check if we have params.
	if ( typeof klarna_checkout_for_woocommerce_params === 'undefined' ) {
		return false;
	}

	var kco_wc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',

		// Order notes
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#klarna-checkout-select-other',

		documentReady: function() {
			if (kco_wc.paymentMethodEl.length > 0) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter(':checked').val();
			} else {
				kco_wc.paymentMethod = 'klarna_checkout_for_woocommerce';
			}
		},

		kcoSuspend: function () {
			window._klarnaCheckout(function (api) {
				api.suspend();
			});
		},

		kcoResume: function () {
			window._klarnaCheckout(function (api) {
				api.resume();
			});
		},

		updateCart: function () {
			kco_wc.kcoSuspend();
			$('body').trigger('update_checkout');

			$.ajax({
				type: 'POST',
				url: klarna_checkout_for_woocommerce_params.update_cart_url,
				data: {
					checkout: $('form.checkout').serialize(),
					nonce: klarna_checkout_for_woocommerce_params.update_cart_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					kco_wc.kcoResume();
				}
			});
		},

		updateShipping: function () {
			kco_wc.kcoSuspend();
			$('body').trigger('update_checkout');

			var shipping_methods = {};
			$( 'select.shipping_method, input[name^="shipping_method"][type="radio"]:checked, input[name^="shipping_method"][type="hidden"]' ).each( function() {
				shipping_methods[ $( this ).data( 'index' ) ] = $( this ).val();
			} );

			$.ajax({
				type: 'POST',
				url: klarna_checkout_for_woocommerce_params.update_shipping_url,
				data: {
					shipping: shipping_methods,
					nonce: klarna_checkout_for_woocommerce_params.update_shipping_nonce
				},
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					kco_wc.kcoResume();
				}
			});
		},

		updateOrderNotes: function() {
			if (kco_wc.orderNotesEl.val() !== kco_wc.orderNotesValue) {
				kco_wc.orderNotesValue = kco_wc.orderNotesEl.val();

				$.ajax({
					type: 'POST',
					url: klarna_checkout_for_woocommerce_params.update_order_notes_url,
					data: {
						order_notes: kco_wc.orderNotesValue,
						nonce: klarna_checkout_for_woocommerce_params.update_order_notes_nonce
					},
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						console.log('complete', data);
					}
				});
			}
		},

		refreshCheckoutFragment: function(e) {
			e.preventDefault();

			$(kco_wc.checkoutFormSelector).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					kco: false,
					nonce: klarna_checkout_for_woocommerce_params.refresh_checkout_fragment_nonce
				},
				url: klarna_checkout_for_woocommerce_params.refresh_checkout_fragment_url,
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					console.log(data.responseJSON);
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		refreshCheckoutFragmentKco: function() {
			console.log($(this).val());

			if ( 'klarna_checkout_for_woocommerce' === $(this).val() ) {
				$('.woocommerce-info').remove();

				$(kco_wc.checkoutFormSelector).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$.ajax({
					type: 'POST',
					data: {
						kco: true,
						nonce: klarna_checkout_for_woocommerce_params.refresh_checkout_fragment_nonce
					},
					dataType: 'json',
					url: klarna_checkout_for_woocommerce_params.checkout_url + '?wc-ajax=kco_wc_refresh_checkout_fragment',
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						console.log(data.responseJSON);
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
		},

		init: function () {
			$(document).ready(kco_wc.documentReady);
			// kco_wc.bodyEl.on('updated_checkout', kco_wc.documentReady);

			kco_wc.bodyEl.on('change', 'input.qty', kco_wc.updateCart);
			kco_wc.bodyEl.on('change', 'input.shipping_method', kco_wc.updateShipping);
			kco_wc.bodyEl.on('blur', kco_wc.orderNotesSelector, kco_wc.updateOrderNotes);
			kco_wc.bodyEl.on('change', 'input[name="payment_method"]', kco_wc.refreshCheckoutFragmentKco);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.refreshCheckoutFragment);

			/*
			window._klarnaCheckout(function(api) {
				api.on({
					'change': function(data) {},
					'shipping_address_change': function(data) {},
					'order_total_change': function(data) {},
					'shipping_option_change': function(data) {},
					'can_not_complete_order': function(data) {}
				});
			});
			*/
		}
	};

	kco_wc.init();
});

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
			console.log(klarna_checkout_for_woocommerce_params);

			if (kco_wc.paymentMethodEl.length > 0) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter(':checked').val();
			} else {
				kco_wc.paymentMethod = 'klarna_checkout_for_woocommerce';
			}

			kco_wc.confirmLoading();
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

		confirmLoading: function () {
			$('#kco-confirm-loading')
				.css('minHeight', '300px')
				.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
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

		// When "Change to another payment method" is clicked.
		changeFromKco: function(e) {
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
					nonce: klarna_checkout_for_woocommerce_params.change_payment_method_nonce
				},
				url: klarna_checkout_for_woocommerce_params.change_payment_method_url,
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					console.log(data.responseJSON);
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		// When payment method is changed to KCO in regular WC Checkout page.
		maybeChangeToKco: function() {
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
						nonce: klarna_checkout_for_woocommerce_params.change_payment_method_nonce
					},
					dataType: 'json',
					url: klarna_checkout_for_woocommerce_params.change_payment_method_url,
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
			kco_wc.bodyEl.on('change', 'input[name="payment_method"]', kco_wc.maybeChangeToKco);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.changeFromKco);

			if (typeof window._klarnaCheckout === 'function') {
				window._klarnaCheckout(function (api) {
					api.on({
						'change': function(data) {
							console.log('change', data);

							$('table.woocommerce-checkout-review-order-table').block({
								message: null,
								overlayCSS: {
									background: '#fff',
									opacity: 0.6
								}
							});
							kco_wc.kcoSuspend();

							$.ajax(
								{
									url: klarna_checkout_for_woocommerce_params.iframe_change_url,
									type: 'POST',
									dataType: 'json',
									data: {
										data: data,
										nonce: klarna_checkout_for_woocommerce_params.iframe_change_nonce
									},
									success: function (response) {
										console.log(response);
										$('table.woocommerce-checkout-review-order-table').replaceWith(response.data.html);
									},
									error: function (response) {
										console.log(response);
									},
									complete: function() {
										$('table.woocommerce-checkout-review-order-table').unblock();
										kco_wc.kcoResume();
									}
								}
							);
						},
						'shipping_address_change': function(data) {
							console.log('shipping_address_change', data);
						},
						'order_total_change': function(data) {
							console.log('order_total_change', data);
						},
						'shipping_option_change': function(data) {
							console.log('shipping_option_change', data);
						},
						'can_not_complete_order': function(data) {
							console.log('can_not_complete_order', data);
						}
					});
				});
			}
		}
	};

	kco_wc.init();
});

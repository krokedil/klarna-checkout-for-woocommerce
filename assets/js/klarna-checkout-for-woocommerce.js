/* global kco_params */
jQuery(function($) {
	// Check if we have params.
	if ( typeof kco_params === 'undefined' ) {
		return false;
	}

	var kco_wc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',

		// Order notes
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Order notes
		extraFieldsValues: {},
		extraFieldsSelectorText: 'div#kco-extra-fields input[type="text"], div#kco-extra-fields input[type="password"], div#kco-extra-fields textarea',
		extraFieldsSelectorNonText: 'div#kco-extra-fields select, div#kco-extra-fields input[type="radio"], div#kco-extra-fields input[type="checkbox"], div#kco-extra-fields input.checkout-date-picker',

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#klarna-checkout-select-other',

		documentReady: function() {
			kco_wc.log(kco_params);

			if (kco_wc.paymentMethodEl.length > 0) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter(':checked').val();
			} else {
				kco_wc.paymentMethod = 'kco';
			}

			kco_wc.confirmLoading();
		},

		kcoSuspend: function () {
			if (window._klarnaCheckout) {
				window._klarnaCheckout(function (api) {
					api.suspend();
				});
			}
		},

		kcoResume: function () {
			if (window._klarnaCheckout) {
				window._klarnaCheckout(function (api) {
					api.resume();
				});
			}
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

			$.ajax({
				type: 'POST',
				url: kco_params.update_cart_url,
				data: {
					checkout: $('form.checkout').serialize(),
					nonce: kco_params.update_cart_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					$('body').trigger('update_checkout');
					kco_wc.kcoResume();
				}
			});
		},

		updateExtraFields: function() {
			var elementName = $(this).attr('name');
			var updatedValue = $(this).val();

			kco_wc.log('value');
			kco_wc.log(updatedValue);
			kco_wc.log('name');
			kco_wc.log(elementName);
			kco_wc.log(typeof kco_wc.extraFieldsValues);
			kco_wc.log(kco_wc.extraFieldsValues);

			if (null === kco_wc.extraFieldsValues && '' === updatedValue) {
				return;
			}

			if (null !== kco_wc.extraFieldsValues && elementName in kco_wc.extraFieldsValues && updatedValue === kco_wc.extraFieldsValues) {
				return;
			}

			if (null === kco_wc.extraFieldsValues) {
				kco_wc.extraFieldsValues = {};
			}

			kco_wc.log('update');

			kco_wc.extraFieldsValues[elementName] = updatedValue;

			$.ajax({
				type: 'POST',
				url: kco_params.update_extra_fields_url,
				data: {
					extra_fields_values: kco_wc.extraFieldsValues,
					nonce: kco_params.update_extra_fields_nonce
				},
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					kco_wc.log('complete', data);
				}
			});
		},

		updateOrderNotes: function() {
			if (kco_wc.orderNotesEl.val() !== kco_wc.orderNotesValue) {
				kco_wc.orderNotesValue = kco_wc.orderNotesEl.val();

				$.ajax({
					type: 'POST',
					url: kco_params.update_order_notes_url,
					data: {
						order_notes: kco_wc.orderNotesValue,
						nonce: kco_params.update_order_notes_nonce
					},
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						kco_wc.log('complete', data);
					}
				});
			}
		},

		updateKlarnaOrder: function() {
			if ('kco' === kco_wc.paymentMethod) {
				$.ajax({
					type: 'POST',
					url: kco_params.update_klarna_order_url,
					data: {
						nonce: kco_params.update_klarna_order_nonce
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
					nonce: kco_params.change_payment_method_nonce
				},
				url: kco_params.change_payment_method_url,
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					kco_wc.log(data.responseJSON);
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		// When payment method is changed to KCO in regular WC Checkout page.
		maybeChangeToKco: function() {
			kco_wc.log($(this).val());

			if ( 'kco' === $(this).val() ) {
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
						nonce: kco_params.change_payment_method_nonce
					},
					dataType: 'json',
					url: kco_params.change_payment_method_url,
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						kco_wc.log(data.responseJSON);
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
		},

		checkoutError: function() {
			if ('kco' === kco_wc.paymentMethod) {
				$.ajax({
					type: 'POST',
					dataType: 'json',
					data: {
						kco: false,
						nonce: kco_params.checkout_error_nonce
					},
					url: kco_params.checkout_error_url,
					success: function (data) {
					},
					error: function (data) {
					},
					complete: function (data) {
						kco_wc.log(data.responseJSON);
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
		},

		log: function(message) {
			if (kco_params.logging) {
				console.log(message);
			}
		},

		init: function () {
			$(document).ready(kco_wc.documentReady);

			kco_wc.bodyEl.on('update_checkout', kco_wc.kcoSuspend);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.updateKlarnaOrder);
			kco_wc.bodyEl.on('checkout_error', kco_wc.checkoutError);

			kco_wc.bodyEl.on('change', 'input.qty', kco_wc.updateCart);
			kco_wc.bodyEl.on('blur', kco_wc.extraFieldsSelectorText, kco_wc.updateExtraFields);
			kco_wc.bodyEl.on('change', kco_wc.extraFieldsSelectorNonText, kco_wc.updateExtraFields);
			kco_wc.bodyEl.on('change', 'input[name="payment_method"]', kco_wc.maybeChangeToKco);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.changeFromKco);

			if (typeof window._klarnaCheckout === 'function') {
				window._klarnaCheckout(function (api) {
					api.on({
						'shipping_address_change': function(data) {
							kco_wc.log('shipping_address_change');
							kco_wc.log(data);

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
									url: kco_params.iframe_shipping_address_change_url,
									type: 'POST',
									dataType: 'json',
									data: {
										data: data,
										nonce: kco_params.iframe_shipping_address_change_nonce
									},
									success: function (response) {
										kco_wc.log(response);
										$('table.woocommerce-checkout-review-order-table').replaceWith(response.data.html);
									},
									error: function (response) {
										kco_wc.log(response);
									},
									complete: function() {
										$('table.woocommerce-checkout-review-order-table').unblock();
										kco_wc.kcoResume();
									}
								}
							);
						},
						'change': function(data) {
							kco_wc.log('change', data);
						},
						'order_total_change': function(data) {
							kco_wc.log('order_total_change', data);
						},
						'shipping_option_change': function(data) {
							kco_wc.log('shipping_option_change', data);
						},
						'can_not_complete_order': function(data) {
							kco_wc.log('can_not_complete_order', data);
						}
					});
				});
			}
		}
	};

	kco_wc.init();
});

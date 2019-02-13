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
		extraFieldsSelectorText: 'div#kco-extra-fields input[type="text"], div#kco-extra-fields input[type="password"], div#kco-extra-fields textarea, div#kco-extra-fields input[type="email"], div#kco-extra-fields input[type="tel"]',
		extraFieldsSelectorNonText: 'div#kco-extra-fields select, div#kco-extra-fields input[type="radio"], div#kco-extra-fields input[type="checkbox"], div#kco-extra-fields input.checkout-date-picker, input#terms input[type="checkbox"]',

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#klarna-checkout-select-other',

		// Form fields
		formFields: [],

		documentReady: function() {
			kco_wc.log(kco_params);
			kco_wc.setFormData();
			if (kco_wc.paymentMethodEl.length > 0) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter(':checked').val();
			} else {
				kco_wc.paymentMethod = 'kco';
			}

			kco_wc.confirmLoading();
			kco_wc.setFormFieldValuesFromTransiet();
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
			var field = $(this);

			var formFields = kco_wc.formFields;

			var elementName = field.attr('name');
			var newValue = field.val();

			$.each( formFields, function( index, value) {
				if( value.name === elementName ) {
					if( field.is(':checkbox') ) {
						// If is checkbox
						if( ! field.is(':checked') ) {
							newValue = '';
						}
					}
					if( field.is(':radio ') ) {
						// If is radio
						if( ! field.is(':checked') ) {
							newValue = '';
						}
					}
					if( field.prop('type') === 'select-one' ) {
						// If is select one
						newValue = field.find(":selected").val();
					}
					// Update value
					formFields[index].value = newValue;
				}
			} );
			kco_wc.formFields = formFields;
			kco_wc.saveFormData();
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
			if ( 'kco' === kco_wc.paymentMethod && kco_params.is_confirmation_page === 'no' ) {
				$('.woocommerce-checkout-review-order-table').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
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
						if (true === data.responseJSON.success) {
							kco_wc.kcoResume();
							$('.woocommerce-checkout-review-order-table').unblock();							
						} else {
							if( '' !== data.responseJSON.data.redirect_url ) {
								console.log('Cart do not need payment. Reloading checkout.');
								window.location.href = data.responseJSON.data.redirect_url;
							}
						}
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
			if ('kco' === kco_wc.paymentMethod && kco_params.is_confirmation_page === 'yes') {
				var error_message = $( ".woocommerce-NoticeGroup-checkout" ).text();
				$.ajax({
					type: 'POST',
					dataType: 'json',
					data: {
						kco: false,
						error_message: error_message,
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

		setFormData: function() {
			// Check if we have a form already and set that if we do. Prevents overwriting old data.
			if( ! $.isArray( kco_params.form ) ) {
				var form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
				var i;
				var newForm = [];
				for ( i = 0; i < form.length; i++ ) { 
					if ( form[i]['name'] !== '' ) {
						var name    = form[i]['name'];
						var field = $('*[name="' + name + '"]');
						var check = ( field.parents('p.form-row').hasClass('validate-required') ? true: false );
						// Only keep track of non standard WooCommerce checkout fields
						//if ($.inArray(name, kco_params.standard_woo_checkout_fields)=='-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 ) {
						if ($.inArray(name, kco_params.standard_woo_checkout_fields)=='-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 && name.indexOf( 'payment_method' ) < 0 ) {
							var required = false;
							var value = ( ! field.is(':checkbox') ) ? form[i].value : ( field.is(":checked") ) ? form[i].value : '';
							if ( check === true ) {
								if( form[i].name === 'terms' ) {
									value = ( $("input#terms:checked").length === 1 ) ? 1 : '';
								}
								required = true
							}
							// Check if we already have the name in the form to prevent errors.
							var rowExists = newForm.find( function( row ) { 
								if( row.name && row.name === name ) {
								return true;
							}
							return false;
							} );
							if( ! rowExists ) {
								newForm.push({
									name: form[i].name,
									value: value,
									required: required,
								});
							}
						}
					}
				}
				kco_wc.formFields = newForm;
				kco_wc.saveFormData();
			} else {
				kco_wc.formFields = kco_params.form;
				kco_wc.saveFormData();
			}
			console.table( kco_wc.formFields );
		},

		saveFormData: function() {
			$.ajax({
				type: 'POST',
				url: kco_params.save_form_data,
				data: {
					form: kco_wc.formFields,
					nonce: kco_params.save_form_data_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
				}
			});
		},

		setFormFieldValuesFromTransiet: function() {
			var form_data = kco_params.form;
			for ( i = 0; i < form_data.length; i++ ) {
				var field = $('*[name="' + form_data[i].name + '"]');
				var saved_value = form_data[i].value;
				// Check if field is a checkbox
				if( field.is(':checkbox') ) {
					if( saved_value !== '' ) {
						field.prop('checked', true);
					}
				} else if( field.is(':radio') ) {
					for ( x = 0; x < field.length; x++ ) {
						if( field[x].value === form_data[i].value ) {
							$(field[x]).prop('checked', true);
						}
					}
				} else {
					field.val( saved_value );
				}

			}
		},

		setFieldValues: function( data ) {
			// Billing fields
			$('#billing_email').val(data.customer_data.billing_email);
			$('#billing_postcode').val(data.customer_data.billing_postcode);
			$('#billing_state').val(data.customer_data.billing_state);
			$('#billing_country').val(data.customer_data.billing_country);

			// Shipping fields
			$('#shipping_postcode').val(data.customer_data.shipping_postcode);
			$('#shipping_state').val(data.customer_data.shipping_state);
			$('#shipping_country').val(data.customer_data.billing_country);

			// Trigger changes
			$('#billing_email').change();
			$('#billing_email').blur();
		},

		init: function () {
			$(document).ready(kco_wc.documentReady);

			kco_wc.bodyEl.on('update_checkout', kco_wc.kcoSuspend);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.updateKlarnaOrder);
			kco_wc.bodyEl.on('checkout_error', kco_wc.checkoutError);
			kco_wc.bodyEl.on('change', 'input.qty', kco_wc.updateCart);
			//kco_wc.bodyEl.on('blur', kco_wc.extraFieldsSelectorText, kco_wc.setFormData);
			//kco_wc.bodyEl.on('change', kco_wc.extraFieldsSelectorNonText, kco_wc.setFormData);
			kco_wc.bodyEl.on('blur', kco_wc.extraFieldsSelectorText, kco_wc.updateExtraFields);
			kco_wc.bodyEl.on('change', kco_wc.extraFieldsSelectorNonText, kco_wc.updateExtraFields);
			kco_wc.bodyEl.on('change', 'input[name="payment_method"]', kco_wc.maybeChangeToKco);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.changeFromKco);
			kco_wc.bodyEl.on('click', 'input#terms', kco_wc.setFormData)
			kco_wc.bodyEl.on('click', 'input#terms', kco_wc.updateExtraFields)

			if (typeof window._klarnaCheckout === 'function') {
				window._klarnaCheckout(function (api) {
					api.on({
						'shipping_address_change': function(data) {
							kco_wc.log('shipping_address_change');
							kco_wc.log(data);

							$('.woocommerce-checkout-review-order-table').block({
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
										kco_wc.setFieldValues( response.data );
										$('body').trigger('update_checkout');
									},
									error: function (response) {
										kco_wc.log(response);
									},
									complete: function() {
										$('.woocommerce-checkout-review-order-table').unblock();
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
	$('body').on('blur', kco_wc.setFormData );
	$(document).on("keypress", "#kco-order-review .qty", function(event) {
		if (event.keyCode == 13) {
			event.preventDefault();
		}
	});	
});

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
		shippingUpdated: false,
		blocked: false,

		// Email exist
		emailExists: kco_params.email_exists,

		documentReady: function() {
			kco_wc.setFormFieldValues();
			kco_wc.log(kco_params);
			kco_wc.checkFormData();
			if (kco_wc.paymentMethodEl.length > 0) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter(':checked').val();
			} else {
				kco_wc.paymentMethod = 'kco';
			}

			kco_wc.confirmLoading();
		},

		kcoSuspend: function ( autoResumeBool ) {
			if (window._klarnaCheckout) {
				window._klarnaCheckout(function (api) {
					api.suspend({ 
						autoResume: {
						  enabled: autoResumeBool
						}
					  });
				});
			}
		},

		kcoResume: function () {
			if (window._klarnaCheckout) {
				window._klarnaCheckout(function (api) {
					if ( false === kco_wc.blocked ) {
						api.resume();
					}
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
			kco_wc.kcoSuspend( true );
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
				kco_wc.kcoSuspend();
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

		// Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		maybeDisplayShippingPrice: function() {
			if ( 'kco' === kco_wc.paymentMethod && kco_params.shipping_methods_in_iframe === 'yes' && kco_params.is_confirmation_page === 'no' ) {
				if( jQuery("#shipping_method input[type='radio']").length ) {
					// Multiple shipping options available.
					$("#shipping_method input[type='radio']:checked").each(function() {
						var idVal = $(this).attr("id");
						var shippingPrice = $("label[for='"+idVal+"']").text();
						$(".woocommerce-shipping-totals td").html(shippingPrice);
					});
				} else {
					// Only one shipping option available.
					var idVal = $("#shipping_method input[name='shipping_method[0]']").attr("id");
					var shippingPrice = $("label[for='"+idVal+"']").text();
					$(".woocommerce-shipping-totals td").html(shippingPrice);
				}
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
			if ( 'yes' !== kco_params.is_confirmation_page ) {
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

		checkFormData: function() {
			var form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
				var requiredFields = [];
				var fieldData = {};
				var requiredFieldsDirty = $('form[name="checkout"]').find( '.validate-required:visible' );
				// Get all form fields.
				for ( i = 0; i < form.length; i++ ) { 
					// Check if the form has a name set.
					if ( form[i]['name'] !== '' ) {
						var name    = form[i]['name'];
						var field = $('*[name="' + name + '"]');
						// Only keep track of non standard WooCommerce checkout fields
						if ($.inArray(name, kco_params.standard_woo_checkout_fields) == '-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 && name.indexOf( 'payment_method' ) < 0 ) {
							// Only keep track of required fields for validation.
							/*if ( required === true ) {
								requiredFields.push(name);
							}*/
							// Get the value from the field.
							var value = '';
							if( field.is(':checkbox') ) {
								if( field.is(':checked') ) {
									value = form[i].value;
								}
							} else if( field.is(':radio') ) {
								if( field.is(':checked') ) {
									value = $( 'input[name="' + name + '"]:checked').val();
								}
							} else {
								value = form[i].value
							}
							// Set field data with values.
							fieldData[name] = value;
						}
					}
				}
				for ( i = 0; i < requiredFieldsDirty.length; i++ ) {
					var requiredField = requiredFieldsDirty[i]
					var input = $(requiredField).find(':input');
					if ( input.length > 0 ) {
						var name = input.attr('name');
						if ($.inArray(name, kco_params.standard_woo_checkout_fields) == '-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 && name.indexOf( 'payment_method' ) < 0 ) {
							requiredFields.push( name );
						}
					}
				}
				sessionStorage.setItem( 'KCORequiredFields', JSON.stringify( requiredFields ) );
				sessionStorage.setItem( 'KCOFieldData', JSON.stringify( fieldData ) );
				if ( true === kco_wc.shippingUpdated ) {
					kco_wc.validateRequiredFields();
				}
		},

		validateRequiredFields: function() {
			// Get data from session storage.
			var requiredFields = JSON.parse( sessionStorage.getItem( 'KCORequiredFields' ) );
			var fieldData = JSON.parse( sessionStorage.getItem( 'KCOFieldData' ) );
			// Check if all data is set for required fields.
			var allValid = true;
			if ( requiredFields !== null ) {
				for( i = 0; i < requiredFields.length; i++ ) {
					fieldName = requiredFields[i];
					if ( '' === fieldData[fieldName] ) {
						allValid = false;
					}
				}
			}
			kco_wc.maybeSuspendIframe( allValid );
		},
		
		maybeSuspendIframe: function( allValid ) {
			if ( true === allValid ) {
				kco_wc.blocked = false;
				$('#kco-required-fields-notice').remove();
				kco_wc.kcoResume();
			} else 	if( ! $('#kco-required-fields-notice').length ) { // Only if we dont have an error message already.
				kco_wc.blocked = true;
				$('form.checkout').prepend( '<div id="kco-required-fields-notice" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' +  kco_params.required_fields_text + '</li></ul></div>' );
				var etop = $('form.checkout').offset().top;
				$('html, body').animate({
					scrollTop: etop
					}, 1000);
				kco_wc.kcoSuspend( false );
			}
		},

		maybePrintValidationMessage: function() {
			if ( true === kco_wc.blocked && ! $('#kco-required-fields-notice').length ) {
				$('form.checkout').prepend( '<div id="kco-required-fields-notice" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' +  kco_params.required_fields_text + '</li></ul></div>' );
				var etop = $('form.checkout').offset().top;
				$('html, body').animate({
					scrollTop: etop
					}, 1000);
			}
		},

		maybePrintLoginMessage: function() {
			if ( true === $('input[name=createaccount]').prop('checked') && 'yes' === kco_wc.emailExists ) {
				$('form.checkout').prepend( '<div id="kco-login-notice" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' +  kco_params.must_login_message + '</li></ul></div>' );
				var etop = $('form.checkout').offset().top;
				$('html, body').animate({
					scrollTop: etop
					}, 1000);
			} else {
				$('#kco-login-notice').remove();
			}
		},

		setFormFieldValues: function() {
			var form_data = JSON.parse( sessionStorage.getItem( 'KCOFieldData' ) );
			if( form_data !== null ) {
				$.each( form_data, function( name, value ) {
					var field = $('*[name="' + name + '"]');
					var saved_value = value;
					// Check if field is a checkbox
					if( field.is(':checkbox') ) {
						if( saved_value !== '' ) {
							field.prop('checked', true);
						}
					} else if( field.is(':radio') ) {
						for ( x = 0; x < field.length; x++ ) {
							if( field[x].value === value ) {
								$(field[x]).prop('checked', true);
							}
						}
					} else {
						field.val( saved_value );
					}
				});
			}
		},

		// Set Woo address field values when shipping address change event has triggered.
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

			kco_wc.bodyEl.on('update_checkout', kco_wc.kcoSuspend( true ) );
			kco_wc.bodyEl.on('updated_checkout', kco_wc.updateKlarnaOrder);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.maybeDisplayShippingPrice);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.maybePrintValidationMessage);
			kco_wc.bodyEl.on('checkout_error', kco_wc.checkoutError);
			kco_wc.bodyEl.on('change', 'input.qty', kco_wc.updateCart);
			kco_wc.bodyEl.on('change', 'input[name="payment_method"]', kco_wc.maybeChangeToKco);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.changeFromKco);
			kco_wc.bodyEl.on('change', 'input[name="createaccount"]', kco_wc.maybePrintLoginMessage);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.maybePrintLoginMessage);

			// Extra checkout fields.
			kco_wc.bodyEl.on('blur', kco_wc.extraFieldsSelectorText, kco_wc.checkFormData);
			kco_wc.bodyEl.on('change', kco_wc.extraFieldsSelectorNonText, kco_wc.checkFormData);
			kco_wc.bodyEl.on('click', 'input#terms', kco_wc.checkFormData);

			if (typeof window._klarnaCheckout === 'function') {
				window._klarnaCheckout(function (api) {
					api.on({
						'shipping_address_change': function(data) {
							kco_wc.log('shipping_address_change');
							kco_wc.log(data);
							var form_data = JSON.parse( sessionStorage.getItem( 'KCOFieldData' ) );
							$('.woocommerce-checkout-review-order-table').block({
								message: null,
								overlayCSS: {
									background: '#fff',
									opacity: 0.6
								}
							});
							kco_wc.kcoSuspend( true );

							$.ajax(
								{
									url: kco_params.iframe_shipping_address_change_url,
									type: 'POST',
									dataType: 'json',
									data: {
										data: data,
										createaccount: form_data.createaccount,
										nonce: kco_params.iframe_shipping_address_change_nonce
									},
									success: function (response) {
										kco_wc.log(response);
										// Set emailExists variable. Used if customers clicks the create account checkbox.
										kco_wc.emailExists = response.data.email_exists;

										if( 'yes' == response.data.must_login ) {
											// Customer might need to login. Inform customer and freeze KCO checkout.
											kco_wc.kcoSuspend( false );
											var $form = $( 'form.checkout' );
											$form.prepend( '<div id="kco-login-notice" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' + response.data.must_login_message + '</li></ul></div>' );
											
											var etop = $('form.checkout').offset().top;
											$('html, body').animate({
												scrollTop: etop
											  }, 1000);
										} else {
											// All good release checkout and trigger update_checkout event
											kco_wc.kcoResume();
											kco_wc.validateRequiredFields();
											kco_wc.setFieldValues( response.data );
											$('body').trigger('update_checkout');	
										}
									},
									error: function (response) {
										kco_wc.log(response);
									},
									complete: function(response) {
										$('.woocommerce-checkout-review-order-table').unblock();
										kco_wc.shippingUpdated = true;
										kco_wc.bodyEl.trigger( 'kco_shipping_address_changed', response );
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
							kco_wc.log( data );
							$('.woocommerce-checkout-review-order-table').block({
								message: null,
								overlayCSS: {
									background: '#fff',
									opacity: 0.6
								}
							});
							kco_wc.kcoSuspend( true );
							$.ajax(
								{
									url: kco_params.update_shipping_url,
									type: 'POST',
									dataType: 'json',
									data: {
										data: data,
										nonce: kco_params.update_shipping_nonce
									},
									success: function (response) {
										kco_wc.log(response);
										$('body').trigger('update_checkout');
									},
									error: function (response) {
										kco_wc.log(response);
									},
									complete: function(response) {
										$('#shipping_method #' + response.responseJSON.data.shipping_option_name).prop('checked', true);
										$('body').trigger('kco_shipping_option_changed', [ data ] );
										$('.woocommerce-checkout-review-order-table').unblock();
										kco_wc.kcoResume();
									}
								}
							);
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
	$('body').on('blur', kco_wc.checkFormData );
	$(document).on("keypress", "#kco-order-review .qty", function(event) {
		if (event.keyCode == 13) {
			event.preventDefault();
		}
	});
});

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
			kco_wc.log(kco_params);
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

		log: function(message) {
			if (kco_params.logging) {
				console.log(message);
			}
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

		getKlarnaOrder: function() {
			console.log( 'get order' );
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
				url: kco_params.get_klarna_order_url,
				data: {
					nonce: kco_params.get_klarna_order_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					kco_wc.setCustomerData( data.responseJSON.data );
					$('form.checkout').submit();
				}
			});
		},

		setCustomerData: function ( data ) {
			// Billing fields.
			$('#billing_first_name').val( data.billing_address.given_name );
			$('#billing_last_name').val( data.billing_address.family_name );
			$('#billing_address_1').val( data.billing_address.street_address );
			$('#billing_city').val( data.billing_address.city );
			$('#billing_postcode').val( data.billing_address.postal_code );
			$('#billing_phone').val( data.billing_address.phone )
			$('#billing_email').val( data.billing_address.email );

			// Shipping fields.
			$('#shipping_first_name').val( data.shipping_address.given_name );
			$('#shipping_last_name').val( data.shipping_address.family_name );
			$('#shipping_address_1').val( data.shipping_address.street_address );
			$('#shipping_city').val( data.shipping_address.city );
			$('#shipping_postcode').val( data.shipping_address.postal_code );
		},

		init: function () {
			$(document).ready(kco_wc.documentReady);

			kco_wc.bodyEl.on('update_checkout', kco_wc.kcoSuspend( true ) );
			kco_wc.bodyEl.on('updated_checkout', kco_wc.updateKlarnaOrder);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.maybeDisplayShippingPrice);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.maybePrintValidationMessage);
			//kco_wc.bodyEl.on('checkout_error', kco_wc.checkoutError);
			kco_wc.bodyEl.on('change', 'input.qty', kco_wc.updateCart);
			kco_wc.bodyEl.on('change', 'input[name="payment_method"]', kco_wc.maybeChangeToKco);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.changeFromKco);
			kco_wc.bodyEl.on('change', 'input[name="createaccount"]', kco_wc.maybePrintLoginMessage);
			kco_wc.bodyEl.on('updated_checkout', kco_wc.maybePrintLoginMessage);

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
						},
						'validation_callback': function(data, callback) {
							console.log( 'In validation event' );
							kco_wc.getKlarnaOrder();
							callback({ shouldProceed: false });
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

/* global kco_params */
jQuery( function( $ ) {

	// Check if we have params.
	if ( 'undefined' === typeof kco_params ) {
		return false;
	}
	var kco_wc = {
		bodyEl: $( 'body' ),
		checkoutFormSelector: $( 'form.checkout' ),

		// Payment method.
		paymentMethodEl: $( 'input[name="payment_method"]' ),
		paymentMethod: '',
		selectAnotherSelector: '#klarna-checkout-select-other',

		// Form fields.
		shippingUpdated: false,
		validation: false,

		preventPaymentMethodChange: false,

		timeout: null,
		interval: null,

		// True or false if we need to update the Klarna order. Set to false on initial page load.
		klarnaUpdateNeeded: false,
		shippingEmailExists: false,
		shippingPhoneExists: false,

		/**
		 * Triggers on document ready.
		 */
		documentReady: function() {
			kco_wc.log( kco_params );
			if ( 0 < kco_wc.paymentMethodEl.length ) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter( ':checked' ).val();
			} else {
				kco_wc.paymentMethod = 'kco';
			}

			if( 'kco' ===  kco_wc.paymentMethod ){
				$( '#ship-to-different-address-checkbox' ).prop( 'checked', true);
			}

			if( ! kco_params.pay_for_order ) {
				kco_wc.moveExtraCheckoutFields();
				kco_wc.updateShipping( false );
			}

			// Handle events when the Klarna modal is closed when the purchase is not complete.
			// Klarna does not provide an event listener for when the modal is closed.
			const observer = new MutationObserver(function (mutations) {

				mutations.forEach(function (mutation) {
					if ('attributes' === mutation.type && 'class' === mutation.attributeName) {
						const modalClassName = 'klarna-checkout-fso-open';

						if (!$('html').hasClass(modalClassName)) {
							// Wait for the Klarna modal to disappear before scrolling up to show error notices.
							const noticeClassName = kco_params.pay_for_order ? 'div.woocommerce-notices-wrapper' : 'form.checkout';
							$('html, body').animate({
								scrollTop: ($(noticeClassName).offset().top - 100)
							}, 1000);

							// Unlock the order review table and checkout form.
							kco_wc.unblock();

						}
					}
				});
			});

			observer.observe(document.querySelector('html'), { attributes: true, attributeFilter: ['class'] });
		},

		/**
		 * Unblock the checkout form and order review.
		 */
		unblock: function () {
			kco_wc.checkoutFormSelector.removeClass( 'processing' );
			$( '.woocommerce-checkout-review-order-table' ).unblock();
			$( kco_wc.checkoutFormSelector ).unblock();
		},

		/**
		 * Resumes the Klarna Iframe
		 * @param {boolean} autoResumeBool
		 */
		kcoSuspend: function (autoResumeBool) {
			if ( window._klarnaCheckout && ! kco_wc.validation) {
				window._klarnaCheckout( function( api ) {
					api.suspend({
						autoResume: {
						  enabled: autoResumeBool
						}
					});
				});
			}
		},

		/**
		 * Resumes the KCO Iframe
		 */
		kcoResume: function() {
			if ( window._klarnaCheckout && ! kco_wc.validation) {
				window._klarnaCheckout( function( api ) {
					api.resume();
				});
			}
		},

		/**
		 * When the customer changes from KCO to other payment methods.
		 * @param {Event} e
		 */
		changeFromKco: function( e ) {
			e.preventDefault();

			$( kco_wc.checkoutFormSelector ).block({
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
				success: function( data ) {},
				error: function( data ) {},
				complete: function( data ) {
					kco_wc.log( data.responseJSON );
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		/**
		 * When the customer changes to KCO from other payment methods.
		 */
		maybeChangeToKco: function() {
			if ( ! kco_wc.preventPaymentMethodChange ) {
			kco_wc.log( $( this ).val() );

			if ( 'kco' === $( this ).val() ) {
				$( '.woocommerce-info' ).remove();

				$( kco_wc.checkoutFormSelector ).block({
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
					success: function( data ) {},
					error: function( data ) {},
					complete: function( data ) {
						kco_wc.log( data.responseJSON );
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
		}
		},

		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function() {

			// Move order comments.
			$( '.woocommerce-additional-fields' ).appendTo( '#kco-extra-checkout-fields' );
			var form = $( 'form[name="checkout"] input, form[name="checkout"] select, textarea' );
			var checkout_add_ons_moved = false;
			for ( i = 0; i < form.length; i++ ) {
				var name = form[i].name.replace('[]', '\\[\\]'); // Escape any empty "array" keys to prevent errors.
				// Check if field is inside the order review.
				if( $( 'table.woocommerce-checkout-review-order-table' ).find( form[i] ).length ) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, kco_params.standard_woo_checkout_fields ) ) {
					// This is not a standard Woo field, move to our div.
					if ( 'wc_checkout_add_ons' === $( 'p#' + name + '_field' ).parent().attr('id') ) { // Check if this is an add on field.
						if( ! checkout_add_ons_moved ) {
							checkout_add_ons_moved = true;
							$( 'div#wc_checkout_add_ons' ).appendTo( '#kco-extra-checkout-fields' );
						}
					} else if ( 0 < $( 'p#' + name + '_field' ).length ) {
						if (name === 'shipping_phone') {
							kco_wc.shippingPhoneExists = true;
						}
						if (name === 'shipping_email') {
							kco_wc.shippingEmailExists =  true;
						}
						$( 'p#' + name + '_field' ).appendTo( '#kco-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#kco-extra-checkout-fields' );
					}
				}
			}
		},

		/**
		 * Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		 */
		maybeDisplayShippingPrice: function() {
			// Check if we already have set the price. If we have, return.
			if( $('.kco-shipping').length ) {
				return;
			}

			if ('kco' === kco_wc.paymentMethod && 'yes' === kco_params.shipping_methods_in_iframe && 'no' === kco_params.is_confirmation_page) {
				
				if ( $('#shipping_method input[type="radio"]').length > 1 ) {
					// Multiple shipping options available.
					$( '#shipping_method input[type="radio"]:checked' ).each( function() {
						var idVal = $( this ).attr( 'id' );
						var shippingPrice = $( 'label[for="' + idVal + '"]' ).text();
						$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
						$( '.woocommerce-shipping-totals td' ).addClass( 'kco-shipping' );
					});

				} else if ( $('#shipping_method input[type="hidden"]').length === 1) {
					// Only one shipping option available.
					var idVal = $( '#shipping_method input[name="shipping_method[0]"]' ).attr( 'id' );
					var shippingPrice = $( 'label[for="' + idVal + '"]' ).text();
					$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
					$('.woocommerce-shipping-totals td').addClass('kco-shipping');
					
				} else {
					// No shipping method is available.
					$('.woocommerce-shipping-totals td').html(kco_params.no_shipping_message);
				}
			}
		},

		/**
		 * Updates the cart in case of a change in product quantity.
		 */
		updateCart: function() {
			kco_wc.kcoSuspend( true );
			$.ajax({
				type: 'POST',
				url: kco_params.update_cart_url,
				data: {
					checkout: $( 'form.checkout' ).serialize(),
					nonce: kco_params.update_cart_nonce
				},
				dataType: 'json',
				success: function( data ) {
				},
				error: function( data ) {
				},
				complete: function( data ) {
					$( 'body' ).trigger( 'update_checkout' );
					kco_wc.kcoResume();
				}
			});
		},

		/**
		 * Gets the Klarna order and starts the order submission
		 */
		getKlarnaOrder: function() {
			console.log( 'getKlarnaOrder' );
			kco_wc.preventPaymentMethodChange = true;
			$( '.woocommerce-checkout-review-order-table' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			var ajax = $.ajax({
				type: 'POST',
				url: kco_params.get_klarna_order_url,
				data: {
					nonce: kco_params.get_klarna_order_nonce
				},
				dataType: 'json',
				success: function( data ) {
					kco_wc.setCustomerData( data.data );

					// Check Terms checkbox, if it exists.
					if ( 0 < $( 'form.checkout #terms' ).length ) {
						$( 'form.checkout #terms' ).prop( 'checked', true );
					}
					console.log( 'success' );
				},
				error: function( data ) {
					console.log( 'error' );
					window.location.reload();
				},
				complete: function( data ) {
				}
			});

			return ajax;
		},

		/**
		 * Sets the customer data.
		 * @param {array} data
		 */
		setCustomerData: function( data ) {
			kco_wc.log('setCustomerData', data );

			if (typeof data !== 'object' || data === null) {
				return;
			}

			if ( 'billing_address' in data && data.billing_address !== null ) {
				// Billing fields.
				$( '#billing_first_name' ).val( ( ( 'given_name' in data.billing_address ) ? data.billing_address.given_name : '' ) );
				$( '#billing_last_name' ).val( ( ( 'family_name' in data.billing_address ) ? data.billing_address.family_name : '' ) );
				$( '#billing_company' ).val( ( ( 'organization_name' in data.billing_address ) ? data.billing_address.organization_name : '' ) );
				$( '#billing_address_1' ).val( ( ( 'street_address' in data.billing_address ) ? data.billing_address.street_address : '' ) );
				$( '#billing_address_2' ).val( ( ( 'street_address2' in data.billing_address ) ? data.billing_address.street_address2 : '' ) );
				$( '#billing_city' ).val( ( ( 'city' in data.billing_address ) ? data.billing_address.city : '' ) );
				$( '#billing_postcode' ).val( ( ( 'postal_code' in data.billing_address ) ? data.billing_address.postal_code : '' ) );
				$( '#billing_phone' ).val( ( ( 'phone' in data.billing_address ) ? data.billing_address.phone : '' ) );
				$( '#billing_email' ).val( ( ( 'email' in data.billing_address ) ? data.billing_address.email : '' ) );
				$( '#billing_country' ).val( ( ( 'country' in data.billing_address ) ? data.billing_address.country.toUpperCase() : '' ) );
				$( '#billing_state' ).val( ( ( 'region' in data.billing_address ) ? data.billing_address.region : '' ) );
				// Trigger changes
				$('#billing_email').change();
				$('#billing_email').blur();
			}

			if ( 'shipping_address' in data && data.shipping_address !== null ) {
				$( '#ship-to-different-address-checkbox' ).prop( 'checked', true);

				// Shipping fields.
				$( '#shipping_first_name' ).val( ( ( 'given_name' in data.shipping_address ) ? data.shipping_address.given_name : '' ) );
				$( '#shipping_last_name' ).val( ( ( 'family_name' in data.shipping_address ) ? data.shipping_address.family_name : '' ) );
				$( '#shipping_company' ).val( ( ( 'organization_name' in data.shipping_address ) ? data.shipping_address.organization_name : '' ) );
				$( '#shipping_address_1' ).val( ( ( 'street_address' in data.shipping_address ) ? data.shipping_address.street_address : '' ) );
				$( '#shipping_address_2' ).val( ( ( 'street_address2' in data.shipping_address ) ? data.shipping_address.street_address2 : '' ) );
				$( '#shipping_city' ).val( ( ( 'city' in data.shipping_address ) ? data.shipping_address.city : '' ) );
				$( '#shipping_postcode' ).val( ( ( 'postal_code' in data.shipping_address ) ? data.shipping_address.postal_code : '' ) );
				$( '#shipping_country' ).val( ( ( 'country' in data.shipping_address ) ? data.shipping_address.country.toUpperCase() : '' ) );
				$( '#shipping_state' ).val( ( ( 'region' in data.shipping_address ) ? data.shipping_address.region : '' ) );

				// extra shipping fields (email, phone).
				if (kco_wc.shippingEmailExists === true && $('#shipping_email')) {
					$( '#shipping_email' ).val( ( ( 'email' in data.shipping_address ) ? data.shipping_address.email : '' ) );
				}
				if (kco_wc.shippingPhoneExists === true && $('#shipping_phone')) {
					$( '#shipping_phone' ).val( ( ( 'phone' in data.shipping_address ) ? data.shipping_address.phone : '' ) );
				}
			}
		},

		/**
		 * Checks the URL for the hashtag.
		 * @param {function} callback
		 */
		checkUrl: function( callback ) {
			if ( window.location.hash ) {
				var currentHash = window.location.hash;
				if ( -1 < currentHash.indexOf( '#klarna-success' ) ) {
					kco_wc.logToFile( 'klarna-success hashtag detected in URL.' );
					callback({ should_proceed: true });
					// Clear the interval.
					clearInterval(kco_wc.interval);
					// Remove the timeout.
					clearTimeout( kco_wc.timeout );
					// Remove the processing class from the form.
					kco_wc.unblock();
				}
			}
		},

		/**
		 * Fails the order with Klarna on a checkout error and timeout.
		 * @param {function} callback
		 * @param {string} event
		 */
		failOrder: function( event, error_message, callback ) {
			callback({ should_proceed: false });
			kco_wc.validation = false;
			var className = kco_params.pay_for_order ? 'div.woocommerce-notices-wrapper' : 'form.checkout';
			// Update the checkout and renable the form.
			$( 'body' ).trigger( 'update_checkout' );

			// Print error messages, and trigger checkout_error, and scroll to notices.
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			$( className ).prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
			$( className ).removeClass( 'processing' ).unblock();
			$( className ).find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			$( document.body ).trigger( 'checkout_error' , [ error_message ] );
		},

		/**
		 * Logs the message to the klarna checkout log in WooCommerce.
		 * @param {string} message
		 */
		logToFile: function( message ) {
			$.ajax(
				{
					url: kco_params.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message: message,
						nonce: kco_params.log_to_file_nonce
					}
				}
			);
		},

		/**
		 * Logs messages to the console.
		 * @param {string} message
		 */
		log: function( message ) {
			if ( kco_params.logging ) {
				console.log( message );
			}
		},

		updateShipping: function( data ) {
			kco_wc.kcoSuspend( true );
			$('#kco_shipping_data').val(JSON.stringify(data));
			$( 'body' ).trigger( 'kco_shipping_option_changed', [ data ]);
			$( 'body' ).trigger( 'update_checkout' );
		},

		convertCountry: function( country ) {
			return Object.keys(kco_params.countries).find(key => kco_params.countries[key] === country);
		},

  placeKlarnaOrder: function(callback) {
			kco_wc.getKlarnaOrder().done( function(response) {
				if(response.success ) {
					$( '.woocommerce-checkout-review-order-table' ).block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					$.ajax({
						type: 'POST',
						url: kco_params.submit_order,
						data: $('form.checkout').serialize(),
						dataType: 'json',
						success: function( data ) {
							try {
								if ( 'success' === data.result ) {
									kco_wc.logToFile( 'Successfully placed order. Sending "should_proceed: true" to Klarna' );
									callback({ should_proceed: true });
								} else {
									throw 'Result failed';
								}
							} catch ( err ) {
								if ( data.messages )  {
									kco_wc.logToFile( 'Checkout error | ' + data.messages );
									kco_wc.failOrder( 'submission', data.messages, callback );
								} else {
									kco_wc.logToFile( 'Checkout error | No message' );
									kco_wc.failOrder( 'submission', '<div class="woocommerce-error">Checkout error</div>', callback );
								}
							}
						},
						error: function( data ) {
							try {
								kco_wc.logToFile( 'AJAX error | ' + JSON.stringify(data) );
							} catch( e ) {
								kco_wc.logToFile( 'AJAX error | Failed to parse error message.' );
							}
							kco_wc.failOrder( 'ajax-error', '<div class="woocommerce-error">Internal Server Error</div>', callback )
						}
					});
				} else {
					kco_wc.failOrder( 'get_order', '<div class="woocommerce-error">' + 'Failed to get the order from Klarna.' + '</div>', callback );
				}
				kco_wc.validation = false;

			});
		},

		/**
		 * Initiates the script.
		 */
		init: function () {
			/* If this is order received page, abort ASAP to render the snippet faster. */
			if ('yes' === kco_params.is_order_received_page) {
				return;
			}

			$( document ).ready( kco_wc.documentReady );
			kco_wc.bodyEl.on( 'update_checkout', function() {  kco_wc.kcoSuspend( true ) } );
			kco_wc.bodyEl.on( 'updated_checkout', kco_wc.kcoResume );
			kco_wc.bodyEl.on( 'updated_checkout', kco_wc.maybeDisplayShippingPrice );
			kco_wc.bodyEl.on( 'change', 'input.qty', kco_wc.updateCart );
			kco_wc.bodyEl.on( 'change', 'input[name="payment_method"]', kco_wc.maybeChangeToKco );
			kco_wc.bodyEl.on( 'click', kco_wc.selectAnotherSelector, kco_wc.changeFromKco );

			if ( 'function' === typeof window._klarnaCheckout ) {
				window._klarnaCheckout( function( api ) {
					api.on({
						'shipping_address_change': function( data ) {
							kco_wc.log( 'shipping_address_change' );
							kco_wc.log( data );

							var country = kco_wc.convertCountry( data.country.toUpperCase() );

							// Check if shipping address is enabled.
							if( $( '#shipping_first_name' ).length > 0 ) {
								$( '#ship-to-different-address-checkbox' ).prop( 'checked', true);
								$( '#ship-to-different-address-checkbox' ).change();
								$( '#ship-to-different-address-checkbox' ).blur();
								$( '#shipping_first_name' ).val( ( ( 'given_name' in data ) ? data.given_name : '' ) );
								$( '#shipping_last_name' ).val( ( ( 'family_name' in data ) ? data.family_name : '' ) );
								$( '#shipping_postcode' ).val( ( ( 'postal_code' in data) ? data.postal_code : '' ) );
								$( '#shipping_country' ).val( ( ( 'country' in data ) ? country : '' ) );
								$( '#shipping_country' ).change();
							} else {
								$( '#billing_first_name' ).val( ( ( 'given_name' in data ) ? data.given_name : '' ) );
								$( '#billing_last_name' ).val( ( ( 'family_name' in data ) ? data.family_name : '' ) );
								$( '#billing_postcode' ).val( ( ( 'postal_code' in data) ? data.postal_code : '' ) );
								$( '#billing_country' ).val( ( ( 'country' in data ) ? country : '' ) );
								$( '#billing_email' ).val( ( ( 'email' in data ) ? data.email : '' ) );
								$( '#billing_country' ).change();
								$( '#billing_email' ).change();
								$( '#billing_email' ).blur();
							}

							$( 'form.checkout' ).trigger( 'update_checkout' );
						},
						'change': function( data ) {
							kco_wc.log( 'change', data );
						},
						'order_total_change': function( data ) {
							kco_wc.log( 'order_total_change', data );
						},
						'shipping_option_change': function( data ) {
							kco_wc.log( 'shipping_option_change', data );
							kco_wc.log( data );
							kco_wc.updateShipping( data )
						},
						'can_not_complete_order': function( data ) {
							kco_wc.log( 'can_not_complete_order', data );
						},
						'validation_callback': function (data, callback) {
							kco_wc.validation = true;
							kco_wc.logToFile( 'validation_callback from Klarna triggered' );
							if( kco_params.pay_for_order ) {
								callback({ should_proceed: true });
							} else {
								kco_wc.placeKlarnaOrder(callback);
							}
						}
					});
				});
			}
		},
	}

	kco_wc.init();
});

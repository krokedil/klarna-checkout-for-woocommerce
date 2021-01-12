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
		blocked: false,

		preventPaymentMethodChange: false,

		timeout: null,
		interval: null,

		// True or false if we need to update the Klarna order. Set to false on initial page load.
		klarnaUpdateNeeded: false,

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
			kco_wc.moveExtraCheckoutFields();
			kco_wc.updateShipping( false );
		},

		/**
		 * Resumes the Klarna Iframe
		 * @param {boolean} autoResumeBool 
		 */
		kcoSuspend: function( autoResumeBool ) {
			if ( window._klarnaCheckout ) {
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
			if ( window._klarnaCheckout ) {
				window._klarnaCheckout( function( api ) {
					if ( false === kco_wc.blocked ) {
						api.resume();
					}
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
			for ( i = 0; i < form.length; i++ ) {
				var name = form[i].name;
				// Check if field is inside the order review.
				if( $( 'table.woocommerce-checkout-review-order-table' ).find( form[i] ).length ) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, kco_params.standard_woo_checkout_fields ) ) {					
					// This is not a standard Woo field, move to our div.
					if ( 0 < $( 'p#' + name + '_field' ).length ) {
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
			
			if ( 'kco' === kco_wc.paymentMethod && 'yes' === kco_params.shipping_methods_in_iframe && 'no' === kco_params.is_confirmation_page ) {
				if ( $( '#shipping_method input[type=\'radio\']' ).length ) {
					// Multiple shipping options available.
					$( '#shipping_method input[type=\'radio\']:checked' ).each( function() {
						var idVal = $( this ).attr( 'id' );
						var shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
						$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
						$( '.woocommerce-shipping-totals td' ).addClass( 'kco-shipping' );
					});
				} else {
					// Only one shipping option available.
					var idVal = $( '#shipping_method input[name=\'shipping_method[0]\']' ).attr( 'id' );
					var shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
					$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
					$( '.woocommerce-shipping-totals td' ).addClass( 'kco-shipping' );
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
		 * Update the Klarna order on updated_checkout events.
		 */
		updateKlarnaOrder: function() {
			if ( 'kco' === kco_wc.paymentMethod && 'no' === kco_params.is_confirmation_page ) {
				if( ! kco_wc.klarnaUpdateNeeded ) {
					kco_wc.klarnaUpdateNeeded = true;
					return;
				}
				$.ajax({
					type: 'POST',
					url: kco_params.update_klarna_order_url,
					data: {
						nonce: kco_params.update_klarna_order_nonce
					},
					dataType: 'json',
					success: function( data ) {
					},
					error: function( data ) {
					},
					complete: function( data ) {
						if ( true === data.responseJSON.success ) {
							kco_wc.kcoResume();
							$( '.woocommerce-checkout-review-order-table' ).unblock();
						} else if( ! data.responseJSON.success && data.responseJSON.data.redirect_url !== 'undefined' ) {
							window.location = data.responseJSON.data.redirect_url;
						}
					}
				});
			}
		},

		/**
		 * Gets the Klarna order and starts the order submission
		 */
		getKlarnaOrder: function() {
			kco_wc.preventPaymentMethodChange = true;
			$( '.woocommerce-checkout-review-order-table' ).block({
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
				success: function( data ) {
				},
				error: function( data ) {
					return false;
				},
				complete: function( data ) {
					kco_wc.setCustomerData( data.responseJSON.data );

					// Check Terms checkbox, if it exists.
					if ( 0 < $( 'form.checkout #terms' ).length ) {
						$( 'form.checkout #terms' ).prop( 'checked', true );
					}
					$( 'form.checkout' ).submit();
					return true;
				}
			});
		},

		/**
		 * Sets the customer data.
		 * @param {array} data 
		 */
		setCustomerData: function( data ) {
			kco_wc.log( data );
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
				$( '#shipping_company' ).val( ( ( 'organization_name' in data.shipping_address ) ? data.billing_address.organization_name : '' ) );
				$( '#shipping_address_1' ).val( ( ( 'street_address' in data.shipping_address ) ? data.shipping_address.street_address : '' ) );
				$( '#shipping_address_2' ).val( ( ( 'street_address2' in data.shipping_address ) ? data.shipping_address.street_address2 : '' ) );
				$( '#shipping_city' ).val( ( ( 'city' in data.shipping_address ) ? data.shipping_address.city : '' ) );
				$( '#shipping_postcode' ).val( ( ( 'postal_code' in data.shipping_address ) ? data.shipping_address.postal_code : '' ) );
				$( '#shipping_country' ).val( ( ( 'country' in data.shipping_address ) ? data.shipping_address.country.toUpperCase() : '' ) );
				$( '#shipping_state' ).val( ( ( 'region' in data.shipping_address ) ? data.shipping_address.region : '' ) );
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
					kco_wc.checkoutFormSelector.removeClass( 'processing' );
					$( '.woocommerce-checkout-review-order-table' ).unblock();
					$( kco_wc.checkoutFormSelector ).unblock();
				}
			} 
		},

		/**
		 * Fails the order with Klarna on a checkout error and timeout.
		 * @param {function} callback 
		 * @param {string} event 
		 */
		failOrder: function( callback, event ) {
			// Send false and cancel 
			callback({ should_proceed: false });
			// Clear the interval.
			clearInterval(kco_wc.interval);
			// Remove the timeout.
			clearTimeout( kco_wc.timeout );
			// Re-enable the form.
			$( 'body' ).trigger( 'updated_checkout' );
			kco_wc.checkoutFormSelector.removeClass( 'processing' );
			$( kco_wc.checkoutFormSelector ).unblock();
			if ( 'timeout' === event ) {
				kco_wc.logToFile( 'Timeout for validation_callback triggered.' );
				$('#kco-timeout').remove();
				$('form.checkout').prepend(
					'<div id="kco-timeout" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>'
					+  kco_params.timeout_message
					+ '</li></ul></div>'
				);
			} else {
				var error_message = $( ".woocommerce-NoticeGroup-checkout" ).text();
				kco_wc.logToFile( 'Checkout error - ' + error_message );
			}
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
			$.ajax(
				{
					url: kco_params.update_shipping_url,
					type: 'POST',
					dataType: 'json',
					data: {
						data: data,
						nonce: kco_params.update_shipping_nonce
					},
					success: function( response ) {
						kco_wc.log( response );
					},
					error: function( response ) {
						kco_wc.log( response );
					},
					complete: function( response ) {
						//kco_wc.klarnaUpdateNeeded = false;
						$( '#shipping_method #' + response.responseJSON.data.shipping_option_name ).prop( 'checked', true );
						$( 'body' ).trigger( 'kco_shipping_option_changed', [ data ]);
						$( 'body' ).trigger( 'update_checkout' );
						//kco_wc.kcoResume();
					}
				}
			);
		},

		/**
		 * Initiates the script.
		 */
		init: function() {
			$( document ).ready( kco_wc.documentReady );
			kco_wc.bodyEl.on( 'update_checkout', function() {  if( kco_wc.klarnaUpdateNeeded ) { kco_wc.kcoSuspend( true ) } } );
			kco_wc.bodyEl.on( 'updated_checkout', kco_wc.updateKlarnaOrder );
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
							$( '.woocommerce-checkout-review-order-table' ).block({
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
										nonce: kco_params.iframe_shipping_address_change_nonce
									},
									success: function( response ) {
										kco_wc.log( response );

										// Check if we have new address data to apply to the form.
										if( response.data ) {
											// All good trigger update_checkout event
											kco_wc.setCustomerData( response.data );
										}

										$( 'body' ).trigger( 'update_checkout' );
									},
									error: function( response ) {
										kco_wc.log( response );
									},
									complete: function( response ) {
										$( '.woocommerce-checkout-review-order-table' ).unblock();
										kco_wc.shippingUpdated = true;
										kco_wc.bodyEl.trigger( 'kco_shipping_address_changed', response );
									}
								}
							);
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
						'validation_callback': function( data, callback ) {
							kco_wc.logToFile( 'validation_callback from Klarna triggered' );
							// Empty current hash.
							window.location.hash = '';
							// Check for any errors.
							kco_wc.timeout = setTimeout( function() { kco_wc.failOrder( callback, 'timeout' ); }, kco_params.timeout_time * 1000 );
							$( document.body ).on( 'checkout_error', function() { kco_wc.failOrder( callback, 'checkout_error' ); } );
							// Run interval until we find a hashtag or timer runs out.
							kco_wc.interval = setInterval( function() { kco_wc.checkUrl( callback ); }, 500 );
							// Start processing the order.
							kco_wc.getKlarnaOrder();
						}
					});
				});
			}
		},
	}

	kco_wc.init();
});
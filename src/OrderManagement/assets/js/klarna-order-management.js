jQuery( function ( $ ) {
	$( document ).ready( function () {
		const kom = {
			edit_button: $( ".kom_order_sync_edit" ),
			order_sync_box: $( ".kom_order_sync--box" ),
			toggle_button: $( ".kom_order_sync--toggle .woocommerce-input-toggle" ),
			submit_button: $( ".kom_order_sync--action > .submit_button" ),
			cancel_button: $( ".kom_order_sync--action > .cancel_button" ),
			refund_items_button: $( ".button.refund-items" ),
			cancel_refund_button: $( ".refund-actions .cancel-action" ),
			refund_submit_button: $( "button.do-api-refund" ),
			refund_amount_field: $( "#refund_amount" ),
			sync_status: function () {
				return kom.toggle_button.hasClass( "woocommerce-input-toggle--enabled" ) ? "enabled" : "disabled"
			},
			show_refund_fee_section: function () {
				// Show the return fee section if it is hidden.
				$klarnaReturnFee = $( "#klarna_return_fee" )
				$klarnaReturnFee.show()
			},
			hide_refund_fee_section: function () {
				// Hide the return fee section if it is visible.
				// If the return fee section is hidden, do nothing.
				$klarnaReturnFee = $( "#klarna_return_fee" )

				if ( $klarnaReturnFee.attr( "data-klarna-hide" ) === "no" ) {
					return
				}

				$klarnaReturnFee.hide()
			},
			modify_refund_button_text: function () {
				// Add a span with id klarna_return_fee_total to the button with class do-api-refund.
				// This span will be used to display the return fee amount in the button text.
				const $klarnaRefundButton = $( "button.do-api-refund" )
				$klarnaRefundButton.append( '<span id="klarna_return_fee_total"></span>' )
			},
			update_klarna_refund_amount: function () {
				// Get the return fee amount and tax amount from the input fields.
				// If the return fee amount is 0, do nothing.
				const $klarnaReturnFeeAmountField = $( "#klarna_return_fee input.refund_line_total.wc_input_price" )
				const $klarnaReturnFeeTaxAmountField = $( "#klarna_return_fee input.refund_line_tax.wc_input_price" )
				const $klarnaReturnFeeTotalSpan = $( "span#klarna_return_fee_total" )

				const refundFeeAmount =
					kom.unformat_number( $klarnaReturnFeeAmountField.val() ) +
					kom.unformat_number( $klarnaReturnFeeTaxAmountField.val() )

				if ( refundFeeAmount === 0 ) {
					$klarnaReturnFeeTotalSpan.text( "" )
					return
				}

				// Update the button text with the return fee amount by replacing inner text of the span#klarna_return_fee_total with the refund fee amount.
				$klarnaReturnFeeTotalSpan.text(
					" (" + kom_admin_params.with_return_fee_text + " " + kom.format_number( refundFeeAmount ) + ")",
				)
			},
			format_number: function ( number ) {
				// Format the number using accounting.js with the currency format settings from woocommerce_admin_meta_boxes.
				return accounting.formatMoney( number, {
					symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
					decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
					thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
					precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
					format: woocommerce_admin_meta_boxes.currency_format,
				} )
			},
			unformat_number: function ( number ) {
				// Unformat the number using accounting.js with the decimal point from woocommerce_admin.
				// This is used to convert the formatted number back to a float for calculations.
				return accounting.unformat( number, woocommerce_admin.mon_decimal_point )
			},
			on_refund_submit: function ( e ) {
				// Get the refund amount from the input field.
				const $refundAmount = $( "#refund_amount" )
				const $klarnaReturnFeeAmountField = $( "#klarna_return_fee input.refund_line_total.wc_input_price" )
				const $klarnaReturnFeeTaxAmountField = $( "#klarna_return_fee input.refund_line_tax.wc_input_price" )

				const diff =
					kom.unformat_number( $refundAmount.val() ) -
					( kom.unformat_number( $klarnaReturnFeeAmountField.val() ) +
						kom.unformat_number( $klarnaReturnFeeTaxAmountField.val() ) )

				if ( diff < 0 ) {
					// Show an alert box with the message "Refund amount is less than the return fee amount."
					window.alert( kom_admin_params.refund_amount_less_than_return_fee_text )

					// Pause the default action of the button.
					e.preventDefault()
					e.stopPropagation()
					return
				}
			},
		}

		kom.edit_button.on( "click", function () {
			if ( "none" !== kom.edit_button.css( "display" ) ) {
				kom.order_sync_box.fadeIn()
				kom.edit_button.css( "display", "none" )
			} else {
				kom.edit_button.css( "display", "" )
				kom.order_sync_box.css( "display", "" )
			}
		} )

		kom.toggle_button.click( function () {
			const url = new URL( kom.submit_button.attr( "href" ), window.location )
			kom.toggle_button.toggleClass( "woocommerce-input-toggle--disabled woocommerce-input-toggle--enabled" )
			url.searchParams.set( "kom", kom.sync_status() )
			kom.submit_button.attr( "href", url.toString() )
		} )

		kom.cancel_button.on( "click", function () {
			kom.edit_button.click()
		} )

		kom.refund_items_button.on( "click", function () {
			console.log( "refund_items_button" )
			kom.show_refund_fee_section()
		} )
		kom.cancel_refund_button.on( "click", function () {
			kom.hide_refund_fee_section()
		} )
		kom.refund_submit_button.on( "click", function ( e ) {
			kom.on_refund_submit( e )
		} )
		kom.refund_amount_field.on( "change", function () {
			kom.update_klarna_refund_amount()
		} )
		kom.modify_refund_button_text()
	} )
} )

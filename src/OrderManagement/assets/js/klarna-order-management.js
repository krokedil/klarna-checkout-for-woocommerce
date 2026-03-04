/**
 * @var komAdminParams
 */
jQuery( function ( $ ) {
    const format_number = ( unformatted_number ) => {
        // Format the number using accounting.js with the currency format settings from woocommerce_admin_meta_boxes.
        return accounting.formatMoney( unformatted_number, {
            symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
            decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
            thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
            precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
            format: woocommerce_admin_meta_boxes.currency_format,
        } )
    }

    const unformat_number = ( formatted_number ) => {
        // Unformat the number using accounting.js with the decimal point from woocommerce_admin.
        // This is used to convert the formatted number back to a float for calculations.
        return accounting.unformat( formatted_number, woocommerce_admin.mon_decimal_point )
    }

    const komMetabox = {
        init: function () {
            $( document ).on( "click", ".kom_order_sync--action", this.toggleOrderSync )
        },

        toggleOrderSync: async function ( e ) {
            e.preventDefault()
            const $this = $( this )
            const $metabox = $( "#klarna-om" )
            const omStatus = $this.attr( "data-kom-order-sync" )

            // Block the page to prevent changing the order during the request.
            $metabox.block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )

            // Make the AJAX request to toggle the order sync for the order.
            const result = await komMetabox.ajaxSetOrderSync( omStatus )
            if ( result.success ) {
                komMetabox.toggleButton( $this, "enabled" === omStatus ? true : false )
            } else {
                alert( "Failed to toggle order sync. Please try again." )
            }

            // Reload the page to ensure the metadata has been added to the form.
            location.reload()
        },

        toggleButton: function ( $button, enabled ) {
            $button
                .attr( "data-kom-order-sync", enabled )
                .toggleClass( "woocommerce-input-toggle--enabled" )
                .toggleClass( "woocommerce-input-toggle--disabled" )
        },

        ajaxSetOrderSync: async function ( omStatus ) {
            const orderId = komAdminParams.orderId
            const { url, action, nonce } = komAdminParams.ajax.setOrderSync

            const data = {
                nonce: nonce,
                action: action,
                order_id: orderId,
                om_status: omStatus,
            }

            return $.ajax( {
                type: "POST",
                url: url,
                data: data,
            } )
        },
    }

    const returnFee = {
        // TODO: This might conflict with the standalone KOM plugin.
        refund_items_button: $( ".button.refund-items" ),
        cancel_refund_button: $( ".refund-actions .cancel-action" ),
        refund_submit_button: $( "button.do-api-refund" ),
        refund_amount_field: $( "#refund_amount" ),

        init: function () {
            returnFee.refund_items_button.on( "click", () => {
                console.log( "refund_items_button" )
                returnFee.show_refund_fee_section()
            } )
            returnFee.cancel_refund_button.on( "click", () => {
                returnFee.hide_refund_fee_section()
            } )
            returnFee.refund_submit_button.on( "click", ( e ) => {
                returnFee.on_refund_submit( e )
            } )
            returnFee.refund_amount_field.on( "change", () => {
                returnFee.update_klarna_refund_amount()
            } )
            returnFee.modify_refund_button_text()
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

            const returnFeeAmount =
                unformat_number( $klarnaReturnFeeAmountField.val() ) +
                unformat_number( $klarnaReturnFeeTaxAmountField.val() )

            if ( returnFeeAmount === 0 ) {
                $klarnaReturnFeeTotalSpan.text( "" )
                return
            }

            // Update the button text with the return fee amount by replacing inner text of the span#klarna_return_fee_total with the refund fee amount.
            $klarnaReturnFeeTotalSpan.text(
                " (" + komAdminParams.with_return_fee_text + " " + format_number( returnFeeAmount ) + ")",
            )
        },

        on_refund_submit: function ( e ) {
            // Get the refund amount from the input field.
            const $refundAmount = $( "#refund_amount" )
            const $klarnaReturnFeeAmountField = $( "#klarna_return_fee input.refund_line_total.wc_input_price" )
            const $klarnaReturnFeeTaxAmountField = $( "#klarna_return_fee input.refund_line_tax.wc_input_price" )

            const diff =
                unformat_number( $refundAmount.val() ) -
                ( unformat_number( $klarnaReturnFeeAmountField.val() ) +
                    unformat_number( $klarnaReturnFeeTaxAmountField.val() ) )

            if ( diff < 0 ) {
                // Show an alert box with the message "Refund amount is less than the return fee amount."
                window.alert( komAdminParams.refund_amount_less_than_return_fee_text )

                // Pause the default action of the button.
                e.preventDefault()
                e.stopPropagation()
                return
            }
        },
    }

    komMetabox.init()
    returnFee.init()
} )

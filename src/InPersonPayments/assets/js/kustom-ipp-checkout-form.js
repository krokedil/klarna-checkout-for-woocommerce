jQuery( function ( $ ) {
	"use strict"

	const kco_ipp_checkout_form = {
		// The forms whose details are not added to an in-person order.
		formsSelector: ".woocommerce-billing-fields, .woocommerce-shipping-fields",

		// Marks a blocked form, and scopes the styles below to it.
		blockedClass: "kco-ipp-no-address",

		init: function () {
			kco_ipp_checkout_form.addStyles()

			$( document.body ).on( "payment_method_selected updated_checkout", kco_ipp_checkout_form.sync )
			$( document.body ).on( "change", "input[name='payment_method']", kco_ipp_checkout_form.sync )

			kco_ipp_checkout_form.sync()
		},

		/**
		 * Hide the blockUI spinner, since the forms are blocked rather than loading, and hide
		 * the values, so the salesperson does not think they end up on the order.
		 */
		addStyles: function () {
			$(
				"<style>.kco-ipp-no-address .blockUI.blockOverlay::before { display: none !important; } .kco-ipp-no-address input, .kco-ipp-no-address select, .kco-ipp-no-address textarea, .kco-ipp-no-address .select2-selection__rendered { color: transparent !important; -webkit-text-fill-color: transparent !important; text-shadow: none !important; }</style>"
			).appendTo( "head" )
		},

		/**
		 * Whether in-person payment is the chosen method.
		 *
		 * @return {boolean} True when it is.
		 */
		isChosen: function () {
			return "kco_ipp" === $( "input[name='payment_method']:checked" ).val()
		},

		/**
		 * Block or unblock the forms to match the chosen payment method.
		 */
		sync: function () {
			const $forms = $( kco_ipp_checkout_form.formsSelector )

			if ( kco_ipp_checkout_form.isChosen() ) {
				$forms
					.not( "." + kco_ipp_checkout_form.blockedClass )
					.addClass( kco_ipp_checkout_form.blockedClass )
					.block( {
						message: null,
						overlayCSS: { background: "#fff", opacity: 0.6, cursor: "not-allowed" },
					} )
			} else {
				$forms
					.filter( "." + kco_ipp_checkout_form.blockedClass )
					.removeClass( kco_ipp_checkout_form.blockedClass )
					.unblock()
			}
		},
	}

	kco_ipp_checkout_form.init()
} )

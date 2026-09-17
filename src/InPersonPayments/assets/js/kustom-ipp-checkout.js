/* global kco_ipp_checkout_params */
jQuery( function ( $ ) {
	"use strict"

	// Check if we have params.
	if ( "undefined" === typeof kco_ipp_checkout_params ) {
		return false
	}

	var kco_ipp_checkout = {
		// The screen the salesperson watches while the customer pays on the device.
		screenEl: $( "#kco-ipp-waiting" ),
		errorEl: null,
		cancelEl: null,

		// How long to wait between polls, in milliseconds.
		interval: kco_ipp_checkout_params.interval || 2000,

		// When the screen started asking, so the poll knows when to slow down.
		startedAt: 0,

		// True once the session has reached a state that ends the wait.
		stopped: false,

		init: function () {
			if ( ! kco_ipp_checkout.screenEl.length ) {
				return
			}

			kco_ipp_checkout.errorEl = kco_ipp_checkout.screenEl.find( ".kco-ipp-waiting-error" )
			kco_ipp_checkout.cancelEl = kco_ipp_checkout.screenEl.find( ".kco-ipp-cancel-session" )
			kco_ipp_checkout.startedAt = Date.now()

			kco_ipp_checkout.cancelEl.on( "click", kco_ipp_checkout.cancelSession )

			window.setTimeout( kco_ipp_checkout.poll, kco_ipp_checkout.interval )
		},

		/**
		 * Ask one of the session routes, identifying the order by its key.
		 *
		 * @param {string} url The REST route to call.
		 * @param {string} method The HTTP method to call it with.
		 * @return {object} The jQuery promise for the request.
		 */
		request: function ( url, method ) {
			const separator = -1 === url.indexOf( "?" ) ? "?" : "&"

			return $.ajax( {
				url: url + separator + "key=" + encodeURIComponent( kco_ipp_checkout_params.order_key ),
				method: method,
				beforeSend: function ( xhr ) {
					xhr.setRequestHeader( "X-WP-Nonce", kco_ipp_checkout_params.nonce )
				},
			} )
		},

		/**
		 * How long to wait before asking again.
		 *
		 * The device is slow to be picked up far more often than it is broken, so a long
		 * wait only slows the polling down rather than giving up on the sale.
		 *
		 * @return {number} The delay in milliseconds.
		 */
		nextDelay: function () {
			const backoffAfter = kco_ipp_checkout_params.backoff_after || 60000

			if ( Date.now() - kco_ipp_checkout.startedAt < backoffAfter ) {
				return kco_ipp_checkout.interval
			}

			kco_ipp_checkout.interval = Math.min( kco_ipp_checkout.interval * 2, 30000 )

			return kco_ipp_checkout.interval
		},

		/**
		 * Act on what the session routes answered.
		 *
		 * @param {object} response The decoded response body.
		 * @return {void}
		 */
		handle: function ( response ) {
			if ( ! response || ! response.state ) {
				return
			}

			if ( response.message ) {
				kco_ipp_checkout.errorEl.text( response.message ).show()
			} else {
				kco_ipp_checkout.errorEl.hide().text( "" )
			}

			if ( "waiting" === response.state ) {
				return
			}

			kco_ipp_checkout.stopped = true
			kco_ipp_checkout.cancelEl.prop( "disabled", true )

			if ( response.redirect ) {
				window.location.href = response.redirect
			}
		},

		/**
		 * Ask where the session has got to, until it has got somewhere.
		 *
		 * @return {void}
		 */
		poll: function () {
			if ( kco_ipp_checkout.stopped ) {
				return
			}

			kco_ipp_checkout
				.request( kco_ipp_checkout_params.status_url, "GET" )
				.done( kco_ipp_checkout.handle )
				.fail( function () {
					kco_ipp_checkout.errorEl.text( kco_ipp_checkout_params.error_message ).show()
				} )
				.always( function () {
					if ( ! kco_ipp_checkout.stopped ) {
						window.setTimeout( kco_ipp_checkout.poll, kco_ipp_checkout.nextDelay() )
					}
				} )
		},

		/**
		 * Ask the device to give up on the session.
		 *
		 * @param {object} event The click event.
		 * @return {void}
		 */
		cancelSession: function ( event ) {
			event.preventDefault()

			kco_ipp_checkout.cancelEl.prop( "disabled", true )

			kco_ipp_checkout
				.request( kco_ipp_checkout_params.cancel_url, "POST" )
				.done( kco_ipp_checkout.handle )
				.fail( function () {
					kco_ipp_checkout.errorEl.text( kco_ipp_checkout_params.error_message ).show()
					kco_ipp_checkout.cancelEl.prop( "disabled", false )
				} )
		},
	}

	kco_ipp_checkout.init()
} )

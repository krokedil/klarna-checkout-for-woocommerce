/* global kco_ipp_admin_params */
jQuery( function ( $ ) {
	"use strict"

	// Check if we have params.
	if ( "undefined" === typeof kco_ipp_admin_params ) {
		return false
	}

	var kco_ipp_admin = {
		// The wrappers the settings section renders each block of the UI into.
		devicesSelector: "#kco-ipp-devices",
		enrollmentSelector: "#kco-ipp-enrollment",

		// The modal templates WooCommerce renders for us.
		enrollmentModal: "kco-ipp-enrollment-modal",
		deviceModal: "kco-ipp-device-modal",

		init: function () {
			$( document ).on( "click", ".kco-ipp-refresh-devices", kco_ipp_admin.refreshDevices )
			$( document ).on( "click", ".kco-ipp-create-enrollment", kco_ipp_admin.openEnrollmentModal )
			$( document ).on( "click", ".kco-ipp-edit-device", kco_ipp_admin.openDeviceModal )

			// The modals' submits. WooCommerce fires this with the serialized form fields.
			$( document ).on( "wc_backbone_modal_response", kco_ipp_admin.modalResponse )
		},

		/**
		 * Ask one of the admin AJAX actions, reporting failure in the block's own error
		 * paragraph and keeping its button disabled while it waits.
		 *
		 * @param {string} action The AJAX action to call.
		 * @param {object} data The data to send with it.
		 * @param {Function} onSuccess What to do with the data that comes back.
		 * @param {object} errorEl The paragraph to report a failure in.
		 * @param {object} buttonEl The button to disable while it waits.
		 * @return {void}
		 */
		post: function ( action, data, onSuccess, errorEl, buttonEl ) {
			errorEl.hide().text( "" )
			buttonEl.prop( "disabled", true )

			$.post( kco_ipp_admin_params.ajax_url, $.extend( { action: action, nonce: kco_ipp_admin_params.nonce }, data ) )
				.done( function ( response ) {
					if ( response && response.success ) {
						onSuccess( response.data )
					} else {
						errorEl
							.text( ( response && response.data && response.data.message ) || kco_ipp_admin_params.error_message )
							.show()
					}
				} )
				.fail( function () {
					errorEl.text( kco_ipp_admin_params.error_message ).show()
				} )
				.always( function () {
					buttonEl.prop( "disabled", false )
				} )
		},

		/**
		 * Throw the cached device list away and redraw the table.
		 *
		 * @param {object} event The click event.
		 * @return {void}
		 */
		refreshDevices: function ( event ) {
			event.preventDefault()

			var wrapperEl = $( kco_ipp_admin.devicesSelector )

			kco_ipp_admin.post(
				"kco_ipp_refresh_devices",
				{},
				function ( data ) {
					wrapperEl.find( ".kco-ipp-devices-table" ).html( data.html )
				},
				wrapperEl.find( ".kco-ipp-error" ),
				$( this )
			)
		},

		/**
		 * Ask which lifetime the enrollment code should have.
		 *
		 * @param {object} event The click event.
		 * @return {void}
		 */
		openEnrollmentModal: function ( event ) {
			event.preventDefault()

			$( this ).WCBackboneModal( { template: kco_ipp_admin.enrollmentModal } )
		},

		/**
		 * Ask what the device should be called.
		 *
		 * @param {object} event The click event.
		 * @return {void}
		 */
		openDeviceModal: function ( event ) {
			event.preventDefault()

			$( this ).WCBackboneModal( {
				template: kco_ipp_admin.deviceModal,
				variable: {
					id: $( this ).data( "device-id" ),
					name: $( this ).data( "device-name" ),
				},
			} )
		},

		/**
		 * Carry out what a modal was submitted for.
		 *
		 * @param {object} event The modal response event.
		 * @param {string} target The template the response came from.
		 * @param {object} data The serialized form fields.
		 * @return {void}
		 */
		modalResponse: function ( event, target, data ) {
			if ( kco_ipp_admin.enrollmentModal === target ) {
				kco_ipp_admin.createEnrollment( data )
			}

			if ( kco_ipp_admin.deviceModal === target ) {
				kco_ipp_admin.renameDevice( data )
			}
		},

		/**
		 * Ask Kustom for a code the salesperson types into the POS app.
		 *
		 * @param {object} data The serialized form fields.
		 * @return {void}
		 */
		createEnrollment: function ( data ) {
			var wrapperEl = $( kco_ipp_admin.enrollmentSelector )

			kco_ipp_admin.post(
				"kco_ipp_create_enrollment",
				{ ttl: data.ttl },
				function ( result ) {
					var codeEl = wrapperEl.find( ".kco-ipp-code" )

					codeEl.find( "code" ).text( result.code )
					codeEl.find( ".description" ).text( result.expires )
					codeEl.show()
				},
				wrapperEl.find( ".kco-ipp-error" ),
				wrapperEl.find( ".kco-ipp-create-enrollment" )
			)
		},

		/**
		 * Give a paired device a name the counter recognises.
		 *
		 * @param {object} data The serialized form fields.
		 * @return {void}
		 */
		renameDevice: function ( data ) {
			var wrapperEl = $( kco_ipp_admin.devicesSelector )

			kco_ipp_admin.post(
				"kco_ipp_rename_device",
				{ device_id: data.device_id, name: data.name },
				function ( result ) {
					wrapperEl.find( ".kco-ipp-devices-table" ).html( result.html )
				},
				wrapperEl.find( ".kco-ipp-error" ),
				wrapperEl.find( ".kco-ipp-refresh-devices" )
			)
		},
	}

	kco_ipp_admin.init()
} )

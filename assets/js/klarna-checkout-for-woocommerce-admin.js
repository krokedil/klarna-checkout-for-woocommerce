jQuery( function ( $ ) {
	"use strict"
	var location = kco_admin_params.location
	var titles = $( "h3.wc-settings-sub-title" )
	var tables = $( "h3.wc-settings-sub-title + table.form-table" )
	var submit = $( ".wrap.woocommerce p.submit" )

	var credentialsFields =
		"input#woocommerce_kco_test_merchant_id_eu, input#woocommerce_kco_merchant_id_eu, input#woocommerce_kco_test_merchant_id_us, input#woocommerce_kco_merchant_id_us"

	titles.append(
		' <a href="#" class="collapsed" style="font-size:12px; font-weight: normal; text-decoration: none"><span class="dashicons dashicons-arrow-down-alt2"></span></a>',
	)
	tables.css( "marginLeft", "20px" ).hide()
	if ( location === "EU" ) {
		var title = $( "#woocommerce_kco_credentials_eu" )
		title.find( "a" ).html( '<span class="dashicons dashicons-arrow-up-alt2">' )
		title.next().show()
	} else if ( location === "US" ) {
		var title = $( "#woocommerce_kco_credentials_us" )
		title.find( "a" ).html( '<span class="dashicons dashicons-arrow-up-alt2">' )
		title.next().show()
	} else {
		var title = titles
	}

	titles.find( "a" ).on( "click", function ( e ) {
		console.log( "click" )
		e.preventDefault()

		if ( $( this ).hasClass( "collapsed" ) ) {
			$( this ).parent().next().show()
			$( this ).removeClass( "collapsed" )
			$( this ).html( '<span class="dashicons dashicons-arrow-up-alt2"></span>' )
		} else {
			$( this ).parent().next().hide()
			$( this ).addClass( "collapsed" )
			$( this ).html( '<span class="dashicons dashicons-arrow-down-alt2"></span>' )
		}
	} )

	titles.before( '<hr style="margin-top:2em;margin-bottom:2em" />' )
	submit.before( '<hr style="margin-top:2em;margin-bottom:2em" />' )

	function testCredential() {
		var field = $( this )

		// Remove any old blocks at this point.
		field.removeClass( "bad_credential" )
		$( 'button[name="save"]' ).removeAttr( "disabled" )

		// If value is empty do nothing.
		if ( "" === field.val() ) {
			return
		}

		var regex =
			/^([A-Za-z0-9]{1,2}[0-9]{5}|[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12})/

		if ( ! regex.test( field.val() ) ) {
			field.addClass( "bad_credential" )
			$( 'button[name="save"]' ).attr( "disabled", "disabled" )
			window.alert( "Please verify your Kustom Credentials." )
			return
		}
	}

	//Checkbox
	var testCheckBox = $( "#woocommerce_kco_testmode" )
	//EU
	var EUmerchantIdField = $( "#woocommerce_kco_test_merchant_id_eu" )
	var EUmerchantPasswordField = $( "#woocommerce_kco_test_shared_secret_eu" )
	//US
	var USmerchantIdField = $( "#woocommerce_kco_test_merchant_id_eu" )
	var USmerchantPasswordField = $( "#woocommerce_kco_test_shared_secret_eu" )
	//Save Changes
	var saveChangesButton = $( ".button-primary, .woocommerce-save-button" )

	function checkEmptyFields() {
		if ( testCheckBox.prop( "checked" ) ) {
			if ( location === "EU" ) {
				saveChangesButton.prop( "disabled", true )
				if ( ! EUmerchantIdField.val() && ! EUmerchantPasswordField.val() ) {
					alert( "Please enter valid Test Merchant information" )
					EUmerchantIdField.trigger( "focus" )
				} else if ( ! EUmerchantPasswordField.val() ) {
					alert( "Please enter a valid Test Merchant Password" )
					EUmerchantPasswordField.trigger( "focus" )
				} else if ( ! EUmerchantIdField.val() ) {
					alert( "Please enter a valid Test Merchant ID" )
					EUmerchantIdField.trigger( "focus" )
				} else {
					saveChangesButton.prop( "disabled", false )
				}
			} else if ( location === "US" ) {
				saveChangesButton.prop( "disabled", true )
				if ( ! USmerchantIdField.val() && ! USmerchantPasswordField.val() ) {
					alert( "Please enter valid Test Merchant information" )
					USmerchantIdField.trigger( "focus" )
				} else if ( ! USmerchantPasswordField.val() ) {
					alert( "Please enter a valid Test Merchant Password" )
					USmerchantPasswordField.trigger( "focus" )
				} else if ( ! USmerchantIdField.val() ) {
					alert( "Please enter a valid Test Merchant ID" )
					USmerchantIdField.trigger( "focus" )
				} else {
					saveChangesButton.prop( "disabled", false )
				}
			}
		} else {
			saveChangesButton.prop( "disabled", false )
		}
	}

	testCheckBox.on( "click", function () {
		checkEmptyFields()
	} )

	$( "body" ).on( "change", credentialsFields, testCredential )

	/*
	 * The order management master switch governs every other setting in its section, so the rows for
	 * those settings are hidden while it is off rather than greyed out: a disabled checkbox still
	 * renders as checked, which reads as "this is active" when it no longer is. Hiding also keeps the
	 * saved values intact, since a hidden input is still posted - only a disabled one is left out.
	 */
	var omMasterSwitch = $( "#woocommerce_kco_kom_enabled" )
	var omGovernedRows = $(
		"#woocommerce_kco_kom_auto_capture, #woocommerce_kco_kom_auto_cancel, #woocommerce_kco_kom_auto_update, #woocommerce_kco_kom_auto_order_sync, #woocommerce_kco_kom_force_full_capture, #woocommerce_kco_kom_enable_refunds",
	).closest( "tr" )

	function toggleOmGovernedRows() {
		omGovernedRows.toggle( omMasterSwitch.is( ":checked" ) )
	}

	if ( omMasterSwitch.length ) {
		toggleOmGovernedRows()
		omMasterSwitch.on( "change", toggleOmGovernedRows )
	}
} )

jQuery( function ( $ ) {
	"use strict"
	var submit = $( ".wrap.woocommerce p.submit" )

	var credentialsFields = "input#woocommerce_kco_test_merchant_id, input#woocommerce_kco_merchant_id"

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
	var merchantIdField = $( "#woocommerce_kco_test_merchant_id" )
	var merchantPasswordField = $( "#woocommerce_kco_test_shared_secret" )
	//Save Changes
	var saveChangesButton = $( ".button-primary, .woocommerce-save-button" )

	function checkEmptyFields() {
		if ( testCheckBox.prop( "checked" ) ) {
			saveChangesButton.prop( "disabled", true )
			if ( ! merchantIdField.val() && ! merchantPasswordField.val() ) {
				alert( "Please enter valid Test Merchant information" )
				merchantIdField.trigger( "focus" )
			} else if ( ! merchantPasswordField.val() ) {
				alert( "Please enter a valid Test Merchant Password" )
				merchantPasswordField.trigger( "focus" )
			} else if ( ! merchantIdField.val() ) {
				alert( "Please enter a valid Test Merchant ID" )
				merchantIdField.trigger( "focus" )
			} else {
				saveChangesButton.prop( "disabled", false )
			}
		} else {
			saveChangesButton.prop( "disabled", false )
		}
	}

	testCheckBox.on( "click", function () {
		checkEmptyFields()
	} )

	$( "body" ).on( "change", credentialsFields, testCredential )
} )

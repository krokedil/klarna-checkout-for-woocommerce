jQuery( function($) {
	'use strict';
	var location = kco_admin_params.location;
	var titles = $('h3.wc-settings-sub-title');
	var tables = $('h3.wc-settings-sub-title + table.form-table');
	var submit = $('.wrap.woocommerce p.submit');

	var credentialsFields = 'input#woocommerce_kco_test_merchant_id_eu, input#woocommerce_kco_merchant_id_eu, input#woocommerce_kco_test_merchant_id_us, input#woocommerce_kco_merchant_id_us';


	titles.append(' <a href="#" style="font-size:12px; font-weight: normal; text-decoration: none"><span class="dashicons dashicons-arrow-down-alt2"></span></a>');
	tables.css('marginLeft', '20px').hide();
	if(location === 'EU') {
		var title = $('#woocommerce_kco_credentials_eu');
		$('#woocommerce_kco_credentials_us').find('a').addClass('collapsed');
		title.find('a').html('<span class="dashicons dashicons-arrow-up-alt2">');
		title.next().show();
	} else if( location === 'US') {
		var title = $('#woocommerce_kco_credentials_us');
		$('#woocommerce_kco_credentials_eu').find('a').addClass('collapsed');
		title.find('a').html('<span class="dashicons dashicons-arrow-up-alt2">');
		title.next().show();
	} else {
		var title = titles;
	}
	$('#woocommerce_kco_color_settings_title').find('a').addClass('collapsed');

	titles.find('a').click(function(e) {
		e.preventDefault();

		if ($(this).hasClass('collapsed')) {
			$(this).parent().next().show();
			$(this).removeClass('collapsed');
			$(this).html('<span class="dashicons dashicons-arrow-up-alt2"></span>');
		} else {
			$(this).parent().next().hide();
			$(this).addClass('collapsed');
			$(this).html('<span class="dashicons dashicons-arrow-down-alt2"></span>');

		}
	});

	titles.before('<hr style="margin-top:2em;margin-bottom:2em" />');
	submit.before('<hr style="margin-top:2em;margin-bottom:2em" />');

	function testCredential() {
		var field = $(this);

		// Remove any old blocks at this point.
		field.removeClass( 'bad_credential' );
		$('button[name="save"]').removeAttr( 'disabled' );

		// If value is empty do nothing.
		if( '' === field.val() ) {
			return;
		}

		var regex = /[A-Za-z]{1,2}[0-9]{5}/;
		
		if ( !regex.test( field.val() ) ) {
			field.addClass( 'bad_credential' );
			$('button[name="save"]').attr( 'disabled', 'disabled' );
			window.alert('Please verify your Klarna Credentials.');
			return;
		}
	}

	$('body').on('change', credentialsFields, testCredential);
});

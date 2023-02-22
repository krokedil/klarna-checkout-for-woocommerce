jQuery( function($) {
	'use strict';
	var location = kco_admin_params.location;
	var titles = $('h3.wc-settings-sub-title');
	var tables = $('h3.wc-settings-sub-title + table.form-table');
	var submit = $('.wrap.woocommerce p.submit');

	var credentialsFields = 'input#woocommerce_kco_test_merchant_id_eu, input#woocommerce_kco_merchant_id_eu, input#woocommerce_kco_test_merchant_id_us, input#woocommerce_kco_merchant_id_us';


	titles.append(' <a href="#" class="collapsed" style="font-size:12px; font-weight: normal; text-decoration: none"><span class="dashicons dashicons-arrow-down-alt2"></span></a>');
	tables.css('marginLeft', '20px').hide();
	if(location === 'EU') {
		var title = $('#woocommerce_kco_credentials_eu');
		title.find('a').html('<span class="dashicons dashicons-arrow-up-alt2">');
		title.next().show();
	} else if( location === 'US') {
		var title = $('#woocommerce_kco_credentials_us');
		title.find('a').html('<span class="dashicons dashicons-arrow-up-alt2">');
		title.next().show();
	} else {
		var title = titles;
	}

	titles.find('a').click(function(e) {
		console.log('click');
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

	//Checkbox
	var testCheckBox = $('#woocommerce_kco_testmode');
	//EU
	var EUmerchantIdField = $('#woocommerce_kco_test_merchant_id_eu');
	var EUmerchantPasswordField = $('#woocommerce_kco_test_shared_secret_eu');
	//US
	var USmerchantIdField = $('#woocommerce_kco_test_merchant_id_eu');
	var USmerchantPasswordField = $('#woocommerce_kco_test_shared_secret_eu');
	//Save Changes
	var saveChangesButton = $('.button-primary, .woocommerce-save-button');

	function checkEmptyFields(){
		if (testCheckBox.prop('checked')) {
			if(location==='EU') {
				saveChangesButton.prop('disabled', true);
				if(!EUmerchantIdField.val() && !EUmerchantPasswordField.val()) {
					alert('Please enter valid Test Merchant information');
					EUmerchantIdField.focus();
				} else if (!EUmerchantPasswordField.val()) {
					alert('Please enter a valid Test Merchant Password');
					EUmerchantPasswordField.focus();
				} else if (!EUmerchantIdField.val()) {
					alert('Please enter a valid Test Merchant ID');
					EUmerchantIdField.focus();
				}else {
					saveChangesButton.prop('disabled', false);
				}
			} else if (location==='US') {
				saveChangesButton.prop('disabled', true);
				if(!USmerchantIdField.val() && !USmerchantPasswordField.val()) {
					alert('Please enter valid Test Merchant information');
					USmerchantIdField.focus();
				} else if (!USmerchantPasswordField.val()) {
					alert('Please enter a valid Test Merchant Password');
					USmerchantPasswordField.focus();
				} else if (!USmerchantIdField.val()) {
					alert('Please enter a valid Test Merchant ID');
					USmerchantIdField.focus();
				}else {
					saveChangesButton.prop('disabled', false);
				}
			}
		} else {
			saveChangesButton.prop('disabled', false);
		}
	}
	
	testCheckBox.click(function() {
		checkEmptyFields();
	})

	$('body').on('change', credentialsFields, testCredential);

	$('.woocommerce-log .view-log').on('click', function () {
		const pathname = $(this).siblings('select').val();
		if (pathname.length === 0) {
			return;
		}

		const textarea = $(this).siblings('textarea');
		if (! textarea.is(':hidden')) {
			return;
		}

		$.ajax({
			type: 'GET',
			url: kco_admin_params.wc_get_log,
			data: {
				filename: pathname,
				nonce: kco_admin_params.wc_get_log_nonce,
			},
			dataType: 'JSON',
			cache: true,
			success: function (response) {
				console.log(response)
				textarea.val(response.data);
			},
			error: function (response) {
				console.error(response);
			}
		})

	});

	$(document).ready(function () {
			$('#system-report').val(function () {
			/* Refer to "wp-content/plugins/woocommerce/assets/js/admin/system-status.js:generateReport()" */
			let report = '';
			$('.wc_status_table thead, .wc_status_table tbody').each(function () {
				if ($(this).is('thead')) {
					var label = $(this).find('th:eq(0)').data('exportLabel') || $(this).text();
					report = report + '\n### ' + label.trim() + ' ###\n\n';
				} else {
					$('tr', $(this)).each(function () {
						var label = $(this).find('td:eq(0)').data('exportLabel') || $(this).find('td:eq(0)').text();
						var the_name = label.trim().replace(/(<([^>]+)>)/ig, ''); // Remove HTML.
						// Find value
						var $value_html = $(this).find('td:eq(2)').clone();
						$value_html.find('.private').remove();
						$value_html.find('.dashicons-yes').replaceWith('&#10004;');
						$value_html.find('.dashicons-no-alt, .dashicons-warning').replaceWith('&#10060;');
						// Format value
						var the_value = $value_html.text().trim();
						var value_array = the_value.split(', ');
						if (value_array.length > 1) {
							// If value have a list of plugins ','.
							// Split to add new line.
							var temp_line = '';
							$.each(value_array, function (key, line) {
								temp_line = temp_line + line + '\n';
							});
							the_value = temp_line;
						}
						report = report + '' + the_name + ': ' + the_value + '\n';
					});
				}

			})
			
			return report;
		})
		
		$('.system-report-wrapper a').on('click', function () { 
			$('.system-report-content').val($('#system-report').val());
		});

		$('#kco-support input.button').on('click', function () {
			window.onbeforeunload = null;
		});

		$('.system-report-action').click(function (e) {
			$('.system-report-content').toggle({ duration: 250 });
			if ($(this).text() === 'View report') {
				$(this).text('Hide report');
			} else {
				$(this).text('View report');
			}
			e.preventDefault();
		})

		$('.view-log').click(function (e) {
			$(this).siblings('.log-content').toggle({ duration: 250 });
			if ($(this).text() === 'View log') {
				$(this).text('Hide log');
			} else {
				$(this).text('View log');
			}
			e.preventDefault();
		})

		for (let i = 1; i <= 5; i++) {
			$('select.additional-log-' + i).removeAttr('name');
		}

		$('a.additional-log').click(function (e) {
			$(this).hide();
			$(this).siblings('.additional-log-1').show();
			$('select.additional-log-1').attr('name', 'additional-log-1');

			for (let i = 1; i < 5; i++) {
				$(this).siblings('a.additional-log-' + i).click(function (e) {
					/* Show the <div> and the next <a>: */
					const siblings = $(this).siblings('.additional-log-' + (i + 1));
					siblings.show();

					const div = siblings.first('div');
					div.children('select').first().attr('name', 'additional-log-' + (i + 1));
					div.css('display', 'flex');

					$(this).hide();
					e.preventDefault();
				});
			}
		})

		$('.kco-addon-card-action a').click(function (e) {
			const target = $(this);
			
			target.removeClass('failed');
			target.addClass('loading');

			$.ajax({
				type: 'POST',
				url: kco_admin_params.change_addon_status,
				data: {
					plugin: $(this).data('pluginName'),
					plugin_url: $(this).data('pluginUrl'),
					action: $(this).data('action'),
					nonce: kco_admin_params.change_addon_status_nonce,
				},
				success: function (response) {
					target.removeClass('loading');
					if (response.success) {
						if ('activated' === response.data) {
							target.data('action', 'active');
							target.text('Active');
							target.addClass('button button-disabled');
						}

						if ( 'installed' === response.data ) {
							target.data('action', 'activate');
							target.text('Activate');
							target.addClass('install-now button');
						}
					} else {
						target.text('Error');
						target.addClass('failed');

						console.error(response);
					}
				},
				error: function (response) {
					target.text('Error');
					target.removeClass('loading');
					target.addClass('failed');

					console.error(response);
				},
			})

			e.preventDefault();
		});
	});
});





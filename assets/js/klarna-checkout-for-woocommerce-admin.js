jQuery( function($) {
	'use strict';

	var titles = $('h3.wc-settings-sub-title');
	var tables = $('h3.wc-settings-sub-title + table.form-table');
	var submit = $('.wrap.woocommerce p.submit');

	titles.append(' <a href="#" style="font-size:12px; font-weight: normal; text-decoration: none">[expand]</a>');
	tables.css('marginLeft', '20px').hide();
	titles.find('a').addClass('collapsed');
	titles.find('a').click(function(e) {
		e.preventDefault();

		if ($(this).hasClass('collapsed')) {
			$(this).parent().next().show();
			$(this).removeClass('collapsed');
			$(this).text('[collapse]');
		} else {
			$(this).parent().next().hide();
			$(this).addClass('collapsed');
			$(this).text('[expand]');
		}
	});

	titles.before('<hr style="margin-top:2em;margin-bottom:2em" />');
	submit.before('<hr style="margin-top:2em;margin-bottom:2em" />');
});

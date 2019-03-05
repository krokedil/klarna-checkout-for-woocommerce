jQuery( function($) {
	'use strict';
	$('.checkout-addon-action .button').click(function(){
		var pluginStatus = $(this).attr('data-status');
		var pluginAction = $(this).attr('data-action');
		var pluginSlug = $(this).attr('data-plugin-slug');
		console.log('status start');
		console.log(pluginStatus);
		console.log(pluginAction);
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'change_klarna_addon_status',
				plugin_status: pluginStatus,
				plugin_action: pluginAction,
				plugin_slug: pluginSlug,
				nonce: 'deactivate_klarna_addon_nonce'
			},
			dataType: 'json',
			success: function(data) {
			},
			error: function(data) {
			},
			complete: function(data) {
				console.log('complete');
				console.log(data);
				console.log(data.responseJSON.data.new_status);
				$('.checkout-addon .button').attr('data-status', data.responseJSON.data.new_status );
				$('.checkout-addon .button').attr('data-action', data.responseJSON.data.new_action );
				
				console.log('ny status');
				console.log($('.checkout-addon-action .button').attr('data-status'));
			}
		});
						
	});

});
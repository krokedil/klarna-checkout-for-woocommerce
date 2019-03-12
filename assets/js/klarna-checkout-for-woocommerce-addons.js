jQuery( function($) {
	'use strict';
	$('.checkout-addon-action .button').click(function(){
		var pluginStatus = $(this).attr('data-status');
		var pluginAction = $(this).attr('data-action');
		var pluginSlug = $(this).attr('data-plugin-slug');
		var element = this;
		console.log('status start');
		console.log(pluginStatus);
		console.log(pluginAction);
		console.log(pluginSlug);
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
				console.log('success');
				console.log(data);
			},
			error: function(data) {
				console.log('error');
				console.log(data);
			},
			complete: function(data) {
				
				console.log('complete');
				console.log(data);
				if( true === data.responseJSON.success ) {
					// Change the status.
					$( '.checkout-addon.' + pluginSlug + ' .button' ).attr('data-status', data.responseJSON.data.new_status );
					$( '.checkout-addon.' + pluginSlug + ' .button' ).attr('data-action', data.responseJSON.data.new_action );
					$('.checkout-addon-status[data-plugin-slug="' + pluginSlug + '"] .status-text').text( data.responseJSON.data.new_status_label );
					$('[data-plugin-slug="' + pluginSlug + '"] .action-text').text( data.responseJSON.data.new_action_label );

					if( 'installed' === data.responseJSON.data.new_status ) {
						$( '.checkout-addon.' + pluginSlug + ' .button .switch' ).removeClass('download');
						$( '.checkout-addon.' + pluginSlug + ' .button .switch .round' ).removeClass('dashicons').removeClass('dashicons-download').addClass('slider');
					}
				} else {
					
					$( '.checkout-addon.' + pluginSlug ).append( '<p>' + data.responseJSON.error + '</p>' );
				}
				
				
			}
		});
						
	});

});
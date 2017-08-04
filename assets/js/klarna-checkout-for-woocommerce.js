jQuery(function($) {
	var kco_wc;
	kco_wc = {
		bodyEl: $('body'),

		// Order notes
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),
		loadOrderNotesValue: function() {
			if (kco_wc.orderNotesEl.length > 0) {
				kco_wc.orderNotesValue = kco_wc.orderNotesEl.val();
			}
		},

		kcoSuspend: function () {
			window._klarnaCheckout(function (api) {
				api.suspend();
			});
		},

		kcoResume: function () {
			window._klarnaCheckout(function (api) {
				api.resume();
			});
		},

		update: function () {
			kco_wc.kcoSuspend();
			$('body').trigger('update_checkout');

			$.ajax({
				type: 'POST',
				url: '/checkout/?wc-ajax=kco_wc_update_cart',
				data: { checkout: $('form.checkout').serialize() },
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					kco_wc.kcoResume();
				}
			});
		},

		updateOrderNotes: function() {
			console.log(kco_wc.orderNotesEl.val());
			console.log(kco_wc.orderNotesValue);

			if (kco_wc.orderNotesEl.val() !== kco_wc.orderNotesValue) {
				kco_wc.orderNotesValue = kco_wc.orderNotesEl.val();

				$.ajax({
					type: 'POST',
					url: '/checkout/?wc-ajax=kco_wc_update_order_notes',
					data: { order_notes: kco_wc.orderNotesValue },
					success: function (data) {
					},
					error: function (data) {
					},
					complete: function (data) {
						console.log('complete')
					}
				});
			}
		},

		init: function () {
			kco_wc.bodyEl.on('change', 'input.qty, input.shipping_method', kco_wc.update);
			kco_wc.bodyEl.on('blur', kco_wc.orderNotesSelector, kco_wc.updateOrderNotes);

			window._klarnaCheckout(function(api) {
				api.on({
					'change': function(data) {
						console.log('change', data);
					}
				});
			});
			window._klarnaCheckout(function(api) {
				api.on({
					'shipping_address_change': function(data) {
						console.log('shipping_address_change', data);
					}
				});
			});
			window._klarnaCheckout(function(api) {
				api.on({
					'order_total_change': function(data) {
						console.log('order_total_change', data);
					}
				});
			});
			window._klarnaCheckout(function(api) {
				api.on({
					'shipping_option_change': function(data) {
						console.log('shipping_option_change', data);
					}
				});
			});
			window._klarnaCheckout(function(api) {
				api.on({
					'can_not_complete_order': function(data) {
						console.log('can_not_complete_order', data);
					}
				});
			});
		}
	};
	kco_wc.init();
});

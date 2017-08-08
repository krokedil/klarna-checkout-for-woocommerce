jQuery(function($) {
	var kco_wc;
	kco_wc = {
		bodyEl: $('body'),
		checkoutFormEl: $('form.checkout'),

		// Order notes
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#klarna-checkout-select-other',

		documentReady: function() {
			if (kco_wc.paymentMethodEl.length > 0) {
				kco_wc.paymentMethod = kco_wc.paymentMethodEl.filter(':checked').val();
			} else {
				kco_wc.paymentMethod = 'klarna_checkout_for_woocommerce';
			}
			console.log(kco_wc.paymentMethod);
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
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						console.log('complete', data);
					}
				});
			}
		},

		refreshCheckoutFragment: function(e) {
			e.preventDefault();

			kco_wc.checkoutFormEl.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				url: '/checkout/?wc-ajax=kco_wc_refresh_checkout_fragment',
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					console.log(data.responseJSON);
					kco_wc.checkoutFormEl.replaceWith(data.responseJSON.data.fragments.checkout);
					kco_wc.checkoutFormEl.unblock();
				}
			});
		},

		init: function () {
			$(document).ready(kco_wc.documentReady);
			// kco_wc.bodyEl.on('updated_checkout', kco_wc.documentReady);

			kco_wc.bodyEl.on('change', 'input.qty, input.shipping_method', kco_wc.update);
			kco_wc.bodyEl.on('blur', kco_wc.orderNotesSelector, kco_wc.updateOrderNotes);
			// kco_wc.bodyEl.on('change', 'input.payment_method', kco_wc.refreshFragments);
			kco_wc.bodyEl.on('click', kco_wc.selectAnotherSelector, kco_wc.refreshCheckoutFragment);

			/*
			window._klarnaCheckout(function(api) {
				api.on({
					'change': function(data) {},
					'shipping_address_change': function(data) {},
					'order_total_change': function(data) {},
					'shipping_option_change': function(data) {},
					'can_not_complete_order': function(data) {}
				});
			});
			*/
		}
	};
	kco_wc.init();
});

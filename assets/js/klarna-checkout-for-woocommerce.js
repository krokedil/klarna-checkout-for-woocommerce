jQuery(function($) {
	var kco_wc;
	kco_wc = {
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
				url: '/checkout/?wc-ajax=kco_wc_update_quantity',
				data: $('form.checkout').serialize(),
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					kco_wc.kcoResume();
				}
			});
		},

		init: function () {
			$('body').on('change', 'input.qty, input.shipping_method', this.update);
		}
	};
	kco_wc.init();



    var klarna_checkout_for_woocommerce = {
        init: function() {
            $('body').on('change', 'input[name="payment_method"]', function() {
                klarna_checkout_for_woocommerce.update_to_klarna_checkout();
            });
            $(
                '#klarna-checkout-select-other'
            ).on('click', this.update_from_klarna_checkout);
        },
        update_to_klarna_checkout: function() {
            console.log(
                'updating checkout',
                $('input[name="payment_method"]:checked').val()
            );

            // Check if switching to of from Klarna Checkout.
            if (
                'klarna_checkout_for_woocommerce' ===
                    $('input[name="payment_method"]:checked').val()
            ) {
                // $('body').trigger('update_checkout');
                window.location.href = '/checkout/kco';
            }
        },
        update_from_klarna_checkout: function(e) {
            e.preventDefault();

            /*
			$.ajax({
				type: 'POST',
				url: '/checkout/?wc-ajax=kco_ajax_event',
				success: function (data) {}
			});
			*/
            window.location.href = '/checkout';
            // $('body').trigger('update_checkout');
        }
    };

    klarna_checkout_for_woocommerce.init();
});

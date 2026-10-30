/* global jQuery, kcoUpsellParams */
/**
 * Refreshes the order details table on the thank-you page when the customer adds an upsell in the Kustom confirmation iframe, or Kustom fails to add one.
 * The refresh also asks the server to add the upsell to the order, or to drop it when it failed, which the server checks against Kustom's order.
 * The upsell events are not in Kustom's public event docs, so a reload is the fallback if they stop firing.
 */
jQuery(function ($) {
	if (
		'function' !== typeof window._klarnaCheckout ||
		'undefined' === typeof kcoUpsellParams
	) {
		return;
	}

	const kcoUpsell = {
		tableSelector: '.woocommerce-order-details',
		pendingSelector: '.kco-upsell-pending',
		maxAttempts: 5,
		retryDelay: 1500,

		/**
		 * Listens to the upsell events from the Kustom iframe.
		 */
		init() {
			window._klarnaCheckout(function (api) {
				api.on({
					upsell_product_added() {
						kcoUpsell.refresh(false, 1);
					},
					upsell_add_failed() {
						kcoUpsell.refresh(true, 1);
					},
				});
			});
		},

		/**
		 * Asks the server to update the pending upsells, and replaces the order details table with the result.
		 *
		 * @param {boolean} failed  Whether Kustom failed to add the upsell.
		 * @param {number}  attempt The attempt number, starting at 1.
		 */
		refresh(failed, attempt) {
			$.ajax({
				url: kcoUpsellParams.url,
				type: 'POST',
				data: {
					order_id: kcoUpsellParams.order_id,
					key: kcoUpsellParams.order_key,
					failed: failed ? 1 : 0,
				},
				success(response) {
					if (!response.success) {
						return;
					}

					const fresh = $('<div>')
						.append($.parseHTML(response.data.html))
						.find(kcoUpsell.tableSelector);
					if (!fresh.length) {
						return;
					}

					$(kcoUpsell.tableSelector).replaceWith(fresh);

					// Another request may hold the lock, so try again shortly while upsells are still pending.
					if (
						fresh.find(kcoUpsell.pendingSelector).length &&
						attempt < kcoUpsell.maxAttempts
					) {
						setTimeout(
							() => kcoUpsell.refresh(failed, attempt + 1),
							kcoUpsell.retryDelay
						);
					}
				},
			});
		},
	};

	kcoUpsell.init();
});

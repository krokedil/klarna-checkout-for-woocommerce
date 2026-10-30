/* global kcoUpsellParams, DOMParser */
/**
 * Refreshes the order details table on the thank-you page when the customer adds an upsell in the Kustom confirmation iframe.
 * The refresh also asks the server to add the upsell to the order, which it only does once the upsell is in Kustom's order.
 * upsell_product_added is not in Kustom's public event docs, so a reload is the fallback if it stops firing.
 */
(function () {
	if (
		'function' !== typeof window._klarnaCheckout ||
		'undefined' === typeof kcoUpsellParams
	) {
		return;
	}

	const selector = '.woocommerce-order-details';
	const maxAttempts = 5;
	const countRows = (root) =>
		root.querySelectorAll(`${selector} tbody tr`).length;

	const refresh = (rowsBefore, attempt) => {
		const body = new URLSearchParams({
			order_id: kcoUpsellParams.order_id,
			key: kcoUpsellParams.order_key,
		});

		fetch(kcoUpsellParams.url, {
			method: 'POST',
			body,
			credentials: 'same-origin',
		})
			.then((response) => response.json())
			.then((response) => {
				if (!response.success) {
					return;
				}

				const fresh = new DOMParser().parseFromString(
					response.data.html,
					'text/html'
				);
				const stored = countRows(fresh) > rowsBefore;
				const current = document.querySelector(selector);
				const replacement = fresh.querySelector(selector);
				if (stored && current && replacement) {
					current.replaceWith(replacement);
				}

				// Kustom may send the event before the upsell is stored, or before it is in Kustom's order so it can be added, so try again shortly.
				const waiting =
					!stored || fresh.querySelector('.kco-upsell-pending');
				if (waiting && attempt < maxAttempts) {
					setTimeout(() => refresh(rowsBefore, attempt + 1), 1500);
				}
			})
			.catch(() => {});
	};

	window._klarnaCheckout(function (api) {
		api.on({
			upsell_product_added() {
				refresh(countRows(document), 1);
			},
		});
	});
})();

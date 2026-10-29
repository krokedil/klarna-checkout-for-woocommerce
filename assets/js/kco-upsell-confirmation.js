/* global kcoUpsellParams, DOMParser */
/**
 * Refreshes the order details table on the thank-you page when the customer adds an upsell in the Kustom confirmation iframe.
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
	const maxAttempts = 4;
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
				// Kustom may send the event before our upsell validation callback has stored the line, so try again shortly.
				if (countRows(fresh) <= rowsBefore && attempt < maxAttempts) {
					setTimeout(() => refresh(rowsBefore, attempt + 1), 1500);
					return;
				}

				const current = document.querySelector(selector);
				const replacement = fresh.querySelector(selector);
				if (current && replacement) {
					current.replaceWith(replacement);
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

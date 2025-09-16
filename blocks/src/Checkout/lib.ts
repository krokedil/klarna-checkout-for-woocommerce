/**
 * Returns a list of elements to hide in the WooCommerce checkout page that are not relevant for the Kustom Checkout iframe.
 *
 * @param {boolean} shippingInIframe - Whether the shipping fields are displayed in the iframe.
 * @return {string[]} - An array of CSS selectors for elements to hide.
 */
export const getElementsToHide = (shippingInIframe: boolean): string[] => {
	return [
		'#shipping-fields', // Hide shipping fields since they are handled in the iframe.
		'#billing-fields', // Hide billing fields since they are handled in the iframe.
		'#contact-fields', // Hide contact fields since they are handled in the iframe.
		'.wc-block-components-checkout-place-order-button', // Hide the place order button since it is handled in the iframe.
		'.wp-block-woocommerce-checkout-terms-block', // Hide the terms and conditions block since it is handled in the iframe.
		shippingInIframe ? '#shipping-option' : '', // Hide shipping option if shipping is in the iframe.
	];
};

/**
 * Hide the elements specified by the selectors in the WooCommerce checkout page.
 * Used when the customer selects Kustom Checkout as the payment method.
 *
 * @param {string[]} toHide - An array of CSS selectors for elements to hide.
 * @return {void}
 */
export const hideElements = (toHide: string[]): void => {
	// Hide the parts we don't want to show.
	toHide.forEach((selector) => {
		if (selector === '') return; // Skip empty selectors.

		// Find the element and set its style to display none to hide it.
		const element = document.querySelector(selector);
		if (element) {
			element.setAttribute('style', 'display: none;');
		}
	});
};

/**
 * Show the elements specified by the selectors in the WooCommerce checkout page.
 * Used when the customer selects a different payment method then Kustom Checkout.
 *
 * @param {string[]} toShow - An array of CSS selectors for elements to show.
 * @return {void}
 */
export const showElements = (toShow: string[]): void => {
	// Show the hidden parts.
	toShow.forEach((selector) => {
		if (selector === '') return; // Skip empty selectors.

		// Find the element and set its style to display block to restore visibility.
		const element = document.querySelector(selector);
		if (element) {
			element.setAttribute('style', 'display: block;');
		}
	});
};

/**
 * Add the iframe with the Kustom Checkout HTML content to the WooCommerce checkout page.
 * This function creates a wrapper div, and set the inner HTML to the provided content,
 * then appends it to the payment methods block.
 *
 * @param {string} htmlContent - The HTML content to be added as an iframe.
 * @return {HTMLDivElement} - The wrapper div containing the iframe.
 */
export const addIframe = (htmlContent: string): HTMLDivElement => {
	// Create a new div for the htmlContent, and set it as the inner HTML.
	const kcoWrapper = document.createElement('div');
	kcoWrapper.className =
		'wc-block-checkout__klarna-checkout wc-block-components-klarna-checkout-block';
	kcoWrapper.innerHTML = htmlContent;

	// Get the payment methods block in the WooCommerce checkout page.
	const paymentMethodsBlock = document.querySelector(
		'.wc-block-checkout__payment-method'
	);

	// If the payment methods block is found, append the kcoWrapper to it.
	if (paymentMethodsBlock) {
		paymentMethodsBlock.after(kcoWrapper);
	}

	// Return the wrapper div containing the Kustom Checkout iframe so it can be used later for removal.
	return kcoWrapper;
};

/**
 * Remove the Kustom Checkout iframe from the WooCommerce checkout page.
 * Needs to be done when the customer selects a different payment method.
 *
 * @param {HTMLDivElement} kcoWrapper - The wrapper div containing the Kustom Checkout iframe.
 * @return {void} - Removes the Kustom Checkout iframe from the WooCommerce checkout page
 */
export const removeIframe = (kcoWrapper: HTMLDivElement): void => {
	kcoWrapper.remove();
};

/**
 *
 * @param page
 * @param selector
 * @returns {Promise<void>}
 */
const selectPayment = async (page, selector) => {
	if (await page.$(selector)) {
		await page.evaluate(
			(paymentMethod) => paymentMethod.click(),
			await page.$(selector)
		);
	}
};

/**
 *
 * @param page
 * @param selector
 * @returns {Promise<void>}
 */
const acceptTerms = async (page, selector) => {
	await page.waitForSelector(selector);
	await page.evaluate((cb) => cb.click(), await page.$(selector));
};

export default {
	acceptTerms,
	selectPayment,
};

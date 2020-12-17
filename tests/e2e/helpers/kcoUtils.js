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

/**
 * Check for selector - continue if false
 */
const expectSelector = async (frame, page, selector, time) => {
	try {
		await page.waitForTimeout(time);
		await frame.waitForSelector(selector);

		await frame.click(selector);
	} catch {
		console.log("Proceed from expectation");
	}
};

/**
 * Check for input - continue if false
 */
const expectInput = async (frame, page, inputValue, selector, time) => {
	try {
		await page.waitForTimeout(time);
		await frame.waitForSelector(selector);
		await frame.type(selector, inputValue);
	} catch {
		console.log("Proceed from expectation");
	}
};

export default {
	acceptTerms,
	selectPayment,
	expectSelector,
	expectInput,
};

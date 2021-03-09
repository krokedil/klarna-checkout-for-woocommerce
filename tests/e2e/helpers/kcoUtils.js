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
		await page.waitForTimeout(2 * time);
		await frame.click(selector);
	} catch {
	}
};

/**
 * Check for input - continue if false
 */
const expectInput = async (frame, page, inputValue, selector, time) => {
	try {
		await page.waitForTimeout(2 * time);
		await frame.type(selector, inputValue);
	} catch {
	}
};

/**
 * Add coupons
 */
const addCouponsOnCheckout = async (page, appliedCoupons) => {
	await page.waitForTimeout(1000);
	await page.$eval('[id="wpadminbar"]', (adminBar) =>
		adminBar.setAttribute("style", "display:none")
	);

	if (appliedCoupons.length > 0) {
		await appliedCoupons.forEach(async (singleCoupon) => {
			await page.waitForTimeout(1000);
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(1000);
			await page.type('[name="coupon_code"]', singleCoupon);
			await page.waitForTimeout(1000);
			await page.click('[name="apply_coupon"]');
			await page.waitForTimeout(1000);
		});
	}
};

/**
 * Export data
 */
export default {
	acceptTerms,
	selectPayment,
	expectSelector,
	expectInput,
	addCouponsOnCheckout,
};

import API from "../api/API";
import {KCOSettingsArray} from "../config/config";

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
		// Proceed
	}
};

/**
 *
 * Check for input - continue if flase
 *
 * @param {*} frame
 * @param {*} page
 * @param {*} inputValue
 * @param {*} selector
 * @param {*} time
 */
const expectInput = async (frame, page, inputValue, selector, time) => {
	try {
		await page.waitForTimeout(2 * time);
		await frame.type(selector, inputValue);
	} catch {
		// Proceed
	}
};

/**
 * Add coupons
 *
 * @param {*} page
 * @param {*} isUserLoggedIn
 * @param {*} appliedCoupons
 */
const addCouponsOnCheckout = async (page, isUserLoggedIn, appliedCoupons) => {
	await page.waitForTimeout(1000);

	if (isUserLoggedIn) {
		await page.$eval('[id="wpadminbar"]', (adminBar) =>
			adminBar.setAttribute("style", "display:none")
		);
	}

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
 * Select shipping method
 *
 * @param {*} page
 * @param {*} frame
 * @param {*} iframeShipping
 * @param {*} shippingMethod
 * @param {*} freeShippingMethodTarget
 * @param {*} flatRateMethodTarget
 * @param {*} timeOutTime
 */
const chooseKlarnaShippingMethod = async (
	page,
	frame,
	iframeShipping,
	shippingMethod,
	freeShippingMethodTarget,
	flatRateMethodTarget,
	timeOutTime
) => {
	let shippingMethodTarget;

	if (iframeShipping !== "yes") {
		if (shippingMethod === "free") {
			shippingMethodTarget = `[id*="${freeShippingMethodTarget}"]`;
		} else if (shippingMethod === "flat") {
			shippingMethodTarget = `[id*="${flatRateMethodTarget}"]`;
		}

		if (shippingMethod !== "") {
			await page.waitForTimeout(timeOutTime);
			await page.waitForSelector(shippingMethodTarget).id;
			await page.waitForTimeout(timeOutTime);
			await page.click(shippingMethodTarget).id;
			await page.waitForTimeout(timeOutTime);
		}
	} else {
		await page.waitForTimeout(timeOutTime);
		const iframeShippingMethod = shippingMethod;

		const frameShippingTab = await frame.$$(
			'[data-cid="SHIPMO-shipping-option-basic"]'
		);

		if (iframeShippingMethod === "flat") {
			await frameShippingTab[0].click();
		} else if (iframeShippingMethod === "free") {
			await frameShippingTab[1].click();
		}
	}
};

/**
 * Select shipping method through KCO iFrame or standard WC checkout selection
 */
const toggleIFrame = async (toggleSwitch) => {
	KCOSettingsArray.woocommerce_kco_settings.shipping_methods_in_iframe = toggleSwitch
	await API.updateOptions(KCOSettingsArray);
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
	chooseKlarnaShippingMethod,
	toggleIFrame,
};

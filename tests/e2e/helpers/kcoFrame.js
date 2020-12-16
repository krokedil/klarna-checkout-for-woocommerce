/**
 *
 * @param page
 * @param name
 * @returns {Promise<*|Frame>}
 */
const loadIFrame = async (page, name) => {
	const selector = `iframe[name="${name}"]`;
	await page.waitForSelector(selector);
	const elementHandle = await page.$(selector);
	const frame = await elementHandle.contentFrame();
	return frame;
};

/**
 *
 * @param frame
 * @param data
 * @returns {Promise<void>}
 */
const submitBillingForm = async (frame, data) => {
	const {
		emailSelector,
		email,
		postalCodeSelector,
		postalCode,
		submitSelector,
	} = data;
	if (await frame.$("#billing-email")) {
		await frame.type(emailSelector, String(email));
		await frame.type(postalCodeSelector, String(postalCode));
		await frame.click(submitSelector);
	}
};

/**
 *
 * @param frame
 * @param selector
 * @returns {Promise<void>}
 */
const payLater = async (frame, selector) => {
	await frame.waitForSelector(selector);
	await frame.click(selector);
};

/**
 *
 * @param frame
 * @param selector
 * @returns {Promise<void>}
 */
const payNow = async (frame, selector) => {
	await frame.waitForSelector(selector);
	await frame.click(selector);
};

/**
 *
 * @param frame
 * @param selector
 * @returns {Promise<void>}
 */
const payALittle = async (frame, selector) => {
	await frame.waitForSelector(selector);
	await frame.click(selector);
};

/**
 *
 * @param frame
 * @param buttonSelector
 * @returns {*}
 */
const createOrder = async (frame, buttonSelector) => {
	await frame.waitForSelector(buttonSelector);
	await frame.click(buttonSelector);
};

export default {
	loadIFrame,
	submitBillingForm,
	payLater,
	payALittle,
	createOrder,
	payNow,
};

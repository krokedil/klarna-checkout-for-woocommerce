/**
 *
 * @param userCredentials
 * @param options
 * @param selectors
 * @returns {Promise<void>}
 */
const login = async (userCredentials, options, selectors) => {
	const { username, password } = userCredentials;
	const { page } = options;
	await page.type(selectors.username, username);
	await page.type(selectors.password, password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

export default {
	login,
};

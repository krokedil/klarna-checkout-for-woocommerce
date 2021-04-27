/**
 *
 * @param userCredentials
 * @param options
 * @returns {Promise<void>}
 */
const login = async (userCredentials, options) => {
	const {
		username,
		password,
		selectorForName,
		selectorForPass,
	} = userCredentials;
	const { page } = options;

	await page.type(selectorForName, username);
	await page.type(selectorForPass, password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

export default {
	login,
};

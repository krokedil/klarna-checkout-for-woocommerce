/**
 * Helper functions for adding a product to the cart.
 */

/**
 * Adds one product to the cart.
 *
 * @param page
 * @param productId
 * @returns {Promise<void>}
 */
const addSingleProductToCart = async (page, productId) => {
	const productSelector = `a[href="?add-to-cart=${productId}"]`;
	await page.waitForSelector(productSelector);
	await page.evaluate(
		(selector) => document.querySelector(selector).click(),
		productSelector
	);
};

/**
 * Adds multiple products to the cart.
 *
 * @param page
 * @param products
 * @returns {Promise<void>}
 */
const addMultipleProductsToCart = async (page, products) =>
	products.map(async (productId) => {
		await addSingleProductToCart(page, productId);
	});

export default {
	addSingleProductToCart,
	addMultipleProductsToCart,
};

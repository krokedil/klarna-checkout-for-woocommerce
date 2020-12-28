/**
 * Adds one product to the cart.
 *
 * @param page
 * @param productId
 * @returns {Promise<void>}
 */
const addSingleProductToCart = async (page, productId) => {
	const productSelector = `a[href="?add-to-cart=${productId}"]`;
	try {
		await page.click(productSelector);
	} catch {
		console.log("Proceed from expectation");
	}
};

/**
 * Adds multiple products to the cart.
 *
 * @param page
 * @param products
 * @returns {Promise<void>}
 */
const addMultipleProductsToCart = async (page, products) => {
	products.forEach(async (product) => {
		await addSingleProductToCart(page, product);
	});
	await page.waitForTimeout(2000);
};

export default {
	addSingleProductToCart,
	addMultipleProductsToCart,
};

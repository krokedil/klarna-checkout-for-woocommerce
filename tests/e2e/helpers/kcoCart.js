/**
 * Adds one product to the cart.
 *
 * @param page
 * @param productId
 * @returns {Promise<void>}
 */
const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(
			`http://localhost:8000/shop/?add-to-cart=${productSelector}`
		);
		await page.goto("http://localhost:8000/shop/");
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
	const timer = products.length;

	(async function addEachProduct() {
		for (let i = 0; i < products.length + 1; i += 1) {
			await addSingleProductToCart(page, products[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

export default {
	addSingleProductToCart,
	addMultipleProductsToCart,
};

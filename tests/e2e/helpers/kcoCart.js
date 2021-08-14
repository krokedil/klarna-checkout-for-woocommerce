/**
 * Adds one product to the cart.
 *
 * @param page
 * @param productId
 * @returns {Promise<void>}
 */

import kcoURLS from "./kcoURLS";

/**
 *
 * Adds single product to cart.
 *
 * @param page
 * @param productId
 */
const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${kcoURLS.ADD_TO_CART}${productSelector}`);
		await page.goto(kcoURLS.SHOP);
	} catch {
		// Proceed
	}
};

/**
 * Adds multiple products to the cart.
 *
 * @param page
 * @param products
 * @returns {Promise<void>}
 */
const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 800);
	let ids = [];

	products.forEach( name => {
		data.products.simple.forEach(product => {
			if(name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach(product => {
			product.attribute.options.forEach(variation => {
				if(name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	(async function addEachProduct() {
		for (let i = 0; i < ids.length + 1; i += 1) {
			await addSingleProductToCart(page, ids[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

export default {
	addSingleProductToCart,
	addMultipleProductsToCart,
};

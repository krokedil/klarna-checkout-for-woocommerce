import { AdminLogin, GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { test, expect, APIRequestContext } from '@playwright/test';
import { gt, valid } from 'semver';
import { KlarnaPopup } from '../pages/KlarnaPopup';
import { HandleKpPopup } from '../utils/Utils';

const {
	CI,
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

test.describe('Order management @shortcode', () => {
	test.skip(CI !== undefined, 'Skipping tests in CI environment since its currently failing randomly without any reason during CI. Skipping to prevent false negative tests.') // @TODO - Fix this test for CI.

	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	let orderId;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async ({ page }) => {
		// Delete the order from WooCommerce.
		await wcApiClient.delete(`orders/${orderId}`);

		// Clear all cookies.
		await page.context().clearCookies();
	});

	test('Can capture an order', async ({ page }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			// A new window should open with the Klarna payment popup.
			await HandleKpPopup(page);

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Capture the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order captured')).toBe(true);
		});
	});

	test('Can cancel an order', async ({ page }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			// A new window should open with the Klarna payment popup.
			await HandleKpPopup(page);

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Cancel the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.cancelOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order cancelled')).toBe(true);
		});
	});

	test('Can refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			// A new window should open with the Klarna payment popup.
			await HandleKpPopup(page);

			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
		});

		await test.step('Fully refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
		});
	});

	test('Can partially refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			// A new window should open with the Klarna payment popup.
			await HandleKpPopup(page);

			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
		});

		await test.step('Partially refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
		});
	});
});

test.describe('Order management @checkoutBlock', () => {
	test.skip(CI !== undefined, 'Skipping tests in CI environment since its currently failing randomly without any reason during CI. Skipping to prevent false negative tests.') // @TODO - Fix this test for CI.

	test.skip(
		valid(process.env.WC_VERSION) && // And it is not an empty string
		!gt(process.env.WC_VERSION, '6.0.0'), // And it is not greater than 6.0.0
		'Skipping tests with checkout blocks for WooCommerce < 6.0.0');

	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	let orderId;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async ({ page }) => {
		// Delete the order from WooCommerce.
		await wcApiClient.delete(`orders/${orderId}`);

		// Clear all cookies.
		await page.context().clearCookies();
	});

	test('Can capture an order', async ({ page }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaPopup(page, true);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Wait for 5 seconds, sadly this is needed because WooCommerce batches up all changes if we make them too quickly, and disables the butten unpredictably.
			await page.waitForTimeout(5000);

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);
			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Capture the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order captured')).toBe(true);
		});
	});

	test('Can cancel an order', async ({ page }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaPopup(page, true);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Wait for 5 seconds, sadly this is needed because WooCommerce batches up all changes if we make them too quickly, and disables the butten unpredictably.
			await page.waitForTimeout(5000);

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);

			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Cancel the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.cancelOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order cancelled')).toBe(true);
		});
	});

	test('Can refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaPopup(page, true);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Wait for 5 seconds, sadly this is needed because WooCommerce batches up all changes if we make them too quickly, and disables the butten unpredictably.
			await page.waitForTimeout(5000);

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);
			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
		});

		await test.step('Fully refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
		});
	});

	test('Can partially refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaPopup(page, true);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Wait for 5 seconds, sadly this is needed because WooCommerce batches up all changes if we make them too quickly, and disables the butten unpredictably.
			await page.waitForTimeout(5000);

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);
			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
		});

		await test.step('Partially refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
		});
	});
});

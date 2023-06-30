import { test, expect, APIRequestContext } from '@playwright/test';
import { GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';
import { KlarnaPopup } from '../pages/KlarnaPopup';
import { gt, valid } from 'semver';
import { HandleKcPopup } from '../utils/Utils';

const {
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

test.describe('Guest Checkout @shortcode', () => {
	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	const paymentMethodId = 'klarna_checkout';

	let orderId: string;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async () => {
		// Delete the order from WooCommerce.
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
		await wcApiClient.delete(`orders/${orderId}`);
	});

	test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can buy products with different tax rates', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-12', 'simple-06', 'simple-00']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can buy products that don\'t require shipping', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-virtual-downloadable-25', 'simple-virtual-downloadable-12', 'simple-virtual-downloadable-06', 'simple-virtual-downloadable-00']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can buy variable products', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['variable-25-blue', 'variable-12-red', 'variable-12-red', 'variable-25-black', 'variable-12-black']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with separate shipping address', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Fill in the shipping address.
		await checkoutPage.fillShippingAddress();

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with Company name in both billing and shipping address', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress({ company: 'Test Company Billing' });

		// Fill in the shipping address.
		await checkoutPage.fillShippingAddress({ company: 'Test Company Shipping' });

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can change shipping method', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Change the shipping method.
		await checkoutPage.selectShippingMethod('Flat rate');

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.
		await HandleKcPopup(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with coupon 10%', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Apply coupon.
		await checkoutPage.applyCoupon('percent-10');

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with coupon fixed 10', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Apply coupon.
		await checkoutPage.applyCoupon('fixed-10');

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with coupon 100%', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Apply coupon.
		await checkoutPage.applyCoupon('fixed-10');

		// Place the order.
		await checkoutPage.placeOrder();

		// A new window should open with the Klarna payment popup.

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});
});

test.describe('Guest Checkout @checkoutBlock', () => {
	test.skip(
		valid(process.env.WC_VERSION) && // And it is not an empty string
		!gt(process.env.WC_VERSION, '6.0.0'), // And it is not greater than 6.0.0
		'Skipping guest checkout tests with checkout blocks for WooCommerce < 6.0.0');

	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	let orderId: string;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async () => {
		// Delete the order from WooCommerce.
		await wcApiClient.delete(`orders/${orderId}`);
	});

	test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
		const wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.CheckoutBlock(page);
		const klarnaHPP = new KlarnaPopup(page, true);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

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

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});
});

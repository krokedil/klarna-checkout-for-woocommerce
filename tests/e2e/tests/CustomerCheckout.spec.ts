import { GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { test, expect } from '@playwright/test';
import { APIRequestContext } from 'playwright-chromium';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';
import { HandleKcPopup, HandleKcIFrame } from '../utils/Utils';
import { KlarnaIFrame } from '../pages/KlarnaIFrame';

const {
	CI,
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

test.describe('Customer Checkout @shortcode', () => {
	//test.skip(CI !== undefined, 'Skipping tests in CI environment since its currently not working and giving a false negative.') // @TODO - Fix this test for CI.

	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	const paymentMethodId = 'klarna_checkout';

	let customerId;
	let username;
	let orderId;

	test.beforeEach(async ({ page }) => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');

		let randSuffix = Math.floor(Math.random() * 1000000);
		username = `testCustomer_${randSuffix}`;

		// Create a customer account.
		const customerResponse = await wcApiClient.post('customers', {
			data: {
				email: `testCustomer_${username}@krokedil.se`,
				first_name: 'Test',
				last_name: 'Customer',
				username: username,
				password: 'password',
				billing: {
					first_name: 'Test',
					last_name: 'Customer',
					address_1: 'Teststreet 1',
					address_2: '',
					city: 'TestCity',
					postcode: '12345',
					country: 'SE',
					email: 'testCustomer@krokedil.se',
					phone: '123456789',
				},
				shipping: {
					first_name: 'Test',
					last_name: 'Customer',
					address_1: 'Teststreet 1',
					address_2: '',
					city: 'TestCity',
					state: '',
					postcode: '12345',
					country: 'SE',
				}
			},
		});

		const customer = await customerResponse.json();
		customerId = customer.id;

		// Login as the customer.
		await page.goto('/my-account/');
		await page.fill('#username', username);
		await page.fill('#password', 'password');

		await Promise.all([
			page.waitForNavigation(),
			page.click('text=Log in'),
		]);
	});

	test.afterEach(async ({ page }) => {
		// Delete the customer account.
		await wcApiClient.delete(`customers/${customerId}`);

		// Delete the order.
		await wcApiClient.delete(`orders/${orderId}`);

		// Clear all cookies.
		await page.context().clearCookies();
	});

	test('Customer can checkout with Klarna Checkout with prefilled address', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();
		await KlarnaIFrame.WaitForCheckoutInitRequests(page);

		await HandleKcIFrame(page); // Handle the klarna Iframe
		await HandleKcPopup(page);  // A new window should open with the Klarna payment popup.

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});
});

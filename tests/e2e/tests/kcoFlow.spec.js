import puppeteer from "puppeteer";
import kcoURLS from "../helpers/kcoURLS";
import user from "../helpers/kcoUser";
import cart from "../helpers/kcoCart";
import kcoFrame from "../helpers/kcoFrame";
import kcoUtils from "../helpers/kcoUtils";
import API from "../api/API";
// import config from "../config/config";
import {
	klarnaAuth,
	freeShippingMethod,
	freeShippingMethodTarget,
	flatRateMethod,
	flatRateMethodTarget,
    creditPaymentMethod,
	billingData,
	userCredentials,
	timeOutTime,
	cardNumber,
	pinNumber,
	klarnaOrderEndpoint,
} from "../config/config";

let page;
let browser;
let context;

// const billingData = {
// 	emailSelector: "#billing-email",
// 	email: "test@krokedil.se",
// 	postalCodeSelector: "#billing-postal_code",
// 	postalCode: 12600,
// 	submitSelector: 'button[id="button-primary"]',
// };

// const userCredentials = {
// 	username: "admin",
// 	password: "password",
// };

// const selectors = {
// 	username: "#username",
// 	password: "#password",
// };

/**
 * Shipping method
 */
const shippingMethod = freeShippingMethod;
let shippingMethodTarget = null;

if (shippingMethod === "free") {
	shippingMethodTarget = `[id*="${freeShippingMethodTarget}"]`;
} else if (shippingMethod === "flat") {
	shippingMethodTarget = `[id*="${flatRateMethodTarget}"]`;
}

/**
 * Payment Method
 */
const selectedPaymentMethod = creditPaymentMethod;

describe("KCO", () => {
	beforeAll(async () => {
		browser = await puppeteer.launch({
			headless: false,
			defaultViewport: null,
			args: [
				"--disable-infobars",
				"--disable-web-security",
				"--disable-features=IsolateOrigins,site-per-process",
			],
		});
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();


		await page.goto(kcoURLS.MY_ACCOUNT);
		await user.login(userCredentials, { page });
		await page.goto(kcoURLS.SHOP);
		await cart.addSingleProductToCart(page, 1538);
		await page.goto(kcoURLS.CHECKOUT, { waitUntil: "networkidle0" });
	}, 250000);

	afterAll(() => {
		if (!page.isClosed()) {
			browser.close();
			context.close();
		}
	}, 900000);

	test("second flow should be on the my account page", async () => {

		if (await page.$('input[id="payment_method_kco"]')) {
			await page.evaluate(
				(paymentMethod) => paymentMethod.click(),
				await page.$('input[id="payment_method_kco"]')
			);
		}

		await page.waitForSelector('input[id="terms"]');
		await page.evaluate(
			(cb) => cb.click(),
			await page.$('input[id="terms"]')
		);

		if (shippingMethod !== "") {
			await page.waitForTimeout(2 * timeOutTime);
			await page.waitForSelector(shippingMethodTarget).id;
			await page.waitForTimeout(timeOutTime);
			await page.click(shippingMethodTarget).id;
			await page.waitForTimeout(timeOutTime);
		}

		const originalFrame = await kcoFrame.loadIFrame(
			page,
			"klarna-checkout-iframe"
        );

		kcoFrame.submitBillingForm(originalFrame, billingData);

		const frameNew = await kcoFrame.loadIFrame(
			page,
			"klarna-fullscreen-iframe"
		);

		await page.waitForTimeout(timeOutTime);
		await kcoUtils.expectSelector(
			originalFrame,
			page,
			'[data-cid="button.buy_button"]',
			timeOutTime
		);

		if (selectedPaymentMethod === "credit") {
			await kcoUtils.expectSelector(
				frameNew,
				page,
				'div[id*=".paynow_card."]',
				timeOutTime
			);

			const frameCreditCard = await kcoFrame.loadIFrame(
				page,
				"pgw-iframe-paynow_card"
			);
			await kcoUtils.expectInput(
				frameCreditCard,
				page,
				cardNumber,
				'input[id="cardNumber"]',
				0.25 * timeOutTime
			);
			await kcoUtils.expectInput(
				frameCreditCard,
				page,
				"1122",
				'input[id="expire"]',
				0.25 * timeOutTime
			);
			await kcoUtils.expectInput(
				frameCreditCard,
				page,
				"123",
				'input[id="securityCode"]',
				0.25 * timeOutTime
			);
		} else if (selectedPaymentMethod === "debit") {
			await kcoUtils.expectSelector(
				frameNew,
				page,
				'[data-cid="payment-selector-method.direct_debit"]',
				timeOutTime
			);
		} else if (selectedPaymentMethod === "invoice") {
			await kcoUtils.expectSelector(
				frameNew,
				page,
				'input[id*=".invoice."]',
				timeOutTime
			);
		}

		await kcoUtils.expectSelector(
			frameNew,
			page,
			'[data-cid="button.buy_button"]',
			timeOutTime
		);

		await kcoUtils.expectSelector(
			frameNew,
			page,
			'button[data-cid="skip-favorite-dialog-confirm-button"]',
			timeOutTime
		);



		await page.waitForTimeout(10 * timeOutTime);
		const currentURL = await page.url();
		const currentKCOId = currentURL.split("kco_order_id=")[1];
		const KCOResponse = await API.getKlarnaOrderById(
			page,
			klarnaOrderEndpoint,
			currentKCOId
		);

		await page.waitForTimeout(4 * timeOutTime);
		const value = await page.$eval(".entry-title", (e) => e.textContent);
		await page.screenshot({ path: "./order", type: "png" });

		expect(value).toBe("Order received");
	}, 190000);
});

import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import iframeHandler from "../helpers/iframeHandler";
import tests from "../config/tests.json"
import data from "../config/data.json";
import orderManagement from "../helpers/orderManagement";

const options = {
	"headless": true,
	"defaultViewport": null,
	"args": [
		"--disable-infobars",
		"--disable-web-security",
		"--disable-features=IsolateOrigins,site-per-process"
	]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 2500;
let json = data;
let orderID

describe("KCO E2E tests", () => {
	beforeAll(async () => {
		try {
			json = await setup.setupStore(json);
		} catch (e) {
			console.log(e);
		}
	}, 250000);

	beforeEach(async () => {
		browser = await puppeteer.launch(options);
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();
	}),

		afterEach(async () => {
			if (!page.isClosed()) {
				browser.close();
			}
			API.clearWCSession();
		}),

		test.each(tests)(
			"$name",
			async (args) => {
				try {
					// --------------- GUEST/LOGGED IN --------------- //
					if (args.loggedIn) {
						await page.goto(urls.MY_ACCOUNT);
						await utils.login(page, "admin", "password");
					}

					// --------------- SETTINGS --------------- //
					await utils.setPricesIncludesTax({ value: args.inclusiveTax });
					await utils.setIframeShipping(args.shippingInIframe);

					// --------------- ADD PRODUCTS TO CART --------------- //
					await utils.addMultipleProductsToCart(page, args.products, json);
					await page.waitForTimeout(1 * timeOutTime);

					// --------------- GO TO CHECKOUT --------------- //
					await page.goto(urls.CHECKOUT);
					await page.waitForTimeout(timeOutTime);
					await utils.selectKco(page);
					await page.waitForTimeout(1 * timeOutTime);

					// --------------- COUPON HANDLER --------------- //
					await utils.applyCoupons(page, args.coupons);

					// --------------- START OF IFRAME --------------- //
					const kcoIframe = await page.frames().find((frame) => frame.name() === "klarna-checkout-iframe");

					// --------------- B2B/B2C SELECTOR --------------- //
					await iframeHandler.setCustomerType(page, kcoIframe, args.customerType);

					// --------------- FORM SUBMISSION --------------- //
					await iframeHandler.processKcoForm(page, kcoIframe, args.customerType);

					// --------------- SHIPPING HANDLER --------------- //
					await iframeHandler.processShipping(page, kcoIframe, args.shippingMethod, args.shippingInIframe)

					// --------------- COMPLETE ORDER --------------- //
					await iframeHandler.completeOrder(page, kcoIframe);


					await page.waitForTimeout(2 * timeOutTime);


				} catch (e) {
					console.log("Error placing order", e)
				}

				// --------------- POST PURCHASE CHECKS --------------- //

				await page.waitForTimeout(1 * timeOutTime);
				const value = await page.$eval(".entry-title", (e) => e.textContent);
				expect(value).toBe("Order received");

				let checkoutURL = await page.evaluate(() => window.location.href)
				orderID = await checkoutURL.split('/')[5]

				// Get the thankyou page iframe and run checks.
				const thankyouIframe = await page.frames().find((frame) => frame.name() === "klarna-checkout-iframe");
				await thankyouIframe.click("[id='section-order-details__link']");
				await page.waitForTimeout(1 * timeOutTime);
				const kcoOrderData = await iframeHandler.getOrderData(thankyouIframe);
				expect(kcoOrderData[0]).toBe(args.expectedOrderLines);
				expect(kcoOrderData[1]).toBe(args.expectedTotal);


				// Check if Order Management is activated
				if(args.orderManagement != '') {
					await orderManagement.OrderManagementAction(page, orderID, args.orderManagement)
				}

			}, 240000);
});

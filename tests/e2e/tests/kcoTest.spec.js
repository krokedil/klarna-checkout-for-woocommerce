import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import iframeHandler from "../helpers/iframeHandler";
import tests from "../config/tests.json"
import data from "../config/data.json";
import orderManagement from "../helpers/orderManagement"

const options = {
	"headless": false,
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
			// if (!page.isClosed()) {
			// 	browser.close();
			// }
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


					//------------------------------------------------------------------------------------
					await page.waitForTimeout(2 * timeOutTime);

					let checkoutURL = await page.evaluate(() => window.location.href)

					orderID = await checkoutURL.split('/')[5]

					await page.waitForTimeout(1000);

				} catch (e) {
					console.log("Error placing order", e)
				}

				// --------------- POST PURCHASE CHECKS --------------- //

				await page.waitForTimeout(1 * timeOutTime);
				const value = await page.$eval(".entry-title", (e) => e.textContent);
				expect(value).toBe("Order received");

				// Get the thankyou page iframe and run checks.
				const thankyouIframe = await page.frames().find((frame) => frame.name() === "klarna-checkout-iframe");
				await thankyouIframe.click("[id='section-order-details__link']");
				await page.waitForTimeout(1 * timeOutTime);
				const kcoOrderData = await iframeHandler.getOrderData(thankyouIframe);
				// expect(kcoOrderData[0]).toBe(args.expectedOrderLines);
				// expect(kcoOrderData[1]).toBe(args.expectedTotal);

				// GO TO ORDERS - 0

				await page.goto(urls.ORDER);

				await page.waitForTimeout(timeOutTime);



				// UPDATE - I
				let wpDatabaseUpdateRequired = await page.$('.wp-core-ui');

				if (wpDatabaseUpdateRequired) {

					let updateWPDB = await page.$(".button.button-large.button-primary");

					if (updateWPDB) {

						let updateWPDBText = await page.$eval(".button.button-large.button-primary", (e) => e.textContent);

						if (updateWPDBText === "Update WordPress Database") {

							await updateWPDB.focus();
							await updateWPDB.click();

							await page.waitForTimeout(1000);

							let confirmUpdate = await page.$(".button.button-large");
							let confirmUpdateText = await page.$eval(".button.button-large", (e) => e.textContent);

							if (confirmUpdate && confirmUpdateText === "Continue") {

								await confirmUpdate.focus();
								await confirmUpdate.click();
							}
						}
					}

				}


				await page.waitForTimeout(1000);

				// LOGIN - II
				let loginForm = await page.$('.login');

				if (loginForm) {
					let loginName = await page.$('input[id="user_login"]')
					let loginPassword = await page.$('input[id="user_pass"]')

					await loginName.focus()
					await loginName.click({ clickCount: 3 });
					await loginName.type('admin');

					await loginPassword.focus()
					await loginPassword.click({ clickCount: 3 });
					await loginPassword.type('password');

					let submitLogin = await page.$('input[id="wp-submit"]')
					await submitLogin.focus();
					await submitLogin.click({ clickCount: 3 });

					await page.waitForTimeout(1000);

					let loginRemindLater = await page.$('.admin-email__actions-secondary > a');

					if (loginRemindLater) {
						// await page.$eval('.admin-email__actions-secondary > a', e => e.click())
						let loginComplete = await page.$('.admin-email__actions-secondary > a');
						await loginComplete.click()
					}
				}

				// ORDER MANAGEMENT - III

				// III - A

				await page.waitForTimeout(timeOutTime);

				let final = await page.$(`tr[id="post-${orderID}"]`);

				await page.waitForTimeout(1000);

				await final.focus();
				await final.click();

				// III - B

				await page.waitForTimeout(timeOutTime);

				let currentOrderStatus = await page.$eval('.select2-selection__rendered', e => e.innerText);

				if (currentOrderStatus === 'Processing') {

					// Set order to 'COMPLETED'
					await page.select('#order_status', 'wc-completed');
					let submitOrder = await page.$('.button.save_order.button-primary');

					await submitOrder.click();

				}

				await page.waitForTimeout(timeOutTime);

				let refundButton = await page.$('button.refund-items');
				await refundButton.click()

				await page.waitForTimeout(1500)

				// ORDERLINE

				let items = await page.$$('#order_line_items > .item')

				for (let index = 0; index < items.length; index++) {

					let refundItemAmount = await items[index].$eval('.quantity > .edit > input', e => e.value)
				
					let refundItemInput = await items[index].$('.quantity > .refund > input')

					await refundItemInput.click({clickCount:3});
					await refundItemInput.type(refundItemAmount)
					
				}

				let shippingAmount = await page.$eval('#order_shipping_line_items > .shipping > .line_cost > .edit > input', e => e.value)

				let shippingInput = await page.$('#order_shipping_line_items > .shipping > .line_cost > .refund > input');
				await shippingInput.click({clickCount:3});
				await shippingInput.type(shippingAmount);

				await page.waitForTimeout(1500)

				// Refund By Klarna button
				let orderTotal = await page.$eval('.wc-order-totals > tbody :nth-child(4) > .total > .woocommerce-Price-amount.amount > bdi', e => e.innerText)

				let refundAmountDisplay = await page.$('#refund_amount')
				await refundAmountDisplay.click({clickCount:3})

				let refundByKlarnaButtonText = await page.$eval('.button.button-primary.do-api-refund >  .wc-order-refund-amount > .woocommerce-Price-amount.amount', e => e.innerText)
				
				let refundByKlarnaButtonTextAmount = refundByKlarnaButtonText.substr(0, refundByKlarnaButtonText.indexOf(',') + 3);

				let orderTotalAmount = orderTotal.substr(0, orderTotal.indexOf(',') + 3);

				expect(refundByKlarnaButtonTextAmount).toBe(orderTotalAmount);

				let refundByKlarnaButton = await page.$('.button.button-primary.do-api-refund')

				await page.waitForTimeout(1000)

				page.on("dialog", (dialog) => {
					dialog.accept()
				})


				await refundByKlarnaButton.click()

				await page.waitForTimeout(timeOutTime)

				let notes = await page.$$('.system-note');

				let refundedNoteAmount

				for(let index = 0; index < notes.length; index++){

					let noteText = await notes[index].$eval('.note_content > p', e => e.innerText)
									
					if(noteText.includes("refunded via")) {
						refundedNoteAmount = noteText.substr(0, orderTotal.indexOf(',') + 3)
					}
				}

				expect(refundedNoteAmount).toBe(orderTotalAmount);

			}, 240000);
});

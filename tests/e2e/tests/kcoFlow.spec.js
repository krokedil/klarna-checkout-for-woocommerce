import puppeteer from "puppeteer";
import kcoURLS from "../helpers/kcoURLS";
import user from "../helpers/kcoUser";
import cart from "../helpers/kcoCart";
import kcoFrame from "../helpers/kcoFrame";
import kcoUtils from "../helpers/kcoUtils";

import {
	freeShippingMethod,
	freeShippingMethodTarget,
	flatRateMethodTarget,
	invoicePaymentMethod,
	billingData,
	userCredentials,
	timeOutTime,
	cardNumber,
	pinNumber,
	puppeteerOptions as options,
	customerAPIData,
	klarnaOrderEndpoint,
} from "../config/config";
import API from "../api/API";

let page;
let browser;
let context;

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
const selectedPaymentMethod = invoicePaymentMethod;

describe("KCO", () => {
	beforeAll(async () => {
		browser = await puppeteer.launch(options);
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();
		try {
			const customerResponse = await API.getWCCustomers();
			const { data } = customerResponse;
			console.log("customer exists");
			if (parseInt(data.length, 10) < 1) {
				try {
					await API.createWCCustomer(customerAPIData);
				} catch (error) {
					console.log(error);
				}
			}
		} catch (error) {
			console.log(error);
		}
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
			await page.waitForTimeout(timeOutTime);
			await page.waitForSelector(shippingMethodTarget).id;
			await page.waitForTimeout(timeOutTime);
			await page.click(shippingMethodTarget).id;
			await page.waitForTimeout(timeOutTime);
		}

		const originalFrame = await kcoFrame.loadIFrame(
			page,
			"klarna-checkout-iframe"
		);
		await kcoFrame.submitBillingForm(originalFrame, billingData);

		await page.waitForTimeout(timeOutTime);
		await kcoUtils.expectSelector(
			originalFrame,
			page,
			'[data-cid="am.continue_button"]',
			timeOutTime
		);

		await kcoUtils.expectSelector(
			originalFrame,import puppeteer from "puppeteer";
			import kcoURLS from "../helpers/kcoURLS";
			import user from "../helpers/kcoUser";
			import cart from "../helpers/kcoCart";
			import kcoFrame from "../helpers/kcoFrame";
			import kcoUtils from "../helpers/kcoUtils";
			
			import {
				freeShippingMethod,
				freeShippingMethodTarget,
				flatRateMethodTarget,
				invoicePaymentMethod,
				billingData,
				userCredentials,
				timeOutTime,
				cardNumber,
				pinNumber,
				puppeteerOptions as options,
				customerAPIData,
				klarnaOrderEndpoint,
			
			} from "../config/config";
			import API from "../api/API";
			
			let page;
			let browser;
			let context;
			
			
			let klarnaOrderId = [];
			let wooOrderId = [];
			
			let klarnaFirstName = 'klarna';
			let wooFirstName = 'woo';
			
			let klarnaLastName = 'klarna';
			let wooLastName = 'woo';
			
			let klarnaCompany = 'klarna';
			let wooCompany = 'woo';
			
			let klarnaCurrency = 'klarna';
			let wooCurrency = 'woo';
			
			let klarnaAddressOne = 'klarna';
			let wooAddressOne = 'woo';
			
			let klarnaAddressTwo = 'klarna';
			let wooAddressTwo = 'woo';
			
			let klarnaCity = 'klarna';
			let wooCity = 'woo';
			
			let klarnaRegion = 'klarna';
			let wooRegion = 'woo';
			
			let klarnaPostcode = 'klarna';
			let wooPostcode = 'woo';
			
			let klarnaCountry = 'klarna';
			let wooCountry = 'woo';
			
			let klarnaEmail = 'klarna';
			let wooEmail = 'woo';
			
			let klarnaSKU = [];
			let wooSKU = [];
			
			let klarnaTotalAmount = [];
			let wooTotalAmount = [];
			
			let klarnaShippingMethod = [];
			let wooShippingMethod = [];
			
			let klarnaQuantity = [];
			let wooQuantity = [];
			
			let klarnaPhone = 'klarna';
			let wooPhone = 'woo';
			
			let klarnaTotalTax = [];
			let wooTotalTax = [];
			
			let klarnaProductName = [];
			let wooProductName = [];
			
			
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
			const selectedPaymentMethod = invoicePaymentMethod;
			
			describe("KCO", () => {
				beforeAll(async () => {
					browser = await puppeteer.launch(options);
					context = await browser.createIncognitoBrowserContext();
					page = await context.newPage();
					try {
						const customerResponse = await API.getWCCustomers();
						const { data } = customerResponse;
						console.log("customer exists");
						if (parseInt(data.length, 10) < 1) {
							try {
								await API.createWCCustomer(customerAPIData);
							} catch (error) {
								console.log(error);
							}
						}
					} catch (error) {
						console.log(error);
					}
					await page.goto(kcoURLS.MY_ACCOUNT);
					await user.login(userCredentials, { page });
					await page.goto(kcoURLS.SHOP);
					// await cart.addSingleProductToCart(page, 1538);
					// await cart.addSingleProductToCart(page, 1538);
					// await cart.addSingleProductToCart(page, 1538);
			
					await cart.addMultipleProductsToCart(page, [1538, 1538, 1547]);
			
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
						await page.waitForTimeout(timeOutTime);
						await page.waitForSelector(shippingMethodTarget).id;
						await page.waitForTimeout(timeOutTime);
						await page.click(shippingMethodTarget).id;
						await page.waitForTimeout(timeOutTime);
					}
			
					const originalFrame = await kcoFrame.loadIFrame(
						page,
						"klarna-checkout-iframe"
					);
					await kcoFrame.submitBillingForm(originalFrame, billingData);
			
					await page.waitForTimeout(timeOutTime);
					await kcoUtils.expectSelector(
						originalFrame,
						page,
						'[data-cid="am.continue_button"]',
						timeOutTime
					);
			
					await kcoUtils.expectSelector(
						originalFrame,
						page,
						'input[id="payment-selector-pay_now"]',
						timeOutTime
					);
			
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
			
					await kcoUtils.expectSelector(
						frameNew,
						page,
						'[id="nin"]',
						timeOutTime
					);
					await kcoUtils.expectInput(
						frameNew,
						page,
						pinNumber,
						'[id="nin"]',
						timeOutTime
					);
					await kcoUtils.expectSelector(
						frameNew,
						page,
						'[id="supplement_nin_dialog__footer-button-wrapper"]',
						timeOutTime
					);
			
					await page.waitForTimeout(2 * timeOutTime);
					await kcoUtils.expectSelector(
						page
							.frames()
							.find((fr) => fr.name() === "klarna-fullscreen-iframe"),
						page,
						'[id="confirm_bank_account_dialog__footer-button-wrapper"]',
						page,
						'[id="confirm_bank_account_dialog__footer-button-wrapper"]',
						timeOutTime
					);
					await page.waitForTimeout(3 * timeOutTime);
					const currentURL = await page.url();
					const currentKCOId = currentURL.split("kco_order_id=")[1];
					const response = await API.getKlarnaOrderById(
						page,
						klarnaOrderEndpoint,
						currentKCOId		);
			
			
					const orderId = currentURL
						.split("/")
						.filter((urlPart) => /^\d+$/.test(urlPart))[0];
			
					const wooCommerceOrder = await API.getWCOrderById(orderId);
			
					//---------- LOG RESPONSE -------------
			
						// -- STEP 1 ---
			
						// console.log ('---- KLARNA ----- OOO')
						// console.log(response.data)
						// console.log ('---- KLARNA ----- XXX')
			
						// console.log ('---- WOOCOMMERCE ----- OOO')
						// console.log(wooCommerceOrder.data)
						// console.log ('---- WOOCOMMERCE ----- XXX')
			
						// let klarnaPoligon = response.data.order_lines.filter(x => x.reference === 'test-product-simple-product-12-tax')[0];
						// let wooPoligon = wooCommerceOrder.data.line_items.filter(x => x.sku === 'test-product-simple-product-12-tax')[0];
			
						// console.log( klarnaPoligon);
						// console.log( wooPoligon);
			
			
			
						// -- STEP 2 ---
						let klarnaOrderLinesContainer = [];
						let wooOrderLinesContainer = [];
			
			
						response.data.order_lines.forEach(klarnaOrderLinesItemType => {
							if(klarnaOrderLinesItemType.type !== 'shipping_fee') {
								klarnaOrderLinesContainer.push(klarnaOrderLinesItemType)
							}
						});
			
						wooCommerceOrder.data.line_items.forEach(wooOrderLinesItemType => {
							if(wooOrderLinesItemType.type !== 'shipping_fee') {
								wooOrderLinesContainer.push(wooOrderLinesItemType)
							}
						});
			
						// console.log(klarnaOrderLinesContainer)
						// console.log(wooOrderLinesContainer)
			
						for (let i=0; i<klarnaOrderLinesContainer.length; i++){
			
							if (klarnaOrderLinesContainer[i].reference === wooOrderLinesContainer[i].sku) {
			
								if(parseFloat(klarnaOrderLinesContainer[i].total_amount) === parseInt(Math.round((parseFloat(wooOrderLinesContainer[i].total) + parseFloat(wooOrderLinesContainer[i].total_tax)) * 100).toFixed(2))) {
									klarnaTotalAmount = klarnaOrderLinesContainer[i].total_amount;
									wooTotalAmount = parseInt(Math.round((parseFloat(wooOrderLinesContainer[i].total) + parseFloat(wooOrderLinesContainer[i].total_tax)) * 100).toFixed(2));
								}
			
								if(klarnaOrderLinesContainer[i].quantity === wooOrderLinesContainer[i].quantity ) {
									klarnaQuantity.push(klarnaOrderLinesContainer[i].quantity);
									wooQuantity.push(wooOrderLinesContainer[i].quantity);
								}
			
								if(klarnaOrderLinesContainer[i].total_tax_amount === wooOrderLinesContainer[i].rate_percent ) {
									klarnaTotalTax.push(klarnaOrderLinesContainer[i].total_tax_amount);
									wooTotalTax.push(wooOrderLinesContainer[i].rate_percent);
								}
			
								if(klarnaOrderLinesContainer[i].name === wooOrderLinesContainer[i].method_title ) {
									klarnaShippingMethod.push(klarnaOrderLinesContainer[i].name);
									wooShippingMethod.push(wooOrderLinesContainer[i].method_title);
								}
			
								if(klarnaOrderLinesContainer[i].name === wooOrderLinesContainer[i].name ) {
									klarnaProductName.push(klarnaOrderLinesContainer[i].name);
									wooProductName.push(wooOrderLinesContainer[i].name);
								}
			
								if(klarnaOrderLinesContainer[i].reference === wooOrderLinesContainer[i].sku ) {
									klarnaSKU.push(klarnaOrderLinesContainer[i].reference);
									wooSKU.push(wooOrderLinesContainer[i].sku);
								}
			
								if(klarnaOrderLinesContainer[i].order_id === wooOrderLinesContainer[i].transaction_id ) {
									klarnaOrderId.push(klarnaOrderLinesContainer[i].order_id);
									wooOrderId.push(wooOrderLinesContainer[i].transaction_id);
								}
							}
						}
			
			
			
					if(response.data.shipping_address.given_name === wooCommerceOrder.data.billing.first_name ) {
						klarnaFirstName = response.data.shipping_address.given_name;
						wooFirstName = wooCommerceOrder.data.billing.first_name;
					}
			
					if(response.data.shipping_address.family_name === wooCommerceOrder.data.billing.last_name ) {
						klarnaLastName = response.data.shipping_address.family_name;
						wooLastName = wooCommerceOrder.data.billing.last_name;
					}
			
					if(response.data.shipping_address.title === wooCommerceOrder.data.billing.company ) {
						klarnaCompany = response.data.shipping_address.title;
						wooCompany = wooCommerceOrder.data.billing.company;
					}
			
					if(response.data.shipping_address.street_address === wooCommerceOrder.data.billing.address_1 ) {
						klarnaAddressOne = response.data.shipping_address.street_address;
						wooAddressOne = wooCommerceOrder.data.billing.address_1;
					}
			
					if(response.data.shipping_address.street_address2 === wooCommerceOrder.data.billing.address_2 ) {
						klarnaAddressTwo = response.data.shipping_address.street_address2;
						wooAddressTwo = wooCommerceOrder.data.billing.address_2;
					}
			
					if(response.data.shipping_address.city === wooCommerceOrder.data.billing.city ) {
						klarnaCity = response.data.shipping_address.city;
						wooCity = wooCommerceOrder.data.billing.city;
					}
			
					if(response.data.shipping_address.region === wooCommerceOrder.data.billing.state ) {
						klarnaRegion = response.data.shipping_address.region;
						wooRegion = wooCommerceOrder.data.billing.state;
					}
			
					if(response.data.shipping_address.postal_code.replace(/\s/g, '') === wooCommerceOrder.data.billing.postcode ) {
						klarnaPostcode = response.data.shipping_address.postal_code.replace(/\s/g, '');
						wooPostcode = wooCommerceOrder.data.billing.postcode;
					}
			
					if(response.data.shipping_address.country === wooCommerceOrder.data.billing.country ) {
						klarnaCountry = response.data.shipping_address.country;
						wooCountry = wooCommerceOrder.data.billing.country;
					}
			
					if(response.data.shipping_address.email === wooCommerceOrder.data.billing.email ) {
						klarnaEmail = response.data.shipping_address.email;
						wooEmail = wooCommerceOrder.data.billing.email;
					}
			
					if(response.data.shipping_address.phone === wooCommerceOrder.data.billing.phone.replace(/\s/g, '') ) {
						klarnaPhone = response.data.shipping_address.phone;
						wooPhone = wooCommerceOrder.data.billing.phone.replace(/\s/g, '');
					}
			
					// if(parseFloat(response.data.order_lines[0].total_amount) === parseInt(Math.round((parseFloat(wooCommerceOrder.data.line_items[0].total) + parseFloat(wooCommerceOrder.data.line_items[0].total_tax)) * 100).toFixed(2))) {
					// 	klarnaTotalAmount = response.data.order_lines[0].total_amount;
					// 	wooTotalAmount = parseInt(Math.round((parseFloat(wooCommerceOrder.data.line_items[0].total) + parseFloat(wooCommerceOrder.data.line_items[0].total_tax)) * 100).toFixed(2));
					// }
			
			
					// if(response.data.order_lines[1].name === wooCommerceOrder.data.shipping_lines[0].method_title ) {
					// 	klarnaShippingMethod = response.data.order_lines[1].name;
					// 	wooShippingMethod = wooCommerceOrder.data.shipping_lines[0].method_title;
					// }
			
			
					// if(response.data.order_lines[0].total_tax_amount === wooCommerceOrder.data.tax_lines[0].rate_percent ) {
					// 	klarnaTotalTax = response.data.order_lines[0].total_tax_amount;
					// 	wooTotalTax = wooCommerceOrder.data.tax_lines[0].rate_percent;
					// }
			
					// if(response.data.order_lines[0].quantity === wooCommerceOrder.data.line_items[0].quantity ) {
					// 	klarnaQuantity = response.data.order_lines[0].quantity;
					// 	wooQuantity = wooCommerceOrder.data.line_items[0].quantity;
					// }
			
					// if(response.data.order_lines[0].name === wooCommerceOrder.data.line_items[0].name ) {
					// 	klarnaProductName = response.data.order_lines[0].name;
					// 	wooProductName = wooCommerceOrder.data.line_items[0].name;
					// }
			
					// if(response.data.order_lines[0].reference === wooCommerceOrder.data.line_items[0].sku ) {
					// 	klarnaSKU = response.data.order_lines[0].reference;
					// 	wooSKU = wooCommerceOrder.data.line_items[0].sku;
					// }
			
					// if(response.data.order_id === wooCommerceOrder.data.transaction_id ) {
					// 	klarnaOrderId = response.data.order_id;
					// 	wooOrderId = wooCommerceOrder.data.transaction_id;
					// }
			
					if(response.data.purchase_currency === wooCommerceOrder.data.currency ) {
						klarnaCurrency = response.data.purchase_currency;
						wooCurrency = wooCommerceOrder.data.currency;
					}
			
					await page.waitForTimeout(5 * timeOutTime);
					const value = await page.$eval(".entry-title", (e) => e.textContent);
					await page.screenshot({ path: "./order", type: "png" });
			
					expect(value).toBe("Order received")
					;
				}, 190000);
			
			
			
				test("Compare IDs", async () => {
					expect(toString(klarnaOrderId)).toBe(toString(wooOrderId))
					;
				}, 190000);
			
				test("Compare names", async () => {
					expect(klarnaFirstName).toBe(wooFirstName)
					;
				}, 190000);
			
				test("Compare last names", async () => {
					expect(klarnaLastName).toBe(wooLastName)
					;
				}, 190000);
			
				test("Compare cities", async () => {
					expect(klarnaCity).toBe(wooCity)
					;
				}, 190000);
			
				test("Compare regions", async () => {
					expect(klarnaRegion).toBe(wooRegion)
					;
				}, 190000);
			
				test("Compare countries", async () => {
					expect(klarnaCountry).toBe(wooCountry)
					;
				}, 190000);
			
				test("Compare post codes", async () => {
					expect(klarnaPostcode).toBe(wooPostcode)
					;
				}, 190000);
			
				test("Compare companies", async () => {
					expect(klarnaCompany).toBe(wooCompany)
					;
				}, 190000);
			
				test("Compare first address", async () => {
					expect(klarnaAddressOne).toBe(wooAddressOne)
					;
				}, 190000);
			
				test("Compare second address", async () => {
					expect(klarnaAddressTwo).toBe(wooAddressTwo)
					;
				}, 190000);
			
				test("Compare emails", async () => {
					expect(klarnaEmail).toBe(wooEmail)
					;
				}, 190000);
			
				test("Compare telephones", async () => {
					expect(klarnaPhone).toBe(wooPhone)
					;
				}, 190000);
			
				test("Compare SKU-s", async () => {
					expect(toString(klarnaSKU)).toBe(toString(wooSKU))
					;
				}, 190000);
			
				test("Compare total amounts", async () => {
					expect(toString(klarnaTotalAmount)).toBe(toString(wooTotalAmount))
					;
				}, 190000);
			
				test("Compare total taxes", async () => {
					expect(toString(klarnaTotalTax)).toBe(toString(wooTotalTax))
					;
				}, 190000);
			
				test("Compare product names", async () => {
					expect(toString(klarnaProductName)).toBe(toString(wooProductName))
					;
				}, 190000);
			
				test("Compare Shipping methods", async () => {
					expect(toString(klarnaShippingMethod)).toBe(toString(wooShippingMethod))
					;
				}, 190000);
			
				test("Compare Quantities", async () => {
					expect(toString(klarnaQuantity)).toBe(toString(wooQuantity))
					;
				}, 190000);
			
				test("Compare currencies", async () => {
					expect(klarnaCurrency).toBe(wooCurrency)
					;
				}, 190000);
			
			});
			
			page,
			'input[id="payment-selector-pay_now"]',
			timeOutTime
		);

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

		await kcoUtils.expectSelector(
			frameNew,
			page,
			'[id="nin"]',
			timeOutTime
		);
		await kcoUtils.expectInput(
			frameNew,
			page,
			pinNumber,
			'[id="nin"]',
			timeOutTime
		);
		await kcoUtils.expectSelector(
			frameNew,
			page,
			'[id="supplement_nin_dialog__footer-button-wrapper"]',
			timeOutTime
		);

		await page.waitForTimeout(2 * timeOutTime);
		await kcoUtils.expectSelector(
			page
				.frames()
				.find((fr) => fr.name() === "klarna-fullscreen-iframe"),
			page,
			'[id="confirm_bank_account_dialog__footer-button-wrapper"]',
			page,
			'[id="confirm_bank_account_dialog__footer-button-wrapper"]',
			timeOutTime
		);
		await page.waitForTimeout(3 * timeOutTime);
		const currentURL = await page.url();
		const currentKCOId = currentURL.split("kco_order_id=")[1];
		const response = await API.getKlarnaOrderById(
			page,
			klarnaOrderEndpoint,
			currentKCOId
		);
		console.dir(response, { depth: null });

		const orderId = currentURL
			.split("/")
			.filter((urlPart) => /^\d+$/.test(urlPart))[0];

		try {
			const wooCommerceOrder = await API.getWCOrderById(orderId);
			console.dir(wooCommerceOrder, { depth: null });
		} catch (error) {
			console.log(error);
		}
		await page.waitForTimeout(5 * timeOutTime);
		const value = await page.$eval(".entry-title", (e) => e.textContent);
		await page.screenshot({ path: "./order", type: "png" });

		expect(value).toBe("Order received");
	}, 190000);
});

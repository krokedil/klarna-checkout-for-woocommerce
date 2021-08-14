import puppeteer from "puppeteer";
import kcoURLS from "../helpers/kcoURLS";
import user from "../helpers/kcoUser";
import cart from "../helpers/kcoCart";
import kcoFrame from "../helpers/kcoFrame";
import kcoUtils from "../helpers/kcoUtils";
import { wooValues, klarnaValues } from "../helpers/kcoCompRes";

import {
	puppeteerOptions as options,
	userCredentials,
	billingData,
} from "../config/config";

import tests from "../config/tests.json"
import setup from "../api/setup";
import API from "../api/API";
import data from "../config/data.json";

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 5000;
let json = data;

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
			// context.close();
		}
		API.clearWCSession();
	}),

	test.each(tests)(
		"$name",
		async (args) => {
			if(args.loggedIn) {
				await page.goto(kcoURLS.MY_ACCOUNT);
				await user.login(userCredentials, { page });
			}

			await kcoUtils.wcPricesIncludeTax({value: args.inclusiveTax});
			await kcoUtils.toggleIFrame(args.shippingInIframe);

			await cart.addMultipleProductsToCart(page, args.products, json);

			await page.waitForTimeout(timeOutTime);

			await page.goto(kcoURLS.CHECKOUT);
			await page.waitForTimeout(timeOutTime);

			// Choose Klarna as payment method
			if (await page.$('input[id="payment_method_kco"]')) {
				await page.evaluate(
					(paymentMethod) => paymentMethod.click(),
					await page.$('input[id="payment_method_kco"]')
				);
			}

			await page.waitForTimeout(2 * timeOutTime);

			const originalFrame = await kcoFrame.loadIFrame(
				page,
				"klarna-checkout-iframe"
			);

			// Submit billing data
			await kcoFrame.submitBillingForm(
				originalFrame,
				billingData,
				args.customerType
			);

			// Apply coupons
			if( args.coupons.length > 0 ) {
				await kcoUtils.addCouponsOnCheckout(
					page,
					args.loggedIn,
					args.coupons
				);
			}

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

			// Check for Klarna iFrame shipping and implement shipping method
			if( args.shippingMethod !== "" ) {
				await kcoUtils.chooseKlarnaShippingMethod(
					page,
					originalFrame,
					args.shippingInIframe,
					args.shippingMethod,
					timeOutTime
				);
			}

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
				"4111111111111111",
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

			await kcoUtils.expectSelector(
				frameNew,
				page,
				'[data-cid="button.buy_button"]',
				0.25 * timeOutTime
			);

			await kcoUtils.expectSelector(
				frameNew,
				page,
				'button[data-cid="skip-favorite-dialog-confirm-button"]',
				0.25 * timeOutTime
			);

			await kcoUtils.expectSelector(
				frameNew,
				page,
				'[id="nin"]',
				0.25 * timeOutTime
			);

			await kcoUtils.expectInput(
				frameNew,
				page,
				"410321-9202",
				'[id="nin"]',
				0.25 * timeOutTime
			);

			await kcoUtils.expectSelector(
				frameNew,
				page,
				'[id="supplement_nin_dialog__footer-button-wrapper"]',
				0.25 * timeOutTime
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
					
			/* TMP COMMENTS
			await page.waitForTimeout(2 * timeOutTime);
			const currentURL = await page.url();
			const currentKCOId = currentURL.split("kco_order_id=")[1];
			const response = await API.getKlarnaOrderById(
				page,
				klarnaOrderEndpoint,
				currentKCOId
			);
			const orderId = currentURL
				.split("/")
				.filter((urlPart) => /^\d+$/.test(urlPart))[0];
			*/
			
			await page.waitForTimeout(timeOutTime);
			const value = await page.$eval(".entry-title", (e) => e.textContent);
			expect(value).toBe("Order received");
		}
	)

	/**
	 * Begin test suite
	 */
	/*test("Test", async () => {
		// Add products to Cart
		await page.waitForTimeout(timeOutTime);
		await cart.addMultipleProductsToCart(page, productsToCart);
		await page.waitForTimeout(timeOutTime);

		await page.goto(kcoURLS.CHECKOUT);
		await page.waitForTimeout(timeOutTime);

		// Choose Klarna as payment method
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

		await page.waitForTimeout(timeOutTime);

		const originalFrame = await kcoFrame.loadIFrame(
			page,
			"klarna-checkout-iframe"
		);

		await page.waitForTimeout(2 * timeOutTime);

		// Submit billing data
		await kcoFrame.submitBillingForm(
			originalFrame,
			billingData,
			customerType
		);

		// Apply coupons
		await kcoUtils.addCouponsOnCheckout(
			page,
			isUserLoggedIn,
			appliedCoupons
		);

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

		// Check for Klarna iFrame shipping and implement shipping method
		await kcoUtils.chooseKlarnaShippingMethod(
			page,
			originalFrame,
			iframeShipping,
			shippingMethod,
			freeShippingMethodTarget,
			flatRateMethodTarget,
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

		await page.waitForTimeout(2 * timeOutTime);
		const currentURL = await page.url();
		const currentKCOId = currentURL.split("kco_order_id=")[1];
		const response = await API.getKlarnaOrderById(
			page,
			klarnaOrderEndpoint,
			currentKCOId
		);

		const orderId = currentURL
			.split("/")
			.filter((urlPart) => /^\d+$/.test(urlPart))[0];

		const wooCommerceOrder = await API.getWCOrderById(orderId);

		const klarnaOrderLinesContainer = [];
		const wooOrderLinesContainer = [];

		response.data.order_lines.forEach((klarnaOrderLinesItemType) => {
			if (klarnaOrderLinesItemType.type !== "shipping_fee") {
				klarnaOrderLinesContainer.push(klarnaOrderLinesItemType);
			}
		});

		wooCommerceOrder.data.line_items.forEach((wooOrderLinesItemType) => {
			if (wooOrderLinesItemType.type !== "shipping_fee") {
				wooOrderLinesContainer.push(wooOrderLinesItemType);
			}
		});

		for (let i = 0; i < klarnaOrderLinesContainer.length; i += 1) {
			if (
				klarnaOrderLinesContainer[i].reference ===
				wooOrderLinesContainer[i].sku
			) {
				if (
					parseFloat(klarnaOrderLinesContainer[i].total_amount) ===
					parseInt(
						Math.round(
							(parseFloat(wooOrderLinesContainer[i].total) +
								parseFloat(
									wooOrderLinesContainer[i].total_tax
								)) *
								100
						).toFixed(2),
						10
					)
				) {
					klarnaValues.totalAmount.push(
						klarnaOrderLinesContainer[i].total_amount
					);
					wooValues.totalAmount.push(
						parseInt(
							Math.round(
								(parseFloat(wooOrderLinesContainer[i].total) +
									parseFloat(
										wooOrderLinesContainer[i].total_tax
									)) *
									100
							).toFixed(2),
							10
						)
					);
				}

				if (
					klarnaOrderLinesContainer[i].quantity ===
					wooOrderLinesContainer[i].quantity
				) {
					klarnaValues.quantity.push(
						klarnaOrderLinesContainer[i].quantity
					);
					wooValues.quantity.push(wooOrderLinesContainer[i].quantity);
				}

				if (
					klarnaOrderLinesContainer[i].total_tax_amount ===
					wooOrderLinesContainer[i].total_tax * 100
				) {
					klarnaValues.totalTax.push(
						klarnaOrderLinesContainer[i].total_tax_amount
					);
					wooValues.totalTax.push(
						wooOrderLinesContainer[i].total_tax * 100
					);
				}

				if (
					klarnaOrderLinesContainer[i].name ===
					wooOrderLinesContainer[i].name
				) {
					klarnaValues.productName.push(
						klarnaOrderLinesContainer[i].name
					);
					wooValues.productName.push(wooOrderLinesContainer[i].name);
				}

				if (
					klarnaOrderLinesContainer[i].reference ===
					wooOrderLinesContainer[i].sku
				) {
					klarnaValues.sku.push(
						klarnaOrderLinesContainer[i].reference
					);
					wooValues.sku.push(wooOrderLinesContainer[i].sku);
				}
			}
		}

		if (response.data.order_id === wooCommerceOrder.data.transaction_id) {
			klarnaValues.orderId = response.data.order_id;
			wooValues.orderId = wooCommerceOrder.data.transaction_id;
		}

		if (
			response.data.shipping_address.given_name ===
			wooCommerceOrder.data.billing.first_name
		) {
			klarnaValues.firstName = response.data.shipping_address.given_name;
			wooValues.firstName = wooCommerceOrder.data.billing.first_name;
		}

		if (
			response.data.shipping_address.family_name ===
			wooCommerceOrder.data.billing.last_name
		) {
			klarnaValues.lastName = response.data.shipping_address.family_name;
			wooValues.lastName = wooCommerceOrder.data.billing.last_name;
		}

		// Case for B2CB individual
		if (customerType === "person") {
			if (
				response.data.shipping_address.title ===
				wooCommerceOrder.data.billing.company
			) {
				klarnaValues.company = response.data.shipping_address.title;
				wooValues.company = wooCommerceOrder.data.billing.company;
			}

			// Case for B2CB for company
		} else if (customerType === "company") {
			if (
				response.data.shipping_address.organization_name ===
				wooCommerceOrder.data.billing.company
			) {
				klarnaValues.company =
					response.data.shipping_address.organization_name;
				wooValues.company = wooCommerceOrder.data.billing.company;
			}
		}

		if (
			response.data.shipping_address.street_address ===
			wooCommerceOrder.data.billing.address_1
		) {
			klarnaValues.addressOne =
				response.data.shipping_address.street_address;
			wooValues.addressOne = wooCommerceOrder.data.billing.address_1;
		}

		if (
			response.data.shipping_address.street_address2 ===
			wooCommerceOrder.data.billing.address_2
		) {
			klarnaValues.addressTwo =
				response.data.shipping_address.street_address2;
			wooValues.addressTwo = wooCommerceOrder.data.billing.address_2;
		}

		if (
			response.data.shipping_address.city ===
			wooCommerceOrder.data.billing.city
		) {
			klarnaValues.city = response.data.shipping_address.city;
			wooValues.city = wooCommerceOrder.data.billing.city;
		}

		if (
			response.data.shipping_address.region ===
			wooCommerceOrder.data.billing.state
		) {
			klarnaValues.region = response.data.shipping_address.region;
			wooValues.region = wooCommerceOrder.data.billing.state;
		}

		if (
			response.data.shipping_address.postal_code.replace(/\s/g, "") ===
			wooCommerceOrder.data.billing.postcode
		) {
			klarnaValues.postcode =
				response.data.shipping_address.postal_code.replace(/\s/g, "");
			wooValues.postcode = wooCommerceOrder.data.billing.postcode;
		}

		if (
			response.data.shipping_address.country ===
			wooCommerceOrder.data.billing.country
		) {
			klarnaValues.country = response.data.shipping_address.country;
			wooValues.country = wooCommerceOrder.data.billing.country;
		}

		if (
			response.data.shipping_address.email ===
			wooCommerceOrder.data.billing.email
		) {
			klarnaValues.email = response.data.shipping_address.email;
			wooValues.email = wooCommerceOrder.data.billing.email;
		}

		if (
			response.data.shipping_address.phone ===
			wooCommerceOrder.data.billing.phone.replace(/\s/g, "")
		) {
			klarnaValues.phone = response.data.shipping_address.phone;
			wooValues.phone = wooCommerceOrder.data.billing.phone.replace(
				/\s/g,
				""
			);
		}

		if (
			response.data.purchase_currency === wooCommerceOrder.data.currency
		) {
			klarnaValues.currency = response.data.purchase_currency;
			wooValues.currency = wooCommerceOrder.data.currency;
		}

		if (
			response.data.order_lines[productCounterArray.length].name ===
			wooCommerceOrder.data.shipping_lines[0].method_title
		) {
			klarnaValues.shippingMethod =
				response.data.order_lines[productCounterArray.length].name;
			wooValues.shippingMethod =
				wooCommerceOrder.data.shipping_lines[0].method_title;
		}

		await page.waitForTimeout(timeOutTime);
		const value = await page.$eval(".entry-title", (e) => e.textContent);
		expect(value).toBe("Order received");
	}, 190000);*/
});

import puppeteer from "puppeteer";
import kcoURLS from "../helpers/kcoURLS";
import user from "../helpers/kcoUser";
import cart from "../helpers/kcoCart";
import kcoFrame from "../helpers/kcoFrame";
import kcoUtils from "../helpers/kcoUtils";
import { wooValues, klarnaValues } from "../helpers/kcoCompRes";

import {
	puppeteerOptions as options,
	customerAPIData,
	klarnaOrderEndpoint,

	/**
	 * General data
	 */
	billingData,
	userCredentials,
	timeOutTime,
	cardNumber,
	pinNumber,

	/**
	 * Shipping methods
	 */
	freeShippingMethod,
	freeShippingMethodTarget,
	flatRateMethod,
	flatRateMethodTarget,

	/**
	 * Payment methods
	 */
	invoicePaymentMethod,
	debitPaymentMethod,
	creditPaymentMethod,

	/**
	 * Coupons
	 */
	couponFixedCart,
	couponFixedProduct,
	couponPercent,
	couponTotalFreeShipping,
	couponTotalWithShipping,

	/**
	 * Products
	 */
	 outOfStock,
	 variable25,
	 downloadable0,
	 downloadable25,
	 downloadableShipping0,
	 downloadableShipping25,
	 simple12,
	 simple6,
	 virtual0,
	 virtual25,
	 virtualDownloadable0,
	 virtualDownloadable25,
	 manyCharacters
} from "../config/config";

import API from "../api/API";
import woocommerce from "../api/woocommerce";

// Main selectors
let page;
let browser;
let context;
let productCounterArray = []

/**
 * TEST ELEMENTS SELECTORS
 * Input variables that are to be applied for the test
 */

// User logged-in (true) / Guest (false)
const isUserLoggedIn = true;

// Products selection
const productsToCart = [
	downloadable0,
	simple12,
	virtualDownloadable25,
];

kcoUtils.createHelperArray(productsToCart, productCounterArray)

// Shipping method selection
const shippingMethod = freeShippingMethod;

// Private individual ("person") / Organization or Company ("company")
const customerType = "company";

// Payment method selection
const selectedPaymentMethod = invoicePaymentMethod;

// Coupon selection
const appliedCoupons = [couponPercent];

// Shipping in KCO iFrame ("yes") / Standard WC ("no")
const iframeShipping = "yes";

/**
 * TEST INITIALIZATION
 */
describe("KCO", () => {
	beforeAll(async () => {
		browser = await puppeteer.launch(options);
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();
		try {
			const customerResponse = await API.getWCCustomers();
			const { data } = customerResponse;
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

		// Check for user logged in
		if (isUserLoggedIn) {
			// Login with User Credentials
			await page.goto(kcoURLS.MY_ACCOUNT);
			await user.login(userCredentials, { page });
		}

		await kcoUtils.toggleIFrame(iframeShipping);

		await page.goto(kcoURLS.CHECKOUT, { waitUntil: "networkidle0" });
	}, 250000);

	// Close Chromium on test end (will close on both success and fail)
	afterAll(() => {
		if (!page.isClosed()) {
			browser.close();
			// context.close();
		}
	}, 900000);

	/**
	 * Begin test suite
	 */
	test("second flow should be on the my account page", async () => {
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
		await kcoFrame.submitBillingForm(originalFrame, billingData, customerType);

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
					klarnaValues.totalAmount.push(klarnaOrderLinesContainer[i].total_amount);
					wooValues.totalAmount.push(parseInt(
						Math.round(
							(parseFloat(wooOrderLinesContainer[i].total) +
								parseFloat(
									wooOrderLinesContainer[i].total_tax
								)) *
								100
						).toFixed(2),
						10
					));
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
		if(customerType === 'person'){

			if (
				response.data.shipping_address.title ===
				wooCommerceOrder.data.billing.company
			) {
				klarnaValues.company = response.data.shipping_address.title;
				wooValues.company = wooCommerceOrder.data.billing.company;
			}

		// Case for B2CB for company
		} else if ( customerType === 'company') {

			if (
				response.data.shipping_address.organization_name ===
				wooCommerceOrder.data.billing.company
			) {
				klarnaValues.company = response.data.shipping_address.organization_name;
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
			klarnaValues.postcode = response.data.shipping_address.postal_code.replace(
				/\s/g,
				""
			);
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
	}, 190000);

	/**
	 * Compare expected and received values
	 */

	test("Compare IDs", async () => {
		expect(toString(klarnaValues.orderId)).toBe(
			toString(wooValues.orderId)
		);
	}, 190000);

	test("Compare names", async () => {
		expect(klarnaValues.firstName).toBe(wooValues.firstName);
	}, 190000);

	test("Compare last names", async () => {
		expect(klarnaValues.lastName).toBe(wooValues.lastName);
	}, 190000);

	test("Compare cities", async () => {
		expect(klarnaValues.city).toBe(wooValues.city);
	}, 190000);

	test("Compare regions", async () => {
		expect(klarnaValues.region).toBe(wooValues.region);
	}, 190000);

	test("Compare countries", async () => {
		expect(klarnaValues.country).toBe(wooValues.country);
	}, 190000);

	test("Compare post codes", async () => {
		expect(klarnaValues.postcode).toBe(wooValues.postcode);
	}, 190000);

	test("Compare companies", async () => {
		expect(klarnaValues.company).toBe(wooValues.company);
	}, 190000);

	test("Compare first address", async () => {
		expect(klarnaValues.addressOne).toBe(wooValues.addressOne);
	}, 190000);

	test("Compare second address", async () => {
		expect(klarnaValues.addressTwo).toBe(wooValues.addressTwo);
	}, 190000);

	test("Compare emails", async () => {
		expect(klarnaValues.email).toBe(wooValues.email);
	}, 190000);

	test("Compare telephones", async () => {
		expect(klarnaValues.phone).toBe(wooValues.phone);
	}, 190000);

	test("Compare SKU-s", async () => {
		expect(toString(klarnaValues.sku)).toBe(toString(wooValues.sku));
	}, 190000);

	test("Compare total amounts", async () => {
		expect(toString(klarnaValues.totalAmount)).toBe(
			toString(wooValues.totalAmount)
		);
	}, 190000);

	test("Compare total taxes", async () => {
		expect(toString(klarnaValues.totalTax)).toBe(
			toString(wooValues.totalTax)
		);
	}, 190000);

	test("Compare product names", async () => {
		expect(toString(klarnaValues.productName)).toBe(
			toString(wooValues.productName)
		);
	}, 190000);

	test("Compare Shipping methods", async () => {
		expect(toString(klarnaValues.shippingMethod)).toBe(
			toString(wooValues.shippingMethod)
		);
	}, 190000);

	test("Compare Quantities", async () => {
		expect(toString(klarnaValues.quantity)).toBe(
			toString(wooValues.quantity)
		);
	}, 190000);

	test("Compare currencies", async () => {
		expect(klarnaValues.currency).toBe(wooValues.currency);
	}, 190000);
});

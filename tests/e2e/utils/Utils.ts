import { APIRequestContext, Page, request } from "@playwright/test";
import { KlarnaPopup } from "../pages/KlarnaPopup";
import { KlarnaIFrame } from "../pages/KlarnaIFrame";

const {
	KLARNA_API_USERNAME,
	KLARNA_API_PASSWORD,
} = process.env;

export const GetKcApiClient = async (): Promise<APIRequestContext> => {
	return await request.newContext({
		baseURL: `https://api.playground.klarna.com/checkout/v1/`,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				`${KLARNA_API_USERNAME ?? 'admin'}:${KLARNA_API_PASSWORD ?? 'password'}`
			).toString('base64')}`,
		},
	});
}

export const GetKomApiClient = async (): Promise<APIRequestContext> => {
	return await request.newContext({
		baseURL: `https://api.playground.klarna.com/ordermanagement/v1/`,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				`${KLARNA_API_USERNAME ?? 'admin'}:${KLARNA_API_PASSWORD ?? 'password'}`
			).toString('base64')}`,
		},
	});
}

export const SetKcSettings = async (wcApiClient: APIRequestContext) => {
	// Set api credentials and enable the gateway.
	if (KLARNA_API_USERNAME) {
		const settings = {
			enabled: true,
			settings: {
				testmode: "yes",
				logging: "yes",
				allowed_customer_types: "B2CB",
				allow_separate_shipping: "yes",
				shipping_methods_in_iframe: "no",//"yes",
				test_merchant_id_eu: KLARNA_API_USERNAME,
				test_shared_secret_eu: KLARNA_API_PASSWORD
			}
		};

		// Update settings.
		await wcApiClient.post('payment_gateways/kco', { data: settings });
	}
}

export const HandleKcPopup = async (page: Page) => {
	const klarnaPopup = new KlarnaPopup(await page.waitForEvent('popup'));
 	await klarnaPopup.placeOrder();
}

export const HandleKcIFrame = async (page: Page, separateShipping: boolean = false, asCompany: boolean = false) => {
	const klarnaIFrame = new KlarnaIFrame(page);

	await klarnaIFrame.HandleIFrame(separateShipping, asCompany);
}

// export const HandleKcPopup = async (page: Page) => {
// 	const klarnaPopup = new KlarnaPopup(await page.waitForEvent('popup'));
// 	await klarnaPopup.placeOrder();
// }

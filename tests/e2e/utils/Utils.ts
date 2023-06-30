import { APIRequestContext, Page, request } from "@playwright/test";
import { KlarnaPopup } from "../pages/KlarnaPopup";

const {
	KLARNA_API_USERNAME,
	KLARNA_API_PASSWORD,
} = process.env;

export const GetKpApiClient = async (): Promise<APIRequestContext> => {
	return await request.newContext({
		baseURL: `https://api.playground.klarna.com/payments/v1/`,
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

export const SetKpSettings = async (wcApiClient: APIRequestContext) => {
	// Set api credentials and enable the gateway.
	if (KLARNA_API_USERNAME) {
		const settings = {
			enabled: true,
			settings: {
				testmode: "yes",
				logging: "yes",
				test_merchant_id_se: KLARNA_API_USERNAME,
				test_shared_secret_se: KLARNA_API_PASSWORD,
			}
		};

		// Update settings.
		await wcApiClient.post('payment_gateways/klarna_payments', { data: settings });
	}
}

export const HandleKpPopup = async (page: Page) => {
	const klarnaPopup = new KlarnaPopup(await page.waitForEvent('popup'));
	await klarnaPopup.placeOrder();
}

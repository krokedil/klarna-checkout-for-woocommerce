import { WcPages } from "@krokedil/wc-test-helper";
import { expect } from "@playwright/test";
import { GetKomApiClient } from "./Utils";

export const VerifyOrderRecieved = async (orderRecievedPage: WcPages.OrderReceived, expectedStatus: string = 'processing') => {
	const komClient = await GetKomApiClient();

	// Get the WC Order.
	const wcOrder = await orderRecievedPage.getOrder();

	// Verify that the order has the correct status.
	expect(wcOrder.status).toBe(expectedStatus);

	// Verify that the order has the correct payment method.
	expect(wcOrder.payment_method).toBe('klarna_payments');

	// Get the Klarna order id from the transaction id.
	const klarnaOrder = await GetKlarnaOrderOM(wcOrder, komClient);

	// Verify that the Klarna order has the correct status.
	expect(klarnaOrder.status).toBe('AUTHORIZED');

	// Compare order line totals.
	await VerifyOrderLines(wcOrder, klarnaOrder);
}

const VerifyOrderLines = async (wcOrder: any, klarnaOrder: any) => {
	// toBeCloseTo is used because WooCommerce totals are float values. Klarna totals are integers.
	const klarnaOrderTotal = klarnaOrder.order_amount / 100;
	expect(Number(wcOrder.total)).toBeCloseTo(klarnaOrderTotal);

	for (const wcLineItem of wcOrder.line_items) {
		const klarnaLineItem = klarnaOrder.order_lines.find((klarnaLineItem: any) => klarnaLineItem.reference === wcLineItem.sku);
		expect(klarnaLineItem).not.toBeUndefined();

		const klarnaLineItemTotal = klarnaLineItem.total_amount / 100;
		expect(Number(wcLineItem.total) + Number(wcLineItem.total_tax)).toBeCloseTo(klarnaLineItemTotal);
		expect(Number(wcLineItem.quantity)).toBe(klarnaLineItem.quantity);
	}

	// Compare shipping totals, if the order has any shipping.
	if (wcOrder.shipping_lines.length > 0) {
		const klarnaShippingLine = klarnaOrder.order_lines.find((klarnaLineItem: any) => klarnaLineItem.type === 'shipping_fee');
		expect(klarnaShippingLine).not.toBeUndefined();

		const klarnaShippingPrice = klarnaShippingLine.total_amount / 100;
		expect(Number(wcOrder.shipping_total) + Number(wcOrder.shipping_tax)).toBeCloseTo(klarnaShippingPrice);
	}
}

const GetKlarnaOrderOM = async (wcOrder: any, komClient) => {
	const klarnaOrderId = wcOrder.transaction_id;

	// Get the Klarna order.
	const response = await komClient.get(`orders/${klarnaOrderId}`);
	const klarnaOrder = await response.json();
	return klarnaOrder;
}

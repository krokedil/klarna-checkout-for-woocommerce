const timeOutTime = 2500;

const setCustomerType = async (page, kcoIframe, customerType) => {
	console.log('Situation ----------------- 1');
	if (await kcoIframe.$('[data-cid="am.customer_type"]')) {
		console.log('Situation ----------------- 2');
		let inputField = await kcoIframe.$('[data-cid="am.customer_type"]');
		await inputField.click();
		await page.waitForTimeout(0.1 * timeOutTime);
		if (customerType === "person") {
			console.log('Situation ----------------- 3');
			await kcoIframe.click('[data-cid="row person"]');
			await kcoIframe.waitForTimeout(1 * timeOutTime);

		} else if (customerType === "company") {
			console.log('Situation ----------------- 4');
			await kcoIframe.click('[data-cid="row organization"]');
			await kcoIframe.waitForTimeout(1 * timeOutTime);
		}
	}
}

const processKcoForm = async (page, kcoIframe, customerType) => {
	console.log('Situation ----------------- 5');
	if (await kcoIframe.$("[data-cid='am.postal_code']")) {
		console.log('Situation ----------------- 6');
		let inputField = await kcoIframe.$("[data-cid='am.postal_code']");
		await inputField.click({ clickCount: 3 });
		await inputField.type("67131");
		await kcoIframe.waitForTimeout(0.5 * timeOutTime);
	}

	if (customerType === "company") {
		console.log('Situation ----------------- 7');
		if (await kcoIframe.$("[data-cid='am.organization_registration_id']")) {
			console.log('Situation ----------------- 8');
			let inputField = await kcoIframe.$("[data-cid='am.organization_registration_id']");
			await inputField.click({ clickCount: 3 });
			await inputField.type("002031-0132");
			await kcoIframe.waitForTimeout(1 * timeOutTime);
		}
	} else {
		console.log('Situation ----------------- 8');
		if (await kcoIframe.$("[data-cid='am.email']")) {
			console.log('Situation ----------------- 9');
			let inputField = await kcoIframe.$("[data-cid='am.email']");
			await inputField.click({ clickCount: 3 });
			await inputField.type("e2e@krokedil.se");
			await kcoIframe.waitForTimeout(1 * timeOutTime);
		}
	}

	if (await kcoIframe.$("[data-cid='am.continue_button']")) {
		console.log('Situation ----------------- 10');
		await kcoIframe.click('[data-cid="am.continue_button"]');
	}

	await page.waitForTimeout(0.5 * timeOutTime);

	if (customerType === "company") {
		console.log('Situation ----------------- 11');
		if (await kcoIframe.$("[data-cid='am.email']")) {
			console.log('Situation ----------------- 12');
			let inputField = await kcoIframe.$("[data-cid='am.email']");
			await inputField.click({ clickCount: 3 });
			await inputField.type("e2e@krokedil.se");
		}

		if (await kcoIframe.$("[data-cid='am.organization_name']")) {
			console.log('Situation ----------------- 13');
			let inputField = await kcoIframe.$("[data-cid='am.organization_name']");
			await inputField.click({ clickCount: 3 });
			await inputField.type("Krokedil");
		}
	}

	if (await kcoIframe.$("[data-cid='am.given_name']")) {
		console.log('Situation ----------------- 14');
		let inputField = await kcoIframe.$("[data-cid='am.given_name']");
		await inputField.click({ clickCount: 3 });
		await inputField.type("Test");
	}

	if (await kcoIframe.$("[data-cid='am.family_name']")) {
		console.log('Situation ----------------- 15');
		let inputField = await kcoIframe.$("[data-cid='am.family_name']");
		await inputField.click({ clickCount: 3 });
		await inputField.type("Testsson");
	}

	if (await kcoIframe.$("[data-cid='am.street_address']")) {
		console.log('Situation ----------------- 16');
		let inputField = await kcoIframe.$("[data-cid='am.street_address']");
		await inputField.click({ clickCount: 3 });
		await inputField.click({ clickCount: 3 });
		await inputField.type("Hamngatan 2");
	}

	if (await kcoIframe.$("[data-cid='am.city']")) {
		console.log('Situation ----------------- 17');
		let inputField = await kcoIframe.$("[data-cid='am.city']");
		await inputField.click({ clickCount: 3 });
		await inputField.click({ clickCount: 3 });
		await inputField.type("Arvika");
	}

	if (await kcoIframe.$("[data-cid='am.phone']")) {
		console.log('Situation ----------------- 18');
		let inputField = await kcoIframe.$("[data-cid='am.phone']");
		await inputField.click({ clickCount: 3 });
		await inputField.type("0701234567");
	}

	if (await kcoIframe.$("[data-cid='am.continue_button']")) {
		console.log('Situation ----------------- 19');
		await kcoIframe.click('[data-cid="am.continue_button"]');
	}

	await page.waitForTimeout(3.5 * timeOutTime);
}

const processShipping = async (page, kcoIframe, shippingMethod, shippingInIframe) => {
	if (shippingInIframe === "yes") {
		console.log('Situation ----------------- 20');
		let shippingSelection = await kcoIframe.$$(
			'[data-cid="SHIPMO-shipping-option-basic"]'
		);

		if (shippingMethod === "flat_rate") {
			console.log('Situation ----------------- 21');
			await shippingSelection[0].click();
		} else if (shippingMethod === "free_shipping") {
			console.log('Situation ----------------- 22');
			await shippingSelection[1].click();
		}
	} else {
		console.log('Situation ----------------- 23');
		let shippingMethodTarget = `[id*="_${shippingMethod}"]`;

		if (shippingMethod !== "") {
			console.log('Situation ----------------- 24');
			await page.waitForSelector(shippingMethodTarget).id;
			await page.click(shippingMethodTarget).id;
			await page.waitForTimeout(2 * timeOutTime);
		}
	}
}

const completeOrder = async (page, kcoIframe) => {
	console.log('Situation ----------------- 25');
	if (await kcoIframe.$("[data-cid='button.buy_button']")) {
		console.log('Situation ----------------- 26');
		await kcoIframe.click('[data-cid="button.buy_button"]');
	}

	await page.waitForTimeout(4 * timeOutTime);

	const fullscreenIframe = await page.frames().find((frame) => frame.name() === "klarna-fullscreen-iframe");

	// Check if we have a fullscreenIframe. We might not, since the above step might have already completed the order in some cases.
	if (fullscreenIframe) {
		console.log('Situation ----------------- 26');
		if (await fullscreenIframe.$("[data-cid='payment-selector-method.invoice']")) {
			console.log('Situation ----------------- 27');
			await fullscreenIframe.click('[data-cid="payment-selector-method.invoice"]');
		}

		await page.waitForTimeout(0.25 * timeOutTime);

		if (await fullscreenIframe.$("[data-cid='button.buy_button']")) {
			console.log('Situation ----------------- 28');
			await fullscreenIframe.click('[data-cid="button.buy_button"]');
		}

		await page.waitForTimeout(1 * timeOutTime);



		if (await fullscreenIframe.$("[id='nin']")) {
			console.log('Situation ----------------- 29');
			let inputField = await fullscreenIframe.$("[id='nin']");
			await inputField.click({ clickCount: 3 });
			await inputField.type("410321-9202");

			await page.waitForTimeout(0.25 * timeOutTime);
			if (await fullscreenIframe.$('[id="b2b_nin_dialog__footer-button-wrapper"]')) {
				console.log('Situation ----------------- 30');
				await fullscreenIframe.click('[id="b2b_nin_dialog__footer-button-wrapper"]');
			}
		}

		let addressUpdated = await fullscreenIframe.$('[id="supplement_nin_dialog__footer-button-wrapper"]')

		// Handle potential "Address updated" window.
		if (addressUpdated) {
			console.log('Situation ----------------- 31');
			await fullscreenIframe.click('[id="supplement_nin_dialog__footer-button-wrapper"]');
		} else {
			console.log('Situation ----------------- 32');
			let errorDialog = await fullscreenIframe.$('[id="error-dialog__footer-button-wrapper"]')
			
			if (errorDialog) {
				console.log('Situation ----------------- 33');
				await fullscreenIframe.click('[id="error-dialog__footer-button-wrapper"]');
				await page.waitForTimeout(3 * timeOutTime);

				await kcoIframe.click('[data-cid="button.buy_button"]');
				await page.waitForTimeout(1 * timeOutTime);
			}
		}

		if (await fullscreenIframe.$('[id="supplement_nin_dialog__footer-button-wrapper"]')) {
			console.log('Situation ----------------- 34');
			await fullscreenIframe.click('[id="supplement_nin_dialog__footer-button-wrapper"]');
		}
	}

	console.log('Situation ----------------- 35 ENDED -------- ***');
}

const getOrderData = async (thankyouIframe) => {
	let selector = "[data-cid='payment-method.invoice";
	if (await thankyouIframe.$("[data-cid='payment-method.invoice']")) {
		selector = "[data-cid='payment-method.invoice']";
	} else if (await thankyouIframe.$("[data-cid='payment-method.b2b_invoice")) {
		selector = "[data-cid='payment-method.b2b_invoice']";
	}

	let data = await thankyouIframe.$eval(selector, (element) => {
		let orderLineData = element.childNodes[0];
		let orderTotalData = element.childNodes[1];
		let orderLines = 0;

		for (let i = 0; i < orderLineData.childNodes.length; i++) {
			if (orderLineData.childNodes[i].getElementsByTagName("p").length > 0 && orderLineData.childNodes[i].firstChild.innerHTML !== "Delivery") {
				orderLines++
			}
		}

		let orderTotal = parseFloat(orderTotalData.firstChild.querySelector("div").childNodes[1].innerHTML.replace(",", ".").replace("&nbsp;kr", "").replace("&nbsp;", ""));
		return [orderLines, orderTotal];
	});

	return data;
}

export default {
	setCustomerType,
	processKcoForm,
	processShipping,
	completeOrder,
	getOrderData,
}

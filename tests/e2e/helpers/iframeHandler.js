const timeOutTime = 2500;

const setCustomerType = async (page, kcoIframe, customerType) => {
	if ( await kcoIframe.$('[data-cid="am.customer_type"]') ) {
		let inputField = await kcoIframe.$('[data-cid="am.customer_type"]');
		await inputField.click();
		await page.waitForTimeout(0.1 * timeOutTime);
		if (customerType === "person"){
			await kcoIframe.click('[data-cid="row person"]');
			await kcoIframe.waitForTimeout(1 * timeOutTime);

		} else if (customerType === "company") {
			await kcoIframe.click('[data-cid="row organization"]');
			await kcoIframe.waitForTimeout(1 * timeOutTime);
		}
	}
}

const processKcoForm = async (page, kcoIframe, customerType) => {
	if ( await kcoIframe.$("[data-cid='am.postal_code']") ) {
		let inputField = await kcoIframe.$("[data-cid='am.postal_code']");
		await inputField.click({clickCount: 3});
		await inputField.type("67131");
		await kcoIframe.waitForTimeout(0.5 * timeOutTime);
	}

	if (customerType === "company"){
		if ( await kcoIframe.$("[data-cid='am.organization_registration_id']") ) {
			let inputField = await kcoIframe.$("[data-cid='am.organization_registration_id']");
			await inputField.click({clickCount: 3});
			await inputField.type("002031-0132");
			await kcoIframe.waitForTimeout(1 * timeOutTime);
		}
	} else {
		if ( await kcoIframe.$("[data-cid='am.email']") ) {
			let inputField = await kcoIframe.$("[data-cid='am.email']");
			await inputField.click({clickCount: 3});
			await inputField.type("e2e@krokedil.se");
			await kcoIframe.waitForTimeout(1 * timeOutTime);
		}
	}

	if ( await kcoIframe.$("[data-cid='am.continue_button']") ) {
		await kcoIframe.click('[data-cid="am.continue_button"]');
	}

	await page.waitForTimeout(0.5 * timeOutTime);

	if (customerType === "company"){
		if ( await kcoIframe.$("[data-cid='am.email']") ) {
			let inputField = await kcoIframe.$("[data-cid='am.email']");
			await inputField.click({clickCount: 3});
			await inputField.type("e2e@krokedil.se");
		}

		if ( await kcoIframe.$("[data-cid='am.organization_name']") ) {
			let inputField = await kcoIframe.$("[data-cid='am.organization_name']");
			await inputField.click({clickCount: 3});
			await inputField.type("Krokedil");
		}
	}

	if ( await kcoIframe.$("[data-cid='am.given_name']") ) {
		let inputField = await kcoIframe.$("[data-cid='am.given_name']");
		await inputField.click({clickCount: 3});
		await inputField.type("Test");
	}

	if ( await kcoIframe.$("[data-cid='am.family_name']") ) {
		let inputField = await kcoIframe.$("[data-cid='am.family_name']");
		await inputField.click({clickCount: 3});
		await inputField.type("Testsson");
	}

	if ( await kcoIframe.$("[data-cid='am.street_address']") ) {
		let inputField = await kcoIframe.$("[data-cid='am.street_address']");
		await inputField.click({clickCount: 3});
		await inputField.click({clickCount: 3});
		await inputField.type("Hamngatan 2");
	}

	if ( await kcoIframe.$("[data-cid='am.city']") ) {
		let inputField = await kcoIframe.$("[data-cid='am.city']");
		await inputField.click({clickCount: 3});
		await inputField.click({clickCount: 3});
		await inputField.type("Arvika");
	}

	if ( await kcoIframe.$("[data-cid='am.phone']") ) {
		let inputField = await kcoIframe.$("[data-cid='am.phone']");
		await inputField.click({clickCount: 3});
		await inputField.type("0701234567");
	}

	if ( await kcoIframe.$("[data-cid='am.continue_button']") ) {
		await kcoIframe.click('[data-cid="am.continue_button"]');
	}

	await page.waitForTimeout(3.5 * timeOutTime);
}

const processShipping = async (page, kcoIframe, shippingMethod, shippingInIframe) => {
	if ( shippingInIframe === "yes" ) {
		let shippingSelection = await kcoIframe.$$(
			'[data-cid="SHIPMO-shipping-option-basic"]'
		);

		if (shippingMethod === "flat_rate") {
			await shippingSelection[0].click();
		} else if (shippingMethod === "free_shipping") {
			await shippingSelection[1].click();
		}
	} else {
		let shippingMethodTarget = `[id*="_${shippingMethod}"]`;

		if (shippingMethod !== "") {
			await page.waitForSelector(shippingMethodTarget).id;
			await page.click(shippingMethodTarget).id;
			await page.waitForTimeout(2 * timeOutTime);
		}
	}
}

const completeOrder = async (page, kcoIframe) => {
	if ( await kcoIframe.$("[data-cid='button.buy_button']") ) {
		await kcoIframe.click('[data-cid="button.buy_button"]');
	}

	await page.waitForTimeout(4 * timeOutTime);
	
	const fullscreenIframe = await page.frames().find((frame) => frame.name() === "klarna-fullscreen-iframe");

	// Check if we have a fullscreenIframe. We might not, since the above step might have already completed the order in some cases.
	if( fullscreenIframe ) {
		if ( await fullscreenIframe.$("[data-cid='payment-selector-method.invoice']") ) {
			await fullscreenIframe.click('[data-cid="payment-selector-method.invoice"]');
		}

		await page.waitForTimeout(0.25 * timeOutTime);

		if ( await fullscreenIframe.$("[data-cid='button.buy_button']") ) {
			await fullscreenIframe.click('[data-cid="button.buy_button"]');
		}

		await page.waitForTimeout(1 * timeOutTime);

		if ( await fullscreenIframe.$("[id='nin']") ) {
			let inputField = await fullscreenIframe.$("[id='nin']");
			await inputField.click({clickCount: 3});
			await inputField.type("410321-9202");
			
			await page.waitForTimeout(0.25 * timeOutTime);
			if ( await fullscreenIframe.$('[id="b2b_nin_dialog__footer-button-wrapper"]') ) {
				await fullscreenIframe.click('[id="b2b_nin_dialog__footer-button-wrapper"]');
			}
		}

		await page.waitForTimeout(0.25 * timeOutTime);

		if ( await fullscreenIframe.$('[id="supplement_nin_dialog__footer-button-wrapper"]') ) {
			await fullscreenIframe.click('[id="supplement_nin_dialog__footer-button-wrapper"]');
		}
	}
}

const getOrderData = async (thankyouIframe) => {
	let selector = "[data-cid='payment-method.invoice";
	if( await thankyouIframe.$("[data-cid='payment-method.invoice']") ) {
		selector = "[data-cid='payment-method.invoice']";
	} else if( await thankyouIframe.$("[data-cid='payment-method.b2b_invoice") ) {
		selector = "[data-cid='payment-method.b2b_invoice']";
	}

	let data = await thankyouIframe.$eval(selector, (element) => {
		let orderLineData = element.childNodes[0];
		let orderTotalData = element.childNodes[1];
		let orderLines = 0;
	
		for(let i = 0; i < orderLineData.childNodes.length; i++) {
			if(orderLineData.childNodes[i].getElementsByTagName("p").length > 0 && orderLineData.childNodes[i].firstChild.innerHTML !== "Delivery") {
				orderLines++
			}
		}

		let orderTotal = parseFloat( orderTotalData.firstChild.querySelector("div").childNodes[1].innerHTML.replace(",", ".").replace("&nbsp;kr", "").replace("&nbsp;", "") );
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

/**
 *
 * @param page
 * @param name
 * @returns {Promise<*|Frame>}
 */
const loadIFrame = async (page, name) =>
	page.frames().find((frame) => frame.name() === name);

/**
 *
 * @param frame
 * @param data
 * @returns {Promise<void>}
 */

const submitBillingForm = async (frame, data, customerType) => {
	const {
		emailSelector,
		email,
		postalCodeSelector,
		postalCode,
		organizationIDSelector,
		organizationID,
		organizationNameSelector,
		organizationName,
		firstNameSelector,
		firstName,
		lastNameSelector,
		lastName,
		telephoneSelector,
		telephone

	} = data;

	const emptyField = async (fieldName) => {
		if (await frame.$(fieldName)){
			let inputField = await frame.$(fieldName);
			await frame.waitForTimeout(200);
			await inputField.click({clickCount: 3});
			await inputField.press("Backspace");
			await frame.waitForTimeout(200);
		}
	}

	// Fill out input field
	const fillOutFrameField = async (fieldName, inputFieldFillIn) => {
		if (await frame.$(fieldName)){
			let inputField = await frame.$(fieldName);
			await frame.waitForTimeout(200);
			await inputField.type(inputFieldFillIn);
			await frame.waitForTimeout(200);
		}
	}

	// Fill out the form
	const completeForm = async () => {
		await emptyField(organizationIDSelector);
		await emptyField(organizationNameSelector);
		await emptyField(firstNameSelector);
		await emptyField(lastNameSelector);
		await emptyField(emailSelector);
		await emptyField(telephoneSelector);
		// ---------------------------------------------------------------- //
		await fillOutFrameField(organizationIDSelector,organizationID );
		await fillOutFrameField(organizationNameSelector, organizationName);
		await fillOutFrameField(firstNameSelector, firstName);
		await fillOutFrameField(lastNameSelector, lastName);
		await fillOutFrameField(emailSelector, email);
		await fillOutFrameField(telephoneSelector, telephone);
	}
	
	// Check for miniaturized frame
	if(	frame && await frame.$('[id="preview__link"]')) {
		await frame.waitForTimeout(1000);
		await frame.click('[id="preview__link"]');
		await frame.waitForTimeout(1000);
	}
	
	// Fork from B2CB switches
	if ( frame && await frame.$('[data-cid="am.customer_type"]')) {

		await frame.waitForTimeout(1000);
		await frame.click('[data-cid="am.customer_type"]');
		await frame.waitForTimeout(1000);

		if (customerType === "person"){
			await frame.click('[data-cid="row person"]');
			await frame.waitForTimeout(1000);
			await completeForm()

		} else if (customerType === "company") {
			await frame.click('[data-cid="row organization"]');
			await frame.waitForTimeout(1000);
			await completeForm()
		}

		let postalCodeSelectorInput = await frame.$(postalCodeSelector);

		await postalCodeSelectorInput.click({clickCount: 3});
		await postalCodeSelectorInput.press('Backspace');
		await frame.type(postalCodeSelector, postalCode);

		await frame.click('[data-cid="am.continue_button"]');
		await frame.waitForTimeout(1000);

		if(frame && await frame.$('[data-cid="am.continue_button"]')){
			await completeForm()
			await frame.click('[data-cid="am.continue_button"]');
		}
	}
};

/**
 *
 * @param frame
 * @param buttonSelector
 * @returns {*}
 */
const createOrder = async (frame, buttonSelector) => {
	await frame.waitForSelector(buttonSelector);
	await frame.click(buttonSelector);
};

export default {
	loadIFrame,
	submitBillingForm,
	createOrder,
};

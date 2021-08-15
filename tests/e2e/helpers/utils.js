import API from "../api/API";
import urls from "./urls";

const timeOutTime = 1500;
const KCOSettingsArray = {
	woocommerce_kco_settings: {
		enabled: "yes",
		title: "Klarna",
		description: "Klarna Checkout for WooCommerce Test",
		select_another_method_text: "",
		testmode: "yes",
		logging: "yes",
		credentials_eu: "",
		merchant_id_eu: "",
		shared_secret_eu: "",
		test_merchant_id_eu: process.env.API_KEY,
		test_shared_secret_eu: process.env.API_SECRET,
		credentials_us: "",
		merchant_id_us: "",
		shared_secret_us: "",
		test_merchant_id_us: "",
		test_shared_secret_us: "",
		shipping_section: "",
		allow_separate_shipping: "no",
		shipping_methods_in_iframe: "yes",
		shipping_details: "",
		checkout_section: "",
		send_product_urls: "yes",
		dob_mandatory: "no",
		display_privacy_policy_text: "no",
		add_terms_and_conditions_checkbox: "no",
		allowed_customer_types: "B2CB",
		title_mandatory: "yes",
		prefill_consent: "yes",
		quantity_fields: "yes",
		color_settings_title: "",
		color_button: "",
		color_button_text: "",
		color_checkbox: "",
		color_checkbox_checkmark: "",
		color_header: "",
		color_link: "",
		radius_border: "",
		add_to_email: "no",
	},
};

const login = async (page, username, password) => {
	await page.type("#username", username);
	await page.type("#password", password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

const applyCoupons = async (page, appliedCoupons) => {
	if (appliedCoupons.length > 0) {
		await appliedCoupons.forEach(async (singleCoupon) => {
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(500);
			await page.type('[name="coupon_code"]', singleCoupon);
			await page.click('[name="apply_coupon"]');
		});
	}
	await page.waitForTimeout(3 * timeOutTime);
};

const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${urls.ADD_TO_CART}${productSelector}`);
		await page.goto(urls.SHOP);
	} catch {
		// Proceed
	}
};

const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 800);
	let ids = [];

	products.forEach( name => {
		data.products.simple.forEach(product => {
			if(name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach(product => {
			product.attribute.options.forEach(variation => {
				if(name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	(async function addEachProduct() {
		for (let i = 0; i < ids.length + 1; i += 1) {
			await addSingleProductToCart(page, ids[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

const setPricesIncludesTax = async (value) => {
	await API.pricesIncludeTax(value);
};

const setIframeShipping = async (toggleSwitch) => {
	KCOSettingsArray.woocommerce_kco_settings.shipping_methods_in_iframe = toggleSwitch;
	await API.updateOptions(KCOSettingsArray);
};

const selectKco = async (page) => {
	if (await page.$('input[id="payment_method_kco"]')) {
		await page.evaluate(
			(paymentMethod) => paymentMethod.click(),
			await page.$('input[id="payment_method_kco"]')
		);
	}
}

export default {
	login,
	applyCoupons,
	addSingleProductToCart,
	addMultipleProductsToCart,
	setPricesIncludesTax,
	setIframeShipping,
	selectKco,
};

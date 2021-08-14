import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;
export const productIdData = config.products.product_id;

const settingsArray = {
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
		shipping_methods_in_iframe:
			customerData.shippingSelectors.iframe.iframeShipping,
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

export const shippingTargets = customerData.shipping.targets;
export const paymentSelectedMethod = customerData.payment.selectedMethod;
export const customerKey = customerData.api.consumerKey;
export const customerSecret = customerData.api.consumerSecret;
export const klarnaAuth = customerData.klarnaCredentials;

export const shippingSel = customerData.shippingSelectors;
export const freeShippingMethod = shippingSel.methods.freeShipping;
export const flatRateMethod = shippingSel.methods.flatRate;
export const freeShippingMethodTarget = shippingSel.targets.freeShippingTarget;
export const flatRateMethodTarget = shippingSel.targets.flatRateTarget;

export const creditPaymentMethod = paymentSelectedMethod.creditMethod;
export const debitPaymentMethod = paymentSelectedMethod.debitMethod;
export const invoicePaymentMethod = paymentSelectedMethod.invoiceMethod;

export const customerName = customerData.first_name;
export const customerLastname = customerData.last_name;
export const customerEmail = customerData.email;
export const customerUsername = customerData.username;

export const outOfStock = productIdData.out_of_stock;
export const variable25 = productIdData.variable_25;
export const downloadable0 = productIdData.downloadable_0;
export const downloadable25 = productIdData.downloadable_25;
export const downloadableShipping0 = productIdData.downloadable_shipping_0;
export const downloadableShipping25 = productIdData.downloadable_shipping_25;
export const simple12 = productIdData.simple_12;
export const simple6 = productIdData.simple_6;
export const virtual0 = productIdData.virtual_0;
export const virtual25 = productIdData.virtual_25;
export const virtualDownloadable0 = productIdData.virtual_downloadable_0;
export const virtualDownloadable25 = productIdData.virtual_downloadable_25;
export const manyCharacters = productIdData.many_characters;

export const couponFixedCart = customerData.coupons.fixed_cart;
export const couponFixedProduct = customerData.coupons.fixed_product;
export const couponPercent = customerData.coupons.percent;
export const couponTotalFreeShipping = customerData.coupons.free_shipping;
export const couponTotalWithShipping = customerData.coupons.charged_shipping;

export const { pinNumber } = customerData;
export const { cardNumber } = customerData;

export const timeOutTime = config.timeoutTime;
export const { billing } = customerData;
export const { shipping } = customerData;
export const customerAPIData = {
	email: customerEmail,
	first_name: customerName,
	last_name: customerLastname,
	username: customerUsername,
	billing,
	shipping,
};
export const { billingData } = customerData;

export const userCredentials = customerData.credentialsAndSelectors;

export const { klarnaOrderEndpoint } = adminData;
export const { puppeteerOptions } = config;

export const KCOSettingsArray = settingsArray;

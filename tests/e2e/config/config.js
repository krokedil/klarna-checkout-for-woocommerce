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
		test_merchant_id_eu: customerData.klarnaCredentials.test_merchant_id_eu,
		test_shared_secret_eu: customerData.klarnaCredentials.test_shared_secret_eu,
		credentials_us: "",
		merchant_id_us: "",
		shared_secret_us: "",
		test_merchant_id_us: "",
		test_shared_secret_us: "",
		shipping_section: "",
		allow_separate_shipping: "no",
		shipping_methods_in_iframe: customerData.shippingSelectors.iframe.iframeShipping,
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
	}
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

export const simpleProduct25 = productIdData.simple_25;
export const simpleProduct12 = productIdData.simple_12;
export const simpleProduct6 = productIdData.simple_6;
export const simpleProduct0 = productIdData.simple_0;
export const simpleProductSale25 = productIdData.simple_sale_25;
export const simpleProductSale12 = productIdData.simple_sale_12;
export const simpleProductSale6 = productIdData.simple_sale_6;
export const simpleProductSale0 = productIdData.simple_sale_0;
export const variableProduct25Black = productIdData.variable_25_black;
export const variableProduct25Blue = productIdData.variable_25_blue;
export const variableProduct25Brown = productIdData.variable_25_brown;
export const variableProduct25Green = productIdData.variable_25_green;
export const variableProductMixedBlackS = productIdData.variable_mix_black_s;
export const variableProductMixedBlackM = productIdData.variable_mix_black_m;
export const variableProductMixedBlackL = productIdData.variable_mix_black_l;
export const variableProductMixedBlackXL = productIdData.variable_mix_black_xl;
export const variableProductMixedGreenS = productIdData.variable_mix_green_s;
export const variableProductMixedGreenM = productIdData.variable_mix_green_m;
export const variableProductMixedGreenL = productIdData.variable_mix_green_l;
export const variableProductMixedGreenXL = productIdData.variable_mix_green_xl;
export const variableProductVirtualDownloadable25 =
	productIdData.virtual_downloadable_25;
export const variableProductVirtualDownloadable12 =
	productIdData.virtual_downloadable_12;
export const variableProductVirtualDownloadable6 =
	productIdData.virtual_downloadable_6;
export const variableProductVirtualDownloadable0 =
	productIdData.virtual_downloadable_0;
export const variableProductVirtualDownloadableSale25 =
	productIdData.virtual_downloadable_sale_25;
export const variableProductVirtualDownloadableSale12 =
	productIdData.virtual_downloadable_sale_12;
export const variableProductVirtualDownloadableSale6 =
	productIdData.virtual_downloadable_sale_6;
export const variableProductVirtualDownloadableSale0 =
	productIdData.virtual_downloadable_0;

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
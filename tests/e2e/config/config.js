import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;
export const productIdData = config.products.product_id;

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

export const { iframeShipping } = shippingSel.targets;

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

export const couponFixedCart = customerData.coupons.fixed_cart_30;
export const couponFixedProduct = customerData.coupons.fixed_product_25;
export const couponPercent = customerData.coupons.percent_50;
export const couponTotalFreeShipping = customerData.coupons.free_shipping_100;
export const couponTotalWithShipping = customerData.coupons.charged_shipping_100;

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

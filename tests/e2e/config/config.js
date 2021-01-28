import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;
export const shippingTargets = customerData.shipping.targets;
export const paymentSelectedMethod = customerData.payment.selectedMethod;
export const customerKey = customerData.api.consumerKey;
export const customerSecret = customerData.api.consumerSecret;
export const klarnaAuth = customerData.klarnaCredentials;
export const shippingSel = customerData.shippingSelectors;
export const freeShippingMethod = shippingSel.methods.freeShipping;
export const flatRateMethod = shippingSel.methods.flatRate;


export const iframeShipping = shippingSel.targets.iframeShipping;

export const freeShippingMethodTarget = shippingSel.targets.freeShippingTarget;
export const flatRateMethodTarget = shippingSel.targets.flatRateTarget;

export const creditPaymentMethod = paymentSelectedMethod.creditMethod;
export const debitPaymentMethod = paymentSelectedMethod.debitMethod;
export const invoicePaymentMethod = paymentSelectedMethod.invoiceMethod;

export const customerName = customerData.first_name;
export const customerLastname = customerData.last_name;
export const customerEmail = customerData.email;
export const customerUsername = customerData.username;

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

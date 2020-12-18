import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;

export const customerKey = config.users.customer.api.consumerKey;
export const customerSecret = config.users.customer.api.consumerSecret;
export const klarnaAuth = config.users.customer.klarnaCredentials;

export const freeShippingMethod =
	config.users.customer.shipping.methods.freeShipping;
export const flatRateMethod = config.users.customer.shipping.methods.flatRate;

export const freeShippingMethodTarget =
	config.users.customer.shipping.targets.freeShippingTarget;
export const flatRateMethodTarget =
	config.users.customer.shipping.targets.flatRateTarget;

export const creditPaymentMethod =
	config.users.customer.payment.selectedMethod.creditMethod;
export const debitPaymentMethod =
	config.users.customer.payment.selectedMethod.debitMethod;
export const invoicePaymentMethod =
	config.users.customer.payment.selectedMethod.invoiceMethod;

export const { pinNumber } = config.users.customer;
export const { cardNumber } = config.users.customer;

export const timeOutTime = config.timeoutTime;
export const { billingData } = config.users.customer;

export const userCredentials = config.users.customer.credentialsAndSelectors;

export const { klarnaOrderEndpoint } = config.users.admin;

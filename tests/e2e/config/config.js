import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;
export const shippingTargets = customerData.shipping.targets;
export const paymentSelectedMethod = customerData.payment.selectedMethod;
export const customerKey = customerData.api.consumerKey;
export const customerSecret = customerData.api.consumerSecret;
export const klarnaAuth = customerData.klarnaCredentials;

export const freeShippingMethod = customerData.shipping.methods.freeShipping;
export const flatRateMethod = customerData.shipping.methods.flatRate;

export const freeShippingMethodTarget = shippingTargets.freeShippingTarget;
export const flatRateMethodTarget = shippingTargets.flatRateTarget;

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
